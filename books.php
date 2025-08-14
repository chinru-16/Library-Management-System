<?php
include 'db.php';

// Fetch books
$sql = "SELECT * FROM books ORDER BY title ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books - Cozy Library</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Patrick+Hand&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <h1>📖 Our Book Collection</h1>
        <p>A warm place for your favorite reads</p>
    </header>

    <nav>
        <a href="index.html">🏠 Home</a>
        <a href="add_book.php">➕ Add Book</a>
    </nav>

    <div class="container">
        <?php if ($result->num_rows > 0): ?>
        <table class="cozy-table">
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Author</th>
                <th>Category</th>
                <th>Year</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['author']) ?></td>
                <td><?= htmlspecialchars($row['category']) ?></td>
                <td><?= htmlspecialchars($row['year']) ?></td>
                <td><?= htmlspecialchars($row['status']) ?></td>
                <td>
                    <a href="edit_book.php?id=<?= $row['id'] ?>">✏️ Edit</a> | 
                    <a href="delete_book.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete this book?')">🗑️ Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
        <?php else: ?>
            <p>No books yet. Add some!</p>
        <?php endif; ?>
    </div>

    <footer>
        <p>Made with ❤️ & coffee</p>
    </footer>
</body>
</html>
