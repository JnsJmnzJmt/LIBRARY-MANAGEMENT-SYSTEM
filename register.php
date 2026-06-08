<?php
include "database.php";
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error   = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];
    $role     = "user"; // Role is ALWAYS set server-side — never trust hidden fields

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $check = $connection->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "An account with this email already exists.";
        } else {
            // Hash the password — never store plain text
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $connection->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $hashed, $role);

            if ($stmt->execute()) {
                $success = "Account created successfully! You can now log in.";
            } else {
                $error = "Registration failed. Please try again.";
            }
            $stmt->close();
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
    <link rel="stylesheet" type="text/css" href="style.css">
    <title>Register — Library</title>
</head>
<body>

<div id="toast-container"></div>

<div class="auth-wrapper">

    <div class="auth-banner">
        <h1>📚 Join Our<br>Library<br>Today</h1>
        <p>Create an account and start borrowing books from our growing collection.</p>
    </div>

    <div class="auth-form-side">
        <div class="auth-form-box">
            <h2>Create account</h2>
            <p class="subtitle">Fill in the details below to get started</p>

            <form id="register-form" action="register.php" method="POST" novalidate>

                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" placeholder="Juan dela Cruz" required
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    <span class="field-error" id="name-error">Please enter your name.</span>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <span class="field-error" id="email-error">Please enter a valid email.</span>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Min. 6 characters" required>
                    <span class="field-error" id="password-error">Password must be at least 6 characters.</span>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
                    <span class="field-error" id="confirm-error">Passwords do not match.</span>
                </div>

                <button type="submit" class="btn btn-primary">Create Account</button>
            </form>

            <div class="auth-switch">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>
    </div>

</div>

<script>
function showToast(msg, type = 'success') {
    const c = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3600);
    <?php if ($success): ?>
    setTimeout(() => { window.location.href = 'login.php'; }, 2000);
    <?php endif; ?>
}

<?php if ($error):   ?> showToast(<?php echo json_encode($error);   ?>, 'error');   <?php endif; ?>
<?php if ($success): ?> showToast(<?php echo json_encode($success); ?>, 'success'); <?php endif; ?>

document.getElementById('register-form').addEventListener('submit', function(e) {
    let valid = true;

    const name     = document.getElementById('name');
    const email    = document.getElementById('email');
    const password = document.getElementById('password');
    const confirm  = document.getElementById('confirm_password');

    const nameErr    = document.getElementById('name-error');
    const emailErr   = document.getElementById('email-error');
    const passErr    = document.getElementById('password-error');
    const confirmErr = document.getElementById('confirm-error');

    [name, email, password, confirm].forEach(el => el.classList.remove('error'));
    [nameErr, emailErr, passErr, confirmErr].forEach(el => el.style.display = 'none');

    if (!name.value.trim()) {
        name.classList.add('error'); nameErr.style.display = 'block'; valid = false;
    }
    if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        email.classList.add('error'); emailErr.style.display = 'block'; valid = false;
    }
    if (password.value.length < 6) {
        password.classList.add('error'); passErr.style.display = 'block'; valid = false;
    }
    if (confirm.value !== password.value) {
        confirm.classList.add('error'); confirmErr.style.display = 'block'; valid = false;
    }

    if (!valid) e.preventDefault();
});
</script>

</body>
</html>
