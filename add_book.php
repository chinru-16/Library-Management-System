<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $author = $_POST['author'];
    $category = $_POST['category'];
    $year = $_POST['year'];
    $status = $_POST['status'];

    $sql = "INSERT INTO books (title, author, category, year, status) 
            VALUES ('$title', '$author', '$category', '$year', '$status')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Book added successfully!'); window.location.href='books.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book - Cozy Library</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Patrick+Hand&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <h1>➕ Add a New Book</h1>
        <p>Keep your shelves growing</p>
    </header>

    <nav>
        <a href="index.html">🏠 Home</a>
        <a href="books.php">📖 View Books</a>
    </nav>

    <div class="container">
        <form method="POST" class="cozy-form">
            <label>Title:</label>
            <input type="text" name="title" required>

            <label>Author:</label>
            <input type="text" name="author" required>

            <label>Category:</label>
            <input type="text" name="category">

            <label>Year:</label>
            <input type="number" name="year">

            <label>Status:</label>
            <select name="status">
                <option value="Available">Available</option>
                <option value="Borrowed">Borrowed</option>
            </select>

            <button type="submit">Add Book</button>
        </form>
    </div>

    <footer>
        <p>Made with ❤️ & coffee</p>
    </footer>
</body>
</html>
