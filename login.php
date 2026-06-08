<?php
include "database.php";
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    // Prepared statement — prevents SQL injection
    $stmt = $connection->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // password_verify checks hashed password
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id']   = $row['id'];
            $_SESSION['role']      = $row['role'];
            $_SESSION['user_name'] = $row['name'];

            if ($row['role'] == "admin") { 
                header("Location: admin/dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "No account found with that email.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="style.css">
    <title>Login — Library</title>
</head>
<body>

<div id="toast-container"></div>

<div class="auth-wrapper">

    <!-- LEFT BANNER -->
    <div class="auth-banner">
        <h1>📚 Library<br>Management<br>System</h1>
        <p>Your gateway to thousands of books. Borrow, return, and explore.</p>
    </div>

    <!-- RIGHT FORM -->
    <div class="auth-form-side">
        <div class="auth-form-box">
            <h2>Welcome back</h2>
            <p class="subtitle">Sign in to your account to continue</p>

            <?php if ($error): ?>
                <div id="server-error" style="background:#f8d7da;color:#842029;padding:12px 16px;border-radius:6px;font-size:14px;margin-bottom:16px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form id="login-form" action="login.php" method="POST" novalidate>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="you@gmail.com" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <span class="field-error" id="email-error">Please enter a valid email.</span>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <span class="field-error" id="password-error">Password is required.</span>
                </div>

                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>

            <div class="auth-switch">
                Don't have an account? <a href="register.php">Create one</a>
            </div>
        </div>
    </div>

</div>

<script>
// Toast helper
function showToast(msg, type = 'error') {
    const c = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3600);
}

// Client-side validation
document.getElementById('login-form').addEventListener('submit', function(e) {
    let valid = true;

    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const emailErr = document.getElementById('email-error');
    const passErr  = document.getElementById('password-error');

    // Reset
    [email, password].forEach(el => el.classList.remove('error'));
    [emailErr, passErr].forEach(el => el.style.display = 'none');

    if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        email.classList.add('error');
        emailErr.style.display = 'block';
        valid = false;
    }

    if (!password.value) {
        password.classList.add('error');
        passErr.style.display = 'block';
        valid = false;
    }

    if (!valid) e.preventDefault();
});

// Show server error as toast too
const serverErr = document.getElementById('server-error');
if (serverErr) showToast(serverErr.textContent.trim());
</script>

</body>
</html>
