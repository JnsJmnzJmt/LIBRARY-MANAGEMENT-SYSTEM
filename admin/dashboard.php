<?php
session_start();
include "../database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if ($_SESSION['role'] != "admin") { header("Location: ../dashboard.php"); exit(); }

$admin_name = $_SESSION['user_name'];

$total_books   = $connection->query("SELECT COUNT(*) as c FROM books")->fetch_assoc()['c'];
$total_users   = $connection->query("SELECT COUNT(*) as c FROM users WHERE role='user'")->fetch_assoc()['c'];
$total_borrowed= $connection->query("SELECT COUNT(*) as c FROM transactions WHERE status='borrowed'")->fetch_assoc()['c'];
$total_overdue = $connection->query("SELECT COUNT(*) as c FROM transactions WHERE status='borrowed' AND due_date < CURDATE()")->fetch_assoc()['c'];
$total_returned= $connection->query("SELECT COUNT(*) as c FROM transactions WHERE status='returned'")->fetch_assoc()['c'];

// Monthly data (last 6 months)
$monthly = $connection->query("
    SELECT DATE_FORMAT(issue_date,'%b') as month,
           DATE_FORMAT(issue_date,'%Y-%m') as key_month,
           COUNT(*) as total
    FROM transactions
    WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY key_month ORDER BY key_month ASC
");
$m_labels = []; $m_data = [];
while ($r = $monthly->fetch_assoc()) { $m_labels[] = $r['month']; $m_data[] = $r['total']; }

// Top 5 books
$top_books = $connection->query("
    SELECT b.title, COUNT(t.id) as cnt
    FROM transactions t JOIN books b ON t.book_id=b.id
    GROUP BY t.book_id ORDER BY cnt DESC LIMIT 5
");
$b_labels = []; $b_data = [];
while ($r = $top_books->fetch_assoc()) { $b_labels[] = $r['title']; $b_data[] = $r['cnt']; }

// Recent transactions
$recent = $connection->query("
    SELECT t.*, u.name as user_name, b.title as book_title
    FROM transactions t
    JOIN users u ON t.user_id=u.id
    JOIN books b ON t.book_id=b.id
    ORDER BY t.issue_date DESC LIMIT 8
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <title>Admin Dashboard — Library</title>
    <style>
        .charts-grid { display:grid; grid-template-columns:2fr 1fr; gap:24px; margin-bottom:24px; }
        .chart-box   { background:white; border-radius:var(--radius); box-shadow:var(--shadow-sm); padding:24px; }
        .chart-box h3{ font-family:'Playfair Display',serif; font-size:16px; color:var(--primary-dark); margin-bottom:16px; }
        @media(max-width:900px){ .charts-grid{ grid-template-columns:1fr; } }
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
            <a href="dashboard.php" class="active"><span class="icon">🏠</span> Dashboard</a>
            <span class="nav-label">Books</span>
            <a href="view_books.php"><span class="icon">📖</span> View Books</a>
            <a href="add_book.php"><span class="icon">➕</span> Add Book</a>
            <span class="nav-label">Users</span>
            <a href="manage_users.php"><span class="icon">👥</span> Manage Users</a>
            <span class="nav-label">Transactions</span>
            <a href="view_transactions.php"><span class="icon">📋</span> Transactions</a>
            <a href="reports.php"><span class="icon">📊</span> Reports</a>
        </nav>
        <div class="sidebar-footer"><a href="../logout.php" onclick="return confirmLogout()">🚪 Logout</a></div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Admin Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($admin_name); ?>. Here's your library overview.</p>
        </div>

        <!-- Stat Cards -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon">📚</div><div class="stat-info"><h3><?php echo $total_books; ?></h3><p>Total Books</p></div></div>
            <div class="stat-card accent"><div class="stat-icon">👥</div><div class="stat-info"><h3><?php echo $total_users; ?></h3><p>Members</p></div></div>
            <div class="stat-card warning"><div class="stat-icon">🔖</div><div class="stat-info"><h3><?php echo $total_borrowed; ?></h3><p>Active Borrows</p></div></div>
            <div class="stat-card success"><div class="stat-icon">✅</div><div class="stat-info"><h3><?php echo $total_returned; ?></h3><p>Returned</p></div></div>
            <div class="stat-card danger"><div class="stat-icon">⚠️</div><div class="stat-info"><h3><?php echo $total_overdue; ?></h3><p>Overdue</p></div></div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-box">
                <h3>📈 Monthly Borrow Activity</h3>
                <canvas id="monthlyChart" height="120"></canvas>
            </div>
            <div class="chart-box">
                <h3>🍩 Status Breakdown</h3>
                <canvas id="statusChart" height="120"></canvas>
            </div>
        </div>

        <!-- Top Books Chart -->
        <div class="chart-box" style="margin-bottom:24px;">
            <h3>🏆 Top 5 Most Borrowed Books</h3>
            <canvas id="booksChart" height="80"></canvas>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Transactions</h2>
                <a href="view_transactions.php" class="btn btn-secondary btn-sm">View All</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Member</th><th>Book</th><th>Issue Date</th><th>Due Date</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if ($recent->num_rows === 0): ?>
                        <tr><td colspan="5"><div class="empty-state"><div class="icon">📭</div><p>No transactions yet.</p></div></td></tr>
                        <?php else: while ($row = $recent->fetch_assoc()):
                            $overdue = ($row['status']=='borrowed' && !empty($row['due_date']) && strtotime($row['due_date'])<time());
                            $badge   = $overdue ? 'overdue' : $row['status'];
                            $label   = $overdue ? 'Overdue' : ucfirst($row['status']);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['book_title']); ?></td>
                            <td><?php echo $row['issue_date']; ?></td>
                            <td><?php echo $row['due_date'] ?? '—'; ?></td>
                            <td><span class="badge badge-<?php echo $badge; ?>"><?php echo $label; ?></span></td>
                        </tr>
                        <?php endwhile; endif; ?>
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
function confirmLogout(){document.getElementById('logout-modal').classList.add('active');return false;}
function closeModal(){document.getElementById('logout-modal').classList.remove('active');}

// Monthly Bar Chart
new Chart(document.getElementById('monthlyChart').getContext('2d'),{
    type:'bar',
    data:{
        labels:<?php echo json_encode($m_labels); ?>,
        datasets:[{label:'Borrows',data:<?php echo json_encode($m_data); ?>,backgroundColor:'rgba(26,71,42,0.8)',borderColor:'#1a472a',borderWidth:2,borderRadius:6}]
    },
    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}
});

// Doughnut Chart
new Chart(document.getElementById('statusChart').getContext('2d'),{
    type:'doughnut',
    data:{
        labels:['Active','Returned','Overdue'],
        datasets:[{data:[<?php echo $total_borrowed-$total_overdue; ?>,<?php echo $total_returned; ?>,<?php echo $total_overdue; ?>],backgroundColor:['#f39c12','#27ae60','#c0392b'],borderWidth:0}]
    },
    options:{responsive:true,cutout:'65%',plugins:{legend:{position:'bottom'}}}
});

// Top Books Horizontal Bar
new Chart(document.getElementById('booksChart').getContext('2d'),{
    type:'bar',
    data:{
        labels:<?php echo json_encode($b_labels); ?>,
        datasets:[{label:'Times Borrowed',data:<?php echo json_encode($b_data); ?>,backgroundColor:['#1a472a','#2d6a4f','#d4a017','#f39c12','#27ae60'],borderRadius:6}]
    },
    options:{
        indexAxis:'y',
        responsive:true,
        plugins:{legend:{display:false}},
        scales:{x:{beginAtZero:true,ticks:{stepSize:1}}}
    }
});
</script>
</body>
</html>
