<?php
// data.php — central backend for LibNest
session_start();
header('Content-Type: application/json; charset=utf-8');

// --- CONFIG ---
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'libnest_db';
$uploadDir = __DIR__ . '/uploads/'; // ensure writable
$baseUploadUrl = 'uploads/'; // used in responses

// Connect
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed: ' . $conn->connect_error]);
    exit;
}

// Ensure uploads dir exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// --- Convenience: create default admin if none exists ---
$checkAdmin = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='admin'");
if ($checkAdmin) {
    $row = $checkAdmin->fetch_assoc();
    if ($row['c'] == 0) {
        $email = 'admin@libnest.local';
        $name = 'LibNest Admin';
        $pass = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
        $stmt->bind_param("sss", $name, $email, $pass);
        $stmt->execute();
        // don't output anything here (we're JSON endpoint)
    }
}

// --- Helpers ---
function respond($data) { echo json_encode($data); exit; }
function require_login() {
    if (!isset($_SESSION['user'])) respond(['error'=>'login_required']);
}
function is_admin() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}
function sanitize($s) {
    return trim($s);
}
function upload_cover($file) {
    global $uploadDir, $baseUploadUrl;
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array(strtolower($ext), $allowed)) return null;
    $name = uniqid('cov_') . '.' . $ext;
    $dest = $uploadDir . $name;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return $baseUploadUrl . $name;
    }
    return null;
}

// --- Routing by action param (POST or GET) ---
$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? ''; // allow both GET/POST

// ------------- AUTH: register, login, logout -------------
if ($action === 'register' && $method === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $student_id = sanitize($_POST['student_id'] ?? null);
    $password = $_POST['password'] ?? '';

    if (!$name || !$email || !$password) respond(['error'=>'missing_fields']);
    // check exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) respond(['error'=>'email_taken']);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, student_id, password_hash, role) VALUES (?, ?, ?, ?, 'student')");
    $stmt->bind_param("ssss", $name, $email, $student_id, $hash);
    $stmt->execute();
    respond(['ok'=>true]);
}

if ($action === 'login' && $method === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) respond(['error'=>'missing_fields']);
    $stmt = $conn->prepare("SELECT user_id,name,email,student_id,password_hash,role FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if (!$res) respond(['error'=>'invalid']);
    if (!password_verify($password, $res['password_hash'])) respond(['error'=>'invalid']);
    unset($res['password_hash']);
    $_SESSION['user'] = $res;
    respond(['ok'=>true, 'user'=>$res]);
}

if ($action === 'logout') {
    session_destroy();
    // if POST or GET, return JSON
    respond(['ok'=>true]);
}

