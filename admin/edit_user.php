<?php
session_start();
include "../database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if ($_SESSION['role'] != "admin") { header("Location: ../dashboard.php"); exit(); }

$admin_name = $_SESSION['user_name'];

if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    header("Location: manage_users.php?error=Invalid+user.");
    exit();
}

$user_id = (int) $_GET['user_id'];
$error   = "";
$success = "";

$stmt = $connection->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: manage_users.php?error=User+not+found.");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $new_pass = $_POST['new_password'];

    if (empty($name) || empty($email)) {
        $error = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        // Check email not taken by another user
        $check = $connection->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $user_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "This email is already used by another account.";
        } else {
            if (!empty($new_pass)) {
                if (strlen($new_pass) < 6) {
                    $error = "New password must be at least 6 characters.";
                } else {
                    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                    $stmt2  = $connection->prepare("UPDATE users SET name=?, email=?, password=? WHERE id=?");
                    $stmt2->bind_param("sssi", $name, $email, $hashed, $user_id);
                }
            } else {
                $stmt2 = $connection->prepare("UPDATE users SET name=?, email=? WHERE id=?");
                $stmt2->bind_param("ssi", $name, $email, $user_id);
            }

            if (empty($error)) {
                if ($stmt2->execute()) {
                    $success = "User updated successfully!";
                    $user['name']  = $name;
                    $user['email'] = $email;
                } else {
                    $error = "Failed to update user.";
                }
                $stmt2->close();
            }
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../style.css">
    <title>Edit User — Library</title>
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
            <h1>Edit Member</h1>
            <p>Update information for this library member.</p>
        </div>
        <div class="admin-form-card">

            <!-- User Avatar Display -->
            <div style="text-align:center;margin-bottom:24px;">
                <div style="width:72px;height:72px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:white;margin:0 auto;">
                    <?php echo strtoupper(substr($user['name'],0,1)); ?>
                </div>
                <p style="margin-top:8px;font-size:13px;color:var(--gray-600);">Member ID: #<?php echo $user_id; ?></p>
            </div>

            <form id="edit-user-form" action="edit_user.php?user_id=<?php echo $user_id; ?>" method="POST" novalidate>
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" id="name" required value="<?php echo htmlspecialchars($user['name']); ?>">
                    <span class="field-error" id="name-error">Name is required.</span>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" id="email" required value="<?php echo htmlspecialchars($user['email']); ?>">
                    <span class="field-error" id="email-error">Valid email is required.</span>
                </div>
                <div class="form-group">
                    <label>New Password <span style="font-weight:400;color:var(--gray-600);">(leave blank to keep current)</span></label>
                    <input type="password" name="new_password" id="new_password" placeholder="Min. 6 characters">
                    <span class="field-error" id="pass-error">Password must be at least 6 characters.</span>
                </div>
                <div style="display:flex;gap:12px;margin-top:16px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">💾 Save Changes</button>
                    <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>
<script>
function showToast(msg,type='success'){const c=document.getElementById('toast-container');const t=document.createElement('div');t.className=`toast ${type}`;t.textContent=msg;c.appendChild(t);setTimeout(()=>t.remove(),3600);}
<?php if($error): ?>showToast(<?php echo json_encode($error); ?>,'error');<?php endif; ?>
<?php if($success): ?>showToast(<?php echo json_encode($success); ?>,'success');<?php endif; ?>
document.getElementById('edit-user-form').addEventListener('submit',function(e){
    let valid=true;
    const name=document.getElementById('name');
    const email=document.getElementById('email');
    const pass=document.getElementById('new_password');
    [document.getElementById('name-error'),document.getElementById('email-error'),document.getElementById('pass-error')].forEach(el=>el.style.display='none');
    [name,email,pass].forEach(el=>el.classList.remove('error'));
    if(!name.value.trim()){name.classList.add('error');document.getElementById('name-error').style.display='block';valid=false;}
    if(!email.value.trim()||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)){email.classList.add('error');document.getElementById('email-error').style.display='block';valid=false;}
    if(pass.value&&pass.value.length<6){pass.classList.add('error');document.getElementById('pass-error').style.display='block';valid=false;}
    if(!valid)e.preventDefault();
});
</script>
</body>
</html>
