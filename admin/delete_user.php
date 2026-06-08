<?php
session_start();
include "../database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if ($_SESSION['role'] != "admin") { header("Location: ../dashboard.php"); exit(); }

if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    header("Location: manage_users.php?error=Invalid+user.");
    exit();
}

$user_id   = (int) $_GET['user_id'];
$admin_id  = (int) $_SESSION['user_id'];

// Prevent admin from deleting themselves
if ($user_id === $admin_id) {
    header("Location: manage_users.php?error=You+cannot+delete+your+own+account.");
    exit();
}

// Check user exists
$stmt = $connection->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'user'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: manage_users.php?error=User+not+found.");
    exit();
}

// Restore book quantities for any active borrowed transactions
$stmt2 = $connection->prepare("SELECT book_id FROM transactions WHERE user_id = ? AND status = 'borrowed'");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$active = $stmt2->get_result();
$stmt2->close();

while ($row = $active->fetch_assoc()) {
    $stmt3 = $connection->prepare("UPDATE books SET quantity = quantity + 1 WHERE id = ?");
    $stmt3->bind_param("i", $row['book_id']);
    $stmt3->execute();
    $stmt3->close();
}

// Delete user's transactions first (foreign key)
$stmt4 = $connection->prepare("DELETE FROM transactions WHERE user_id = ?");
$stmt4->bind_param("i", $user_id);
$stmt4->execute();
$stmt4->close();

// Delete the user
$stmt5 = $connection->prepare("DELETE FROM users WHERE id = ?");
$stmt5->bind_param("i", $user_id);

if ($stmt5->execute()) {
    header("Location: manage_users.php?success=User+deleted+successfully.");
} else {
    header("Location: manage_users.php?error=Failed+to+delete+user.");
}
$stmt5->close();
exit();
?>
