<?php
session_start();
include "../database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if ($_SESSION['role'] != "admin") { header("Location: ../dashboard.php"); exit(); }

$admin_name = $_SESSION['user_name'];
$filter  = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$allowed = ['all','borrowed','returned','overdue'];
if (!in_array($filter,$allowed)) $filter='all';

if ($filter==='overdue') {
    $result = $connection->query("SELECT t.*,u.name as user_name,b.title as book_title FROM transactions t JOIN users u ON t.user_id=u.id JOIN books b ON t.book_id=b.id WHERE t.status='borrowed' AND t.due_date<CURDATE() ORDER BY t.due_date ASC");
} elseif ($filter==='all') {
    $result = $connection->query("SELECT t.*,u.name as user_name,b.title as book_title FROM transactions t JOIN users u ON t.user_id=u.id JOIN books b ON t.book_id=b.id ORDER BY t.issue_date DESC");
} else {
    $stmt = $connection->prepare("SELECT t.*,u.name as user_name,b.title as book_title FROM transactions t JOIN users u ON t.user_id=u.id JOIN books b ON t.book_id=b.id WHERE t.status=? ORDER BY t.issue_date DESC");
    $stmt->bind_param("s",$filter);
    $stmt->execute();
    $result=$stmt->get_result();
    $stmt->close();
}

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error   = isset($_GET['error'])   ? $_GET['error']   : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../style.css">
    <title>Transactions — Library</title>
</head>
<body>
<div id="toast-container"></div>
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
            <a href="view_transactions.php" class="active"><span class="icon">📋</span> Transactions</a>
            <a href="reports.php"><span class="icon">📊</span> Reports</a>
        </nav>
        <div class="sidebar-footer"><a href="../logout.php">🚪 Logout</a></div>
    </aside>
    <main class="main-content">
        <div class="page-header">
            <h1>Transactions</h1>
            <p>Manage all borrow and return records.</p>
        </div>

        <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
            <a href="?filter=all"      class="btn <?php echo $filter=='all'     ?'btn-primary':'btn-secondary'; ?> btn-sm">All</a>
            <a href="?filter=borrowed" class="btn <?php echo $filter=='borrowed'?'btn-primary':'btn-secondary'; ?> btn-sm">Borrowed</a>
            <a href="?filter=returned" class="btn <?php echo $filter=='returned'?'btn-primary':'btn-secondary'; ?> btn-sm">Returned</a>
            <a href="?filter=overdue"  class="btn <?php echo $filter=='overdue' ?'btn-danger' :'btn-secondary'; ?> btn-sm">⚠️ Overdue</a>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Records (<?php echo $result->num_rows; ?>)</h2>
                <div class="search-bar">
                    <span>🔍</span>
                    <input type="text" id="search-input" placeholder="Search member or book...">
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Member</th><th>Book</th><th>Issue Date</th><th>Due Date</th><th>Return Date</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows===0): ?>
                        <tr><td colspan="7"><div class="empty-state"><div class="icon">📭</div><p>No transactions found.</p></div></td></tr>
                        <?php else: while ($row=$result->fetch_assoc()):
                            $overdue=($row['status']=='borrowed'&&!empty($row['due_date'])&&strtotime($row['due_date'])<time());
                            $badge=$overdue?'overdue':$row['status'];
                            $label=$overdue?'Overdue':ucfirst($row['status']);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['book_title']); ?></td>
                            <td><?php echo $row['issue_date']; ?></td>
                            <td>
                                <?php echo $row['due_date']??'—'; ?>
                                <?php if($overdue): ?><span style="color:var(--danger);font-size:11px;"> ⚠️</span><?php endif; ?>
                            </td>
                            <td><?php echo $row['return_date']??'—'; ?></td>
                            <td><span class="badge badge-<?php echo $badge; ?>"><?php echo $label; ?></span></td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <?php if ($row['status']==='borrowed'): ?>
                                    <a href="set_deadline.php?transaction_id=<?php echo $row['id']; ?>" class="btn btn-sm" style="background:#6f42c1;color:white;">📅 Deadline</a>
                                    <?php endif; ?>
                                    <a href="update_transactions.php?transaction_id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">✏️ Update</a>
                                    <a href="delete_transactions.php?transaction_id=<?php echo $row['id']; ?>"
                                       class="btn btn-danger btn-sm delete-btn"
                                       data-title="<?php echo htmlspecialchars($row['book_title']); ?>">
                                       🗑️
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<div class="modal-overlay" id="delete-modal">
    <div class="modal">
        <h3>Delete Transaction</h3>
        <p id="delete-msg">Are you sure?</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="cancelDelete()">Cancel</button>
            <a href="#" id="delete-confirm-btn" class="btn btn-danger">Yes, Delete</a>
        </div>
    </div>
</div>

<script>
function showToast(msg,type='success'){const c=document.getElementById('toast-container');const t=document.createElement('div');t.className=`toast ${type}`;t.textContent=msg;c.appendChild(t);setTimeout(()=>t.remove(),3600);}
<?php if($success): ?>showToast(<?php echo json_encode($success); ?>,'success');<?php endif; ?>
<?php if($error):   ?>showToast(<?php echo json_encode($error);   ?>,'error');  <?php endif; ?>
function cancelDelete(){document.getElementById('delete-modal').classList.remove('active');}
document.querySelectorAll('.delete-btn').forEach(btn=>{
    btn.addEventListener('click',function(e){
        e.preventDefault();
        document.getElementById('delete-msg').textContent=`Delete transaction for "${this.dataset.title}"? This cannot be undone.`;
        document.getElementById('delete-confirm-btn').href=this.href;
        document.getElementById('delete-modal').classList.add('active');
    });
});
document.getElementById('search-input').addEventListener('input',function(){
    const q=this.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(row=>{row.style.display=row.textContent.toLowerCase().includes(q)?'':'none';});
});
</script>
</body>
</html>