// ------------- BOOKS CRUD & search -------------
if ($action === 'save_book' && $method === 'POST') {
    if (!is_admin()) respond(['error'=>'admin_required']);
    $book_id = !empty($_POST['book_id']) ? intval($_POST['book_id']) : null;
    $title = sanitize($_POST['title'] ?? '');
    $author = sanitize($_POST['author'] ?? '');
    $isbn = sanitize($_POST['isbn'] ?? '');
    $publication_year = intval($_POST['publication_year'] ?? 0);
    $category = sanitize($_POST['category'] ?? null);
    $cover_url = null;
    if (!empty($_FILES['cover_image'])) {
        $cover_url = upload_cover($_FILES['cover_image']);
    }
    if (!$title || !$author || !$isbn || !$publication_year) respond(['error'=>'missing_fields']);
    // find/create category id
    $category_id = null;
    if ($category) {
        $stmt = $conn->prepare("SELECT category_id FROM categories WHERE category_name=?");
        $stmt->bind_param("s",$category);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if ($res) $category_id = $res['category_id'];
        else {
            $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
            $stmt->bind_param("s",$category);
            $stmt->execute();
            $category_id = $conn->insert_id;
        }
    }
    if ($book_id) {
        // update
        if ($cover_url) {
            $stmt = $conn->prepare("UPDATE books SET title=?,author=?,isbn=?,publication_year=?,category_id=?,cover_image=? WHERE book_id=?");
            $stmt->bind_param("sssiisi",$title,$author,$isbn,$publication_year,$category_id,$cover_url,$book_id);
        } else {
            $stmt = $conn->prepare("UPDATE books SET title=?,author=?,isbn=?,publication_year=?,category_id=? WHERE book_id=?");
            $stmt->bind_param("sssiii",$title,$author,$isbn,$publication_year,$category_id,$book_id);
        }
        $stmt->execute();
        respond(['ok'=>true, 'updated_id'=>$book_id]);
    } else {
        // insert
        $stmt = $conn->prepare("INSERT INTO books (title,author,isbn,category_id,publication_year,cover_image) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("sssiis",$title,$author,$isbn,$category_id,$publication_year,$cover_url);
        $stmt->execute();
        respond(['ok'=>true, 'inserted_id'=>$conn->insert_id]);
    }
}

if ($action === 'delete_book' && $method === 'POST') {
    if (!is_admin()) respond(['error'=>'admin_required']);
    $id = intval($_POST['book_id'] ?? 0);
    if (!$id) respond(['error'=>'missing']);
    $stmt = $conn->prepare("DELETE FROM books WHERE book_id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    respond(['ok'=>true]);
}

// Search / fetch books (public)
if ($action === 'search_books' && $method === 'GET') {
    $q = '%' . ($conn->real_escape_string($_GET['q'] ?? '')) . '%';
    $category = $conn->real_escape_string($_GET['category'] ?? '');
    $sql = "SELECT b.*, c.category_name FROM books b LEFT JOIN categories c ON b.category_id=c.category_id WHERE (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
    if ($category) {
        $sql .= " AND c.category_name = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss",$q,$q,$q,$category);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss",$q,$q,$q);
    }
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    respond(['ok'=>true,'books'=>$res]);
}

// Fetch categories
if ($action === 'list_categories') {
    $r = $conn->query("SELECT * FROM categories ORDER BY category_name");
    $rows = $r->fetch_all(MYSQLI_ASSOC);
    respond(['ok'=>true,'categories'=>$rows]);
}

// ------------- USER MANAGEMENT (admin) -------------
if ($action === 'create_user' && $method === 'POST') {
    if (!is_admin()) respond(['error'=>'admin_required']);
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $student_id = sanitize($_POST['student_id'] ?? null);
    $role = in_array($_POST['role'] ?? 'student',['student','admin']) ? $_POST['role'] : 'student';
    $password = $_POST['password'] ?? '';
    if (!$name || !$email || !$password) respond(['error'=>'missing_fields']);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name,email,student_id,password_hash,role) VALUES (?,?,?,?,?)");
    $stmt->bind_param("sssss",$name,$email,$student_id,$hash,$role);
    $stmt->execute();
    respond(['ok'=>true,'user_id'=>$conn->insert_id]);
}

// ------------- TRANSACTIONS: borrow, return, list -------------
if ($action === 'borrow' && $method === 'POST') {
    require_login();
    $user = $_SESSION['user'];
    $book_id = intval($_POST['book_id'] ?? 0);
    if (!$book_id) respond(['error'=>'missing']);
    // check availability
    $stmt = $conn->prepare("SELECT status FROM books WHERE book_id=?");
    $stmt->bind_param("i",$book_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) respond(['error'=>'not_found']);
    if ($row['status'] !== 'available') respond(['error'=>'not_available']);
    $issue_date = date('Y-m-d');
    $due_date = date('Y-m-d', strtotime('+14 days')); // 2 weeks default
    // create transaction
    $stmt = $conn->prepare("INSERT INTO transactions (user_id,book_id,issue_date,due_date,status) VALUES (?,?,?,?, 'borrowed')");
    $stmt->bind_param("iiss",$user['user_id'],$book_id,$issue_date,$due_date);
    $stmt->execute();
    // update book status
    $stmt = $conn->prepare("UPDATE books SET status='borrowed' WHERE book_id=?");
    $stmt->bind_param("i",$book_id);
    $stmt->execute();
    respond(['ok'=>true,'transaction_id'=>$conn->insert_id,'due_date'=>$due_date]);
}

