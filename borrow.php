<?php
session_start();
include "database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] != "user") {
    header("Location: admin/dashboard.php");
    exit();
}

if (!isset($_GET['book_id']) || !is_numeric($_GET['book_id'])) {
    header("Location: index.php?error=No+book+selected.");
    exit();
}

$book_id = (int) $_GET['book_id'];
$user_id = (int) $_SESSION['user_id'];

// Check if book exists and has available quantity
$stmt = $connection->prepare("SELECT * FROM books WHERE id = ? AND quantity > 0");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book_result = $stmt->get_result();
$stmt->close();

if ($book_result->num_rows === 0) {
    header("Location: index.php?error=Book+is+unavailable+or+out+of+stock.");
    exit();
}

// Check if user already has this book borrowed
$stmt2 = $connection->prepare("SELECT id FROM transactions WHERE user_id = ? AND book_id = ? AND status = 'borrowed'");
$stmt2->bind_param("ii", $user_id, $book_id);
$stmt2->execute();
$already = $stmt2->get_result();
$stmt2->close();

if ($already->num_rows > 0) {
    header("Location: index.php?error=You+already+have+this+book+borrowed.");
    exit();
}

// Check if transactions table has due_date column
$cols = $connection->query("SHOW COLUMNS FROM transactions LIKE 'due_date'");
if ($cols->num_rows > 0) {
    // Has due_date column
    $stmt3 = $connection->prepare("INSERT INTO transactions (user_id, book_id, issue_date, due_date, status) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'borrowed')");
} else {
    // No due_date column
    $stmt3 = $connection->prepare("INSERT INTO transactions (user_id, book_id, issue_date, status) VALUES (?, ?, CURDATE(), 'borrowed')");
}

$stmt3->bind_param("ii", $user_id, $book_id);
$result = $stmt3->execute();
$stmt3->close();

if ($result) {
    $stmt4 = $connection->prepare("UPDATE books SET quantity = quantity - 1 WHERE id = ?");
    $stmt4->bind_param("i", $book_id);
    $stmt4->execute();
    $stmt4->close();
    header("Location: index.php?success=Book+borrowed+successfully!+Due+in+14+days.");
} else {
    header("Location: index.php?error=Failed+to+borrow.+Error:+" . urlencode($connection->error));
}
exit();
?>