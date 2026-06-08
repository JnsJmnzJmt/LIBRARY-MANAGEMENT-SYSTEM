<?php
session_start();
include "../database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if ($_SESSION['role'] != "admin") { header("Location: ../dashboard.php"); exit(); }

if (!isset($_GET['transaction_id']) || !is_numeric($_GET['transaction_id'])) {
    header("Location: view_transactions.php?error=Invalid+transaction.");
    exit();
}

$transaction_id = (int) $_GET['transaction_id'];

// Get the transaction first to check if we need to restore book quantity
$stmt = $connection->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$trans = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trans) {
    header("Location: view_transactions.php?error=Transaction+not+found.");
    exit();
}

// Delete the transaction
$stmt2 = $connection->prepare("DELETE FROM transactions WHERE id = ?");
$stmt2->bind_param("i", $transaction_id);

if ($stmt2->execute()) {
    // If book was still 'borrowed', restore the quantity
    if ($trans['status'] === 'borrowed') {
        $stmt3 = $connection->prepare("UPDATE books SET quantity = quantity + 1 WHERE id = ?");
        $stmt3->bind_param("i", $trans['book_id']);
        $stmt3->execute();
        $stmt3->close();
    }
    header("Location: view_transactions.php?success=Transaction+deleted+and+book+quantity+restored.");
} else {
    header("Location: view_transactions.php?error=Failed+to+delete+transaction.");
}
$stmt2->close();
exit();
?>
