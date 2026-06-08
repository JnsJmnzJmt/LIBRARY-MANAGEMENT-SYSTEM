<?php
session_start();
include "../database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if ($_SESSION['role'] != "admin") { header("Location: ../dashboard.php"); exit(); }

$admin_name = $_SESSION['user_name'];

if (!isset($_GET['transaction_id']) || !is_numeric($_GET['transaction_id'])) {
    header("Location: view_transactions.php?error=Invalid+transaction.");
    exit();
}

$transaction_id = (int) $_GET['transaction_id'];
$error   = "";
$success = "";

$stmt = $connection->prepare("
    SELECT t.*, u.name as user_name, b.title as book_title
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    JOIN books b ON t.book_id = b.id
    WHERE t.id = ? AND t.status = 'borrowed'
");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$trans = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trans) {
    header("Location: view_transactions.php?error=Transaction+not+found+or+already+returned.");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $new_due_date = $_POST['due_date'];

    if (empty($new_due_date)) {
        $error = "Please select a due date.";
    } elseif (strtotime($new_due_date) < strtotime(date('Y-m-d'))) {
        $error = "Due date cannot be in the past.";
    } else {
        $stmt2 = $connection->prepare("UPDATE transactions SET due_date = ? WHERE id = ?");
        $stmt2->bind_param("si", $new_due_date, $transaction_id);
        if ($stmt2->execute()) {
            $success = "Deadline updated successfully!";
            $trans['due_date'] = $new_due_date;
        } else {
            $error = "Failed to update deadline.";
        }
        $stmt2->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../style.css">
    <title>Set Deadline — Library</title>
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
            <h1>Set Borrow Deadline</h1>
            <p>Update the due date for this borrowed book.</p>
        </div>

        <!-- Info Card -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-body">
                <table style="width:auto;border-collapse:collapse;">
                    <tr>
                        <th style="padding:8px 20px 8px 0;color:var(--gray-600);font-size:12px;text-transform:uppercase;letter-spacing:0.5px;text-align:left;">Member</th>
                        <td style="padding:8px 0;font-weight:600;"><?php echo htmlspecialchars($trans['user_name']); ?></td>
                    </tr>
                    <tr>
                        <th style="padding:8px 20px 8px 0;color:var(--gray-600);font-size:12px;text-transform:uppercase;letter-spacing:0.5px;text-align:left;">Book</th>
                        <td style="padding:8px 0;font-weight:600;"><?php echo htmlspecialchars($trans['book_title']); ?></td>
                    </tr>
                    <tr>
                        <th style="padding:8px 20px 8px 0;color:var(--gray-600);font-size:12px;text-transform:uppercase;letter-spacing:0.5px;text-align:left;">Borrowed On</th>
                        <td style="padding:8px 0;"><?php echo $trans['issue_date']; ?></td>
                    </tr>
                    <tr>
                        <th style="padding:8px 20px 8px 0;color:var(--gray-600);font-size:12px;text-transform:uppercase;letter-spacing:0.5px;text-align:left;">Current Deadline</th>
                        <td style="padding:8px 0;">
                            <?php if (!empty($trans['due_date'])): ?>
                                <?php
                                $is_overdue = strtotime($trans['due_date']) < time();
                                ?>
                                <span class="badge <?php echo $is_overdue ? 'badge-overdue' : 'badge-borrowed'; ?>">
                                    <?php echo $trans['due_date']; ?>
                                    <?php if ($is_overdue) echo ' ⚠️ Overdue'; ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--gray-400);">No deadline set</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Set Deadline Form -->
        <div class="admin-form-card">
            <form id="deadline-form" action="set_deadline.php?transaction_id=<?php echo $transaction_id; ?>" method="POST">
                <div class="form-group">
                    <label for="due_date">New Due Date *</label>
                    <input type="date" id="due_date" name="due_date"
                           min="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo !empty($trans['due_date']) ? $trans['due_date'] : date('Y-m-d', strtotime('+14 days')); ?>">
                    <span class="field-error" id="date-error">Please select a valid due date.</span>
                </div>

                <!-- Quick preset buttons -->
                <div style="margin-bottom:20px;">
                    <p style="font-size:12px;color:var(--gray-600);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Quick Presets</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="setDays(7)">+7 Days</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="setDays(14)">+14 Days</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="setDays(30)">+30 Days</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="setDays(60)">+60 Days</button>
                    </div>
                </div>

                <div style="display:flex;gap:12px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">📅 Update Deadline</button>
                    <a href="view_transactions.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>
<script>
function showToast(msg,type='success'){const c=document.getElementById('toast-container');const t=document.createElement('div');t.className=`toast ${type}`;t.textContent=msg;c.appendChild(t);setTimeout(()=>t.remove(),3600);}
<?php if($error): ?>showToast(<?php echo json_encode($error); ?>,'error');<?php endif; ?>
<?php if($success): ?>showToast(<?php echo json_encode($success); ?>,'success');<?php endif; ?>

function setDays(days) {
    const d = new Date();
    d.setDate(d.getDate() + days);
    const yyyy = d.getFullYear();
    const mm   = String(d.getMonth()+1).padStart(2,'0');
    const dd   = String(d.getDate()).padStart(2,'0');
    document.getElementById('due_date').value = `${yyyy}-${mm}-${dd}`;
}

document.getElementById('deadline-form').addEventListener('submit', function(e) {
    const due = document.getElementById('due_date');
    const err = document.getElementById('date-error');
    due.classList.remove('error');
    err.style.display = 'none';
    if (!due.value) {
        due.classList.add('error');
        err.style.display = 'block';
        e.preventDefault();
    }
});
</script>
</body>
</html>
