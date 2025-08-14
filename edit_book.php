<?php
include 'db.php';
$id = $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $author = $_POST['author'];
    $category = $_POST['category'];
    $year = $_POST['year'];
    $status = $_POST['status'];

    $sql = "UPDATE books SET title='$title', author='$author', category='$category', year='$year', status='$status' WHERE id=$id";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Book updated successfully!'); window.location.href='books.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}

$result = $conn->query("SELECT * FROM books WHERE id=$id");
$book = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Book - Cozy Library</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Patrick+Hand&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <h1>✏️ Edit Book</h1>
        <p>Update your book details</p>
    </header>

    <nav>
        <a href="index.html">🏠 Home</a>
        <a href="books.php">📖 View Books</a>
    </nav>

    <div class="container">
        <form method="POST" class="cozy-form">
            <label>Title:</label>
            <input type="text" name="title" value="<?= $book['title'] ?>" required>

            <label>Author:</label>
            <input type="text" name="author" value="<?= $book['author'] ?>" required>

            <label>Category:</label>
            <input type="text" name="category" value="<?= $book['category'] ?>">

            <label>Year:</label>
            <input type="number" name="year" value="<?= $book['year'] ?>">

            <label>Status:</label>
            <select name="status">
                <option value="Available" <?= $book['status']=="Available" ? "selected" : "" ?>>Available</option>
                <option value="Borrowed" <?= $book['status']=="Borrowed" ? "selected" : "" ?>>Borrowed</option>
            </select>

            <button type="submit">Update Book</button>
        </form>
    </div>

    <footer>
        <p>Made with ❤️ & coffee</p>
    </footer>
</body>
</html>
