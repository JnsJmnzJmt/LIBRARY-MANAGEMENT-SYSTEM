<?php
session_start();
include "../database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
if ($_SESSION['role'] != "admin") {
    header("Location: ../dashboard.php");
    exit();
}

$admin_name = $_SESSION['user_name'];

// Stats
$total_books    = $connection->query("SELECT COUNT(*) as c FROM books")->fetch_assoc()['c'];
$total_users    = $connection->query("SELECT COUNT(*) as c FROM users WHERE role='user'")->fetch_assoc()['c'];
$total_borrowed = $connection->query("SELECT COUNT(*) as c FROM transactions WHERE status='borrowed'")->fetch_assoc()['c'];
$total_overdue  = $connection->query("SELECT COUNT(*) as c FROM transactions WHERE status='borrowed' AND due_date < CURDATE()")->fetch_assoc()['c'];

// Recent transactions with names
$recent = $connection->query("
    SELECT t.*, u.name as user_name, b.title as book_title
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    JOIN books b ON t.book_id = b.id
    ORDER BY t.issue_date DESC
    LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../style.css">
    <title>Admin Dashboard — Library</title>
</head>
<body>

<div class="layout">

    <aside class="sidebar">
        <div class="sidebar-logo">
            <h2>📚 LMS</h2>
            <span>Admin Panel</span>
        </div>
        <div class="sidebar-user">
            <div class="user-avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
            <div class="user-info">
                <small>Administrator</small>
                <strong><?php echo htmlspecialchars($admin_name); ?></strong>
            </div>
        </div>
        <nav class="sidebar-nav">
            <span class="nav-label">Main</span>
            <a href="dashboard.php" class="active"><span class="icon">🏠</span> Dashboard</a>
            <span class="nav-label">Books</span>
            <a href="view_books.php"><span class="icon">📖</span> View Books</a>
            <a href="add_book.php"><span class="icon">➕</span> Add Book</a>
            <span class="nav-label">Users</span>
            <a href="manage_users.php"><span class="icon">👥</span> Manage Users</a>
            <span class="nav-label">Transactions</span>
            <a href="view_transactions.php"><span class="icon">📋</span> Transactions</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../logout.php" onclick="return confirmLogout()">🚪 Logout</a>
        </div>
    </aside>

    <main class="main-content">

        <div class="page-header">
            <h1>Admin Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($admin_name); ?>. Here's your library overview.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-info">
                    <h3><?php echo $total_books; ?></h3>
                    <p>Total Books</p>
                </div>
            </div>
            <div class="stat-card accent">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h3><?php echo $total_users; ?></h3>
                    <p>Registered Users</p>
                </div>
            </div>
            <div class="stat-card warning">
                <div class="stat-icon">🔖</div>
                <div class="stat-info">
                    <h3><?php echo $total_borrowed; ?></h3>
                    <p>Active Borrows</p>
                </div>
            </div>
            <div class="stat-card danger">
                <div class="stat-icon">⚠️</div>
                <div class="stat-info">
                    <h3><?php echo $total_overdue; ?></h3>
                    <p>Overdue</p>
                </div>
            </div>
        </div>

        <!-- RECENT TRANSACTIONS -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Transactions</h2>
                <a href="view_transactions.php" class="btn btn-secondary btn-sm">View All</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Book</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent->num_rows === 0): ?>
                        <tr><td colspan="5"><div class="empty-state"><div class="icon">📭</div><p>No transactions yet.</p></div></td></tr>
                        <?php else: ?>
                            <?php while ($row = $recent->fetch_assoc()): ?>
                            <?php
                                $is_overdue = ($row['status'] == 'borrowed' && !empty($row['due_date']) && strtotime($row['due_date']) < time());
                                $badge = $is_overdue ? 'overdue' : $row['status'];
                                $label = $is_overdue ? 'Overdue' : ucfirst($row['status']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['book_title']); ?></td>
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

<div class="modal-overlay" id="logout-modal">
    <div class="modal">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to log out?</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <a href="../logout.php" class="btn btn-danger">Yes, Logout</a>
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
