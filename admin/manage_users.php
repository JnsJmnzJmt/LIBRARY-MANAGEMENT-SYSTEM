<?php
session_start();
include "../database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if ($_SESSION['role'] != "admin") { header("Location: ../dashboard.php"); exit(); }

$admin_name = $_SESSION['user_name'];

$result = $connection->query("
    SELECT u.*,
           COUNT(CASE WHEN t.status='borrowed' THEN 1 END) as active_borrows
    FROM users u
    LEFT JOIN transactions t ON u.id=t.user_id
    WHERE u.role='user'
    GROUP BY u.id
    ORDER BY u.name ASC
");

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error   = isset($_GET['error'])   ? $_GET['error']   : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../style.css">
    <title>Manage Users — Library</title>
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
            <a href="manage_users.php" class="active"><span class="icon">👥</span> Manage Users</a>
            <span class="nav-label">Transactions</span>
            <a href="view_transactions.php"><span class="icon">📋</span> Transactions</a>
            <a href="reports.php"><span class="icon">📊</span> Reports</a>
        </nav>
        <div class="sidebar-footer"><a href="../logout.php">🚪 Logout</a></div>
    </aside>
    <main class="main-content">
        <div class="page-header">
            <h1>Manage Members</h1>
            <p>View, edit, and manage registered library members.</p>
        </div>
        <div class="card">
            <div class="card-header">
                <h2>Members (<?php echo $result->num_rows; ?>)</h2>
                <div class="search-bar">
                    <span>🔍</span>
                    <input type="text" id="search-input" placeholder="Search by name or email...">
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>#</th><th>Name</th><th>Email</th><th>Active Borrows</th><th>Edit</th><th>Delete</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows===0): ?>
                        <tr><td colspan="6"><div class="empty-state"><div class="icon">👥</div><p>No users yet.</p></div></td></tr>
                        <?php else: $i=1; while ($row=$result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="user-avatar" style="width:32px;height:32px;font-size:13px;background:var(--primary-light);">
                                        <?php echo strtoupper(substr($row['name'],0,1)); ?>
                                    </div>
                                    <?php echo htmlspecialchars($row['name']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td>
                                <?php if ($row['active_borrows']>0): ?>
                                    <span class="badge badge-borrowed"><?php echo $row['active_borrows']; ?> book(s)</span>
                                <?php else: ?>
                                    <span style="color:var(--gray-400);font-size:13px;">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="edit_user.php?user_id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">✏️ Edit</a>
                            </td>
                            <td>
                                <a href="delete_user.php?user_id=<?php echo $row['id']; ?>"
                                   class="btn btn-danger btn-sm delete-btn"
                                   data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                   data-borrows="<?php echo $row['active_borrows']; ?>">
                                   🗑️ Delete
                                </a>
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
        <h3>Delete User</h3>
        <p id="delete-msg"></p>
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
    btn.addEventListener('click',function(){
        const name=this.dataset.name, borrows=parseInt(this.dataset.borrows);
        let msg=`Delete user "${name}"?`;
        if(borrows>0) msg+=` They have ${borrows} active borrow(s) — books will be returned to stock.`;
        msg+=' This cannot be undone.';
        document.getElementById('delete-msg').textContent=msg;
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