if ($action === 'return' && $method === 'POST') {
    require_login();
    $user = $_SESSION['user'];
    $transaction_id = intval($_POST['transaction_id'] ?? 0);
    if (!$transaction_id) respond(['error'=>'missing']);
    // fetch transaction
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE transaction_id=?");
    $stmt->bind_param("i",$transaction_id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();
    if (!$tx) respond(['error'=>'tx_not_found']);
    if ($tx['status'] === 'returned') respond(['error'=>'already_returned']);
    $return_date = date('Y-m-d');
    $due = $tx['due_date'];
    $fine = 0.00;
    if ($return_date > $due) {
        $days = (strtotime($return_date) - strtotime($due)) / 86400;
        $fine = round($days * 0.50,2); // default RM0.50 per day (you can change)
        $status = 'overdue';
    } else {
        $status = 'returned';
    }
    $stmt = $conn->prepare("UPDATE transactions SET return_date=?, status=?, fine=? WHERE transaction_id=?");
    $stmt->bind_param("ssdi",$return_date,$status,$fine,$transaction_id);
    $stmt->execute();
    // update book status to available
    $stmt = $conn->prepare("UPDATE books SET status='available' WHERE book_id=?");
    $stmt->bind_param("i",$tx['book_id']);
    $stmt->execute();
    respond(['ok'=>true,'fine'=>$fine,'status'=>$status]);
}

// list borrowed for current user
if ($action === 'my_borrowed') {
    require_login();
    $uid = $_SESSION['user']['user_id'];
    $stmt = $conn->prepare("SELECT t.*, b.title, b.author FROM transactions t JOIN books b ON t.book_id=b.book_id WHERE t.user_id=? ORDER BY t.issue_date DESC");
    $stmt->bind_param("i",$uid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    respond(['ok'=>true,'borrowed'=>$rows]);
}

// list overdue (admin)
if ($action === 'list_overdue') {
    if (!is_admin()) respond(['error'=>'admin_required']);
    $r = $conn->query("SELECT t.*, u.name AS user_name, b.title FROM transactions t JOIN users u ON t.user_id=u.user_id JOIN books b ON t.book_id=b.book_id WHERE t.status='overdue' OR (t.status='borrowed' AND t.due_date < CURDATE())");
    $rows = $r->fetch_all(MYSQLI_ASSOC);
    respond(['ok'=>true,'overdue'=>$rows]);
}

// ------------- Reservations -------------
if ($action === 'reserve' && $method === 'POST') {
    require_login();
    $user = $_SESSION['user'];
    $book_id = intval($_POST['book_id'] ?? 0);
    if (!$book_id) respond(['error'=>'missing']);
    // create reservation
    $stmt = $conn->prepare("INSERT INTO reservations (user_id,book_id) VALUES (?,?)");
    $stmt->bind_param("ii",$user['user_id'],$book_id);
    $stmt->execute();
    // if book is available, set status to reserved
    $stmt2 = $conn->prepare("SELECT status FROM books WHERE book_id=?");
    $stmt2->bind_param("i",$book_id);
    $stmt2->execute();
    $st = $stmt2->get_result()->fetch_assoc();
    if ($st && $st['status'] === 'available') {
        $stmt3 = $conn->prepare("UPDATE books SET status='reserved' WHERE book_id=?");
        $stmt3->bind_param("i",$book_id);
        $stmt3->execute();
    }
    respond(['ok'=>true,'reservation_id'=>$conn->insert_id]);
}

// ------------- Backup / export (admin) -------------
if ($action === 'export_backup' && $method === 'POST') {
    if (!is_admin()) respond(['error'=>'admin_required']);
    // simple export: dump tables to SQL-ish text (very simple)
    $tables = ['users','categories','books','transactions','reservations'];
    $dump = "-- LibNest simple DB export\n";
    foreach ($tables as $t) {
        $res = $conn->query("SELECT * FROM $t");
        while ($r = $res->fetch_assoc()) {
            $cols = array_map(function($c){ return "`$c`";}, array_keys($r));
            $vals = array_map(function($v){ return "'" . addslashes($v) . "'";}, array_values($r));
            $dump .= "INSERT INTO `$t` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
        }
    }
    // send as download
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="libnest_backup_'.date('Ymd_His').'.sql"');
    echo $dump;
    exit;
}

// Default: if no action, act as JSON index — tell what can be used
respond(['ok'=>true,'msg'=>'LibNest backend running. Provide action parameter.']);
