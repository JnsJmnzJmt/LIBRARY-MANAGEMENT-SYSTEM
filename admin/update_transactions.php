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

// Fetch current transaction data with book and user info
$stmt = $connection->prepare("
    SELECT t.*, u.name as user_name, b.title as book_title, b.id as bid
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    JOIN books b ON t.book_id = b.id
    WHERE t.id = ?
");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$trans = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trans) {
    header("Location: view_transactions.php?error=Transaction+not+found.");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['update_button'])) {
    $return_date = $_POST['return_date'];
    $new_status  = $_POST['status'];
    $old_status  = $trans['status'];

    $allowed_statuses = ['borrowed', 'returned'];
    if (!in_array($new_status, $allowed_statuses)) {
        $error = "Invalid status.";
    } elseif ($new_status === 'returned' && empty($return_date)) {
        $error = "Return date is required when marking as returned.";
    } else {
        $stmt2 = $connection->prepare("UPDATE transactions SET return_date = ?, status = ? WHERE id = ?");
        $ret = !empty($return_date) ? $return_date : null;
        $stmt2->bind_param("ssi", $ret, $new_status, $transaction_id);

        if ($stmt2->execute()) {
            // Restore book quantity if status changed to 'returned'
            if ($old_status === 'borrowed' && $new_status === 'returned') {
                $stmt3 = $connection->prepare("UPDATE books SET quantity = quantity + 1 WHERE id = ?");
                $stmt3->bind_param("i", $trans['bid']);
                $stmt3->execute();
                $stmt3->close();
            }
            header("Location: view_transactions.php?success=Transaction+updated+successfully.");
            exit();
        } else {
            $error = "Failed to update transaction.";
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
    <title>Update Transaction — Library</title>
</head>
<body>

<div id="toast-container"></div>

<div class="layout">

    <aside class="sidebar">
        <div class="sidebar-logo"><h2>📚 LMS</h2><span>Admin Panel</span></div>
        <div class="sidebar-user">
            <div class="user-avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
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
        </nav>
        <div class="sidebar-footer"><a href="../logout.php">🚪 Logout</a></div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Update Transaction</h1>
            <p>Edit the return date and status of this transaction.</p>
        </div>

        <!-- CURRENT INFO -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header"><h2>Current Details</h2></div>
            <div class="card-body">
                <table style="width:auto;">
                    <tr><th style="padding:8px 16px 8px 0;text-align:left;">Member</th><td><?php echo htmlspecialchars($trans['user_name']); ?></td></tr>
                    <tr><th style="padding:8px 16px 8px 0;text-align:left;">Book</th><td><?php echo htmlspecialchars($trans['book_title']); ?></td></tr>
                    <tr><th style="padding:8px 16px 8px 0;text-align:left;">Issue Date</th><td><?php echo $trans['issue_date']; ?></td></tr>
                    <tr><th style="padding:8px 16px 8px 0;text-align:left;">Due Date</th><td><?php echo $trans['due_date'] ?? '—'; ?></td></tr>
                    <tr><th style="padding:8px 16px 8px 0;text-align:left;">Current Status</th>
                        <td><span class="badge badge-<?php echo $trans['status']; ?>"><?php echo ucfirst($trans['status']); ?></span></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- UPDATE FORM -->
        <div class="admin-form-card">
            <form id="update-form" action="update_transactions.php?transaction_id=<?php echo $transaction_id; ?>" method="POST">

                <div class="form-group">
                    <label for="status">New Status *</label>
                    <select id="status" name="status" onchange="toggleReturnDate(this.value)">
                        <option value="borrowed" <?php echo $trans['status'] == 'borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                        <option value="returned" <?php echo $trans['status'] == 'returned' ? 'selected' : ''; ?>>Returned</option>
                    </select>
                </div>

                <div class="form-group" id="return-date-group"
                     style="<?php echo $trans['status'] == 'returned' ? '' : 'display:none;'; ?>">
                    <label for="return_date">Return Date *</label>
                    <input type="date" id="return_date" name="return_date"
                           value="<?php echo $trans['return_date'] ?? date('Y-m-d'); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                    <span class="field-error" id="date-error">Return date is required when marking as returned.</span>
                </div>

                <div style="display:flex;gap:12px;margin-top:8px;">
                    <button type="submit" name="update_button" class="btn btn-primary" style="flex:1;">✅ Update Transaction</button>
                    <a href="view_transactions.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
function showToast(msg, type = 'error') {
    const c = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3600);
}

<?php if ($error): ?> showToast(<?php echo json_encode($error); ?>, 'error'); <?php endif; ?>

function toggleReturnDate(status) {
    const group = document.getElementById('return-date-group');
    group.style.display = status === 'returned' ? '' : 'none';
    document.getElementById('return_date').required = status === 'returned';
}

document.getElementById('update-form').addEventListener('submit', function(e) {
    const status = document.getElementById('status').value;
    const returnDate = document.getElementById('return_date').value;
    const dateErr = document.getElementById('date-error');

    dateErr.style.display = 'none';
    document.getElementById('return_date').classList.remove('error');

    if (status === 'returned' && !returnDate) {
        document.getElementById('return_date').classList.add('error');
        dateErr.style.display = 'block';
        e.preventDefault();
    }
});
</script>

</body>
</html>
