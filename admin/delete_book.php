<?php
session_start();
include "../database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if ($_SESSION['role'] != "admin") { header("Location: ../dashboard.php"); exit(); }

if (!isset($_GET['book_id']) || !is_numeric($_GET['book_id'])) {
    header("Location: view_books.php?error=Invalid+book.");
    exit();
}

$book_id = (int) $_GET['book_id'];

// Get image filename before deleting
$stmt = $connection->prepare("SELECT image FROM books WHERE id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
    header("Location: view_books.php?error=Book+not+found.");
    exit();
}

// Delete the book from database
$stmt2 = $connection->prepare("DELETE FROM books WHERE id = ?");
$stmt2->bind_param("i", $book_id);

if ($stmt2->execute()) {
    // Delete image file if it exists
    $image_path = $_SERVER['DOCUMENT_ROOT'] . "/library_MS/image/" . $book['image'];
    if (!empty($book['image']) && file_exists($image_path)) {
        unlink($image_path);
    }
    header("Location: view_books.php?success=Book+deleted+successfully.");
} else {
    header("Location: view_books.php?error=Failed+to+delete+book.");
}
$stmt2->close();
exit();
?>