<?php
session_start();
include "database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['role'] == "admin") {
    header("Location: admin/dashboard.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Count user's active borrowed books
$stmt = $connection->prepare("SELECT COUNT(*) as total FROM transactions WHERE user_id = ? AND status = 'borrowed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$borrowed_count = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Count returned books
$stmt2 = $connection->prepare("SELECT COUNT(*) as total FROM transactions WHERE user_id = ? AND status = 'returned'");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$returned_count = $stmt2->get_result()->fetch_assoc()['total'];
$stmt2->close();

// Count overdue (borrowed and past due_date)
$stmt3 = $connection->prepare("SELECT COUNT(*) as total FROM transactions WHERE user_id = ? AND status = 'borrowed' AND due_date < CURDATE()");
$stmt3->bind_param("i", $user_id);
$stmt3->execute();
$overdue_count = $stmt3->get_result()->fetch_assoc()['total'];
$stmt3->close();

// Recent transactions with book titles
$stmt4 = $connection->prepare("
    SELECT t.*, b.title, b.author
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    WHERE t.user_id = ?
    ORDER BY t.issue_date DESC
    LIMIT 5
");
$stmt4->bind_param("i", $user_id);
$stmt4->execute();
$recent = $stmt4->get_result();
$stmt4->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="style.css">
    <title>My Dashboard — Library</title>
</head>
<body>

<div id="toast-container"></div>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <h2>📚 LMS</h2>
            <span>Library Management</span>
        </div>
        <div class="sidebar-user">
            <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
            <div class="user-info">
                <small>Member</small>
                <strong><?php echo htmlspecialchars($user_name); ?></strong>
            </div>
        </div>
        <nav class="sidebar-nav">
            <span class="nav-label">Menu</span>
            <a href="dashboard.php" class="active"><span class="icon">🏠</span> Dashboard</a>
            <a href="index.php"><span class="icon">📖</span> Browse Books</a>
            <a href="request_check.php"><span class="icon">📋</span> My Transactions</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" onclick="return confirmLogout()">🚪 Logout</a>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main-content">

        <div class="page-header">
            <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
            <p>Here's an overview of your library activity.</p>
        </div>

        <!-- STAT CARDS -->
        <div class="stats-grid">
            <div class="stat-card warning">
                <div class="stat-icon">📚</div>
                <div class="stat-info">
                    <h3><?php echo $borrowed_count; ?></h3>
                    <p>Currently Borrowed</p>
                </div>
            </div>
            <div class="stat-card success">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <h3><?php echo $returned_count; ?></h3>
                    <p>Books Returned</p>
                </div>
            </div>
            <div class="stat-card danger">
                <div class="stat-icon">⚠️</div>
                <div class="stat-info">
                    <h3><?php echo $overdue_count; ?></h3>
                    <p>Overdue Books</p>
                </div>
            </div>
        </div>

        <!-- RECENT TRANSACTIONS -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Transactions</h2>
                <a href="request_check.php" class="btn btn-secondary btn-sm">View All</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent->num_rows === 0): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <div class="icon">📭</div>
                                    <p>No transactions yet. <a href="index.php" style="color:var(--primary)">Browse books</a> to get started!</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php while ($row = $recent->fetch_assoc()): ?>
                            <?php
                                $is_overdue = ($row['status'] == 'borrowed' && !empty($row['due_date']) && strtotime($row['due_date']) < time());
                                $badge = $is_overdue ? 'overdue' : $row['status'];
                                $label = $is_overdue ? 'Overdue' : ucfirst($row['status']);
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['author']); ?></td>
                                <td><?php echo $row['issue_date']; ?></td>
                                <td><?php echo $row['due_date'] ?? '—'; ?></td>
                                <td><span class="badge badge-<?php echo $badge; ?>"><?php echo $label; ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<!-- Logout confirm modal -->
<div class="modal-overlay" id="logout-modal">
    <div class="modal">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to log out of your account?</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <a href="logout.php" class="btn btn-danger">Yes, Logout</a>
        </div>
    </div>
</div>

<script>
function confirmLogout() {
    document.getElementById('logout-modal').classList.add('active');
    return false;
}
function closeModal() {
    document.getElementById('logout-modal').classList.remove('active');
}
</script>

</body>
</html>

