<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: index.html");
    exit;
}
$user = $_SESSION['user'];
if ($user['role'] !== 'admin') {
    echo "Access denied. Admins only.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>LibNest — Admin Panel</title>
  <link rel="stylesheet" href="admin.css">
  <script>
    // simple confirm for delete actions
    function confirmDelete(form){
      if(confirm('Delete this record?')) form.submit();
      return false;
    }
  </script>
</head>
<body>
  <div class="container">
    <header><h1>LibNest — Admin Panel</h1></header>

    <section class="card">
      <h2>Books — Add / Edit</h2>
      <form method="POST" action="data.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_book">
        <input type="hidden" name="book_id" value="">
        <input name="title" placeholder="Title" required>
        <input name="author" placeholder="Author" required>
        <input name="isbn" placeholder="ISBN" required>
        <input name="publication_year" placeholder="Publication Year (YYYY)" type="number" min="1000" max="9999" required>
        <input name="category" placeholder="Category/Genre">
        <label>Cover image (optional)</label>
        <input type="file" name="cover_image" accept="image/*">
        <button type="submit">Save Book</button>
      </form>
    </section>

    <section class="card">
      <h2>Users — Create / Manage</h2>
      <form method="POST" action="data.php">
        <input type="hidden" name="action" value="create_user">
        <input name="name" placeholder="Full name" required>
        <input name="email" placeholder="Email" type="email" required>
        <input name="student_id" placeholder="Student ID (optional)">
        <select name="role"><option value="student">Student</option><option value="admin">Admin</option></select>
        <input name="password" placeholder="Password (plain - will be hashed)" required>
        <button type="submit">Create user</button>
      </form>
    </section>

    <section class="card">
      <h2>Quick Actions</h2>
      <form method="POST" action="data.php" style="display:inline;">
        <input type="hidden" name="action" value="export_backup">
        <button type="submit">Export DB Backup (SQL)</button>
      </form>
      &nbsp;
      <form method="POST" action="data.php" style="display:inline;">
        <input type="hidden" name="action" value="list_overdue">
        <button type="submit">List Overdue</button>
      </form>
    </section>

    <section class="card">
      <h2>Manage</h2>
      <p>To edit or delete records, use the API endpoints (this admin view is kept minimal). You can use tools like Postman, or extend the UI further. Basic endpoints are in <code>data.php</code>.</p>
      <p><a href="index.html">Back to site</a></p>
      <form method="POST" action="data.php"><input type="hidden" name="action" value="logout"><button type="submit">Logout</button></form>
    </section>

    <footer><small>LibNest Admin</small></footer>
  </div>
</body>
</html>
