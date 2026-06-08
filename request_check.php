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

// Filter by status
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$allowed = ['all', 'borrowed', 'returned'];
if (!in_array($filter, $allowed)) $filter = 'all';

// JOIN to get book title and author instead of raw IDs
if ($filter === 'all') {
    $stmt = $connection->prepare("
        SELECT t.*, b.title, b.author, b.image
        FROM transactions t
        JOIN books b ON t.book_id = b.id
        WHERE t.user_id = ?
        ORDER BY t.issue_date DESC
    ");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $connection->prepare("
        SELECT t.*, b.title, b.author, b.image
        FROM transactions t
        JOIN books b ON t.book_id = b.id
        WHERE t.user_id = ? AND t.status = ?
        ORDER BY t.issue_date DESC
    ");
    $stmt->bind_param("is", $user_id, $filter);
}
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="style.css">
    <title>My Transactions — Library</title>
</head>
<body>

<div id="toast-container"></div>

<div class="layout">

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
            <a href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
            <a href="index.php"><span class="icon">📖</span> Browse Books</a>
            <a href="request_check.php" class="active"><span class="icon">📋</span> My Transactions</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" onclick="return confirmLogout()">🚪 Logout</a>
        </div>
    </aside>

    <main class="main-content">

        <div class="page-header">
            <h1>My Transactions</h1>
            <p>Track your borrowed and returned books.</p>
        </div>

        <!-- FILTER TABS -->
        <div style="display:flex;gap:8px;margin-bottom:20px;">
            <a href="?filter=all"      class="btn <?php echo $filter=='all'      ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">All</a>
            <a href="?filter=borrowed" class="btn <?php echo $filter=='borrowed' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">Borrowed</a>
            <a href="?filter=returned" class="btn <?php echo $filter=='returned' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">Returned</a>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Author</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="icon">📭</div>
                                    <p>No transactions found.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                                $is_overdue = ($row['status'] == 'borrowed' && !empty($row['due_date']) && strtotime($row['due_date']) < time());
                                $badge = $is_overdue ? 'overdue' : $row['status'];
                                $label = $is_overdue ? 'Overdue' : ucfirst($row['status']);
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['author']); ?></td>
                                <td><?php echo $row['issue_date']; ?></td>
                                <td>
                                    <?php if (!empty($row['due_date'])): ?>
                                        <?php echo $row['due_date']; ?>
                                        <?php if ($is_overdue): ?>
                                            <span style="color:var(--danger);font-size:11px;"> ⚠️ Overdue</span>
                                        <?php endif; ?>
                                    <?php else: ?> — <?php endif; ?>
                                </td>
                                <td><?php echo $row['return_date'] ?? '—'; ?></td>
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
