<?php
session_start();
include "../database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if ($_SESSION['role'] != "admin") { header("Location: ../dashboard.php"); exit(); }

$admin_name = $_SESSION['user_name'];

// Summary stats
$total_books     = $connection->query("SELECT COUNT(*) as c FROM books")->fetch_assoc()['c'];
$total_users     = $connection->query("SELECT COUNT(*) as c FROM users WHERE role='user'")->fetch_assoc()['c'];
$total_borrowed  = $connection->query("SELECT COUNT(*) as c FROM transactions WHERE status='borrowed'")->fetch_assoc()['c'];
$total_returned  = $connection->query("SELECT COUNT(*) as c FROM transactions WHERE status='returned'")->fetch_assoc()['c'];
$total_overdue   = $connection->query("SELECT COUNT(*) as c FROM transactions WHERE status='borrowed' AND due_date < CURDATE()")->fetch_assoc()['c'];
$total_trans     = $connection->query("SELECT COUNT(*) as c FROM transactions")->fetch_assoc()['c'];

// Most borrowed books
$top_books = $connection->query("
    SELECT b.title, b.author, COUNT(t.id) as borrow_count
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    GROUP BY t.book_id
    ORDER BY borrow_count DESC
    LIMIT 10
");

// Most active users
$top_users = $connection->query("
    SELECT u.name, u.email, COUNT(t.id) as borrow_count
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    GROUP BY t.user_id
    ORDER BY borrow_count DESC
    LIMIT 10
");

// Monthly borrow activity (last 6 months)
$monthly = $connection->query("
    SELECT DATE_FORMAT(issue_date, '%b %Y') as month,
           DATE_FORMAT(issue_date, '%Y-%m') as month_key,
           COUNT(*) as total
    FROM transactions
    WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key
    ORDER BY month_key ASC
");
$monthly_labels = [];
$monthly_data   = [];
while ($row = $monthly->fetch_assoc()) {
    $monthly_labels[] = $row['month'];
    $monthly_data[]   = $row['total'];
}

// Books with low stock
$low_stock = $connection->query("SELECT * FROM books WHERE quantity <= 2 ORDER BY quantity ASC");

// All transactions for full report
$all_trans = $connection->query("
    SELECT t.*, u.name as user_name, b.title as book_title
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    JOIN books b ON t.book_id = b.id
    ORDER BY t.issue_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <title>Reports — Library</title>
    <style>
        .report-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .chart-card  { background: white; border-radius: var(--radius); box-shadow: var(--shadow-sm); padding: 24px; }
        .chart-card h3 { font-family: 'Playfair Display', serif; font-size: 16px; color: var(--primary-dark); margin-bottom: 16px; }
        .print-btn { position: fixed; bottom: 32px; right: 32px; z-index: 200; }
        @media print {
            .sidebar, .print-btn, .page-header p { display: none !important; }
            .main-content { margin-left: 0 !important; }
            .report-grid  { grid-template-columns: 1fr !important; }
        }
        @media (max-width: 900px) { .report-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="../logo.svg" alt="Logo" style="width:42px;height:42px;border-radius:50%;margin-bottom:6px;display:block;">
            <h2>LMS</h2><span>Admin Panel</span>
        </div>
        <div class="sidebar-user">
            <div class="user-avatar"><?php echo strtoupper(substr($admin_name,0,1)); ?></div>
            <div class="user-info"><small>Administrator</small><strong><?php echo htmlspecialchars($admin_name); ?></strong></div>
        </div>
        <nav class="sidebar-nav">
            <span class="nav-label">Main</span>
            <a href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
            <span class="nav-label">Books</span>
            <a href="view_books.php"><span class="icon">📖</span> View Books</a>
            <a href="add_book.php"><span class="icon">➕</span> Add Book</a>
            <span class="nav-label">Users</span>
            <a href="manage_users.php"><span class="icon">👥</span> Manage Users</a>
            <span class="nav-label">Transactions</span>
            <a href="view_transactions.php"><span class="icon">📋</span> Transactions</a>
            <a href="reports.php" class="active"><span class="icon">📊</span> Reports</a>
        </nav>
        <div class="sidebar-footer"><a href="../logout.php">🚪 Logout</a></div>
    </aside>
    <main class="main-content">
        <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <h1>Library Reports</h1>
                <p>Generated on <?php echo date('F d, Y \a\t h:i A'); ?></p>
            </div>
            <button onclick="window.print()" class="btn btn-primary btn-sm">🖨️ Print Report</button>
        </div>

        <!-- Summary Stats -->
        <div class="stats-grid" style="margin-bottom:24px;">
            <div class="stat-card"><div class="stat-icon">📚</div><div class="stat-info"><h3><?php echo $total_books; ?></h3><p>Total Books</p></div></div>
            <div class="stat-card accent"><div class="stat-icon">👥</div><div class="stat-info"><h3><?php echo $total_users; ?></h3><p>Members</p></div></div>
            <div class="stat-card warning"><div class="stat-icon">🔖</div><div class="stat-info"><h3><?php echo $total_borrowed; ?></h3><p>Currently Borrowed</p></div></div>
            <div class="stat-card success"><div class="stat-icon">✅</div><div class="stat-info"><h3><?php echo $total_returned; ?></h3><p>Returned</p></div></div>
            <div class="stat-card danger"><div class="stat-icon">⚠️</div><div class="stat-info"><h3><?php echo $total_overdue; ?></h3><p>Overdue</p></div></div>
        </div>

        <!-- Charts -->
        <div class="report-grid">
            <!-- Monthly Activity -->
            <div class="chart-card">
                <h3>📈 Monthly Borrow Activity</h3>
                <canvas id="monthlyChart" height="200"></canvas>
            </div>
            <!-- Status Breakdown -->
            <div class="chart-card">
                <h3>🍩 Transaction Status</h3>
                <canvas id="statusChart" height="200"></canvas>
            </div>
        </div>

        <!-- Top Borrowed Books -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header"><h2>🏆 Most Borrowed Books</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>#</th><th>Title</th><th>Author</th><th>Times Borrowed</th><th>Popularity</th></tr></thead>
                    <tbody>
                        <?php
                        $top_books->data_seek(0);
                        $max = 0; $rows = [];
                        while ($r = $top_books->fetch_assoc()) { $rows[] = $r; if ($r['borrow_count'] > $max) $max = $r['borrow_count']; }
                        foreach ($rows as $i => $r):
                            $pct = $max > 0 ? round(($r['borrow_count']/$max)*100) : 0;
                        ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><strong><?php echo htmlspecialchars($r['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['author']); ?></td>
                            <td><span class="badge badge-borrowed"><?php echo $r['borrow_count']; ?> times</span></td>
                            <td style="width:180px;">
                                <div style="background:var(--gray-200);border-radius:4px;height:8px;">
                                    <div style="background:var(--primary);height:8px;border-radius:4px;width:<?php echo $pct; ?>%;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Most Active Users -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header"><h2>👑 Most Active Members</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Total Borrows</th></tr></thead>
                    <tbody>
                        <?php $i=1; while ($r = $top_users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($r['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['email']); ?></td>
                            <td><span class="badge badge-returned"><?php echo $r['borrow_count']; ?> books</span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <?php if ($low_stock->num_rows > 0): ?>
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header"><h2>⚠️ Low Stock Alert</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Title</th><th>Author</th><th>ISBN</th><th>Quantity Left</th></tr></thead>
                    <tbody>
                        <?php while ($r = $low_stock->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($r['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['author']); ?></td>
                            <td><code><?php echo htmlspecialchars($r['isbn']); ?></code></td>
                            <td><span class="badge <?php echo $r['quantity']==0?'badge-overdue':'badge-borrowed'; ?>"><?php echo $r['quantity']==0?'Out of Stock':$r['quantity'].' left'; ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Full Transaction Log -->
        <div class="card">
            <div class="card-header"><h2>📋 Full Transaction Log</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Member</th><th>Book</th><th>Issue Date</th><th>Due Date</th><th>Return Date</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php while ($r = $all_trans->fetch_assoc()):
                            $overdue = ($r['status']=='borrowed' && !empty($r['due_date']) && strtotime($r['due_date'])<time());
                            $badge   = $overdue ? 'overdue' : $r['status'];
                            $label   = $overdue ? 'Overdue' : ucfirst($r['status']);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['book_title']); ?></td>
                            <td><?php echo $r['issue_date']; ?></td>
                            <td><?php echo $r['due_date'] ?? '—'; ?></td>
                            <td><?php echo $r['return_date'] ?? '—'; ?></td>
                            <td><span class="badge badge-<?php echo $badge; ?>"><?php echo $label; ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script>
// Monthly Activity Chart
const mCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(mCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($monthly_labels); ?>,
        datasets: [{
            label: 'Books Borrowed',
            data: <?php echo json_encode($monthly_data); ?>,
            backgroundColor: 'rgba(26,71,42,0.8)',
            borderColor: '#1a472a',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// Status Doughnut Chart
const sCtx = document.getElementById('statusChart').getContext('2d');
new Chart(sCtx, {
    type: 'doughnut',
    data: {
        labels: ['Borrowed', 'Returned', 'Overdue'],
        datasets: [{
            data: [<?php echo $total_borrowed - $total_overdue; ?>, <?php echo $total_returned; ?>, <?php echo $total_overdue; ?>],
            backgroundColor: ['#f39c12', '#27ae60', '#c0392b'],
            borderWidth: 0,
        }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
</body>
</html>
