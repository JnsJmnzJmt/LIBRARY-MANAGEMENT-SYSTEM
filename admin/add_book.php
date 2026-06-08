<?php
session_start();
include "../database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if ($_SESSION['role'] != "admin") { header("Location: ../dashboard.php"); exit(); }

$admin_name = $_SESSION['user_name'];
$error   = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $title    = trim($_POST['title']);
    $author   = trim($_POST['author']);
    $isbn     = trim($_POST['isbn']);
    $quantity = (int) $_POST['quantity'];

    if (empty($title) || empty($author) || empty($isbn) || $quantity < 1) {
        $error = "All fields are required and quantity must be at least 1.";
    } else {
        $image_name = "";
        if (!empty($_FILES['image']['name'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['image']['type'];
            $file_size = $_FILES['image']['size'];

            if (!in_array($file_type, $allowed_types)) {
                $error = "Only JPG, PNG, GIF, and WEBP images are allowed.";
            } elseif ($file_size > 5 * 1024 * 1024) {
                $error = "Image must be smaller than 5MB.";
            } else {
                $ext        = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $image_name = uniqid('book_', true) . '.' . $ext;
                $upload_path = $_SERVER['DOCUMENT_ROOT'] . "/library_MS/image/" . $image_name;

                if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $error = "Failed to upload image. Check folder permissions.";
                    $image_name = "";
                }
            }
        }

        if (empty($error)) {
            $stmt = $connection->prepare("INSERT INTO books (title, author, isbn, image, quantity) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $title, $author, $isbn, $image_name, $quantity);
            if ($stmt->execute()) {
                $success = "Book \"$title\" added successfully!";
            } else {
                $error = "Failed to add book. ISBN might already exist.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../style.css">
    <title>Add Book — Library</title>
</head>
<body>

<div id="toast-container"></div>

<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="../logo.svg" alt="Logo" style="width:42px;height:42px;border-radius:50%;margin-bottom:6px;display:block;">
            <h2>LMS</h2>
            <span>Admin Panel</span>
        </div>
        <div class="sidebar-user">
            <div class="user-avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
            <div class="user-info"><small>Administrator</small><strong><?php echo htmlspecialchars($admin_name); ?></strong></div>
        </div>
        <nav class="sidebar-nav">
            <span class="nav-label">Main</span>
            <a href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
            <span class="nav-label">Books</span>
            <a href="view_books.php"><span class="icon">📖</span> View Books</a>
            <a href="add_book.php" class="active"><span class="icon">➕</span> Add Book</a>
            <span class="nav-label">Users</span>
            <a href="manage_users.php"><span class="icon">👥</span> Manage Users</a>
            <span class="nav-label">Transactions</span>
            <a href="view_transactions.php"><span class="icon">📋</span> Transactions</a>
            <a href="reports.php"><span class="icon">📊</span> Reports</a>
        </nav>
        <div class="sidebar-footer"><a href="../logout.php">🚪 Logout</a></div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Add New Book</h1>
            <p>Fill in the details to add a book to the library collection.</p>
        </div>

        <div class="admin-form-card">
            <form id="add-book-form" action="add_book.php" method="POST" enctype="multipart/form-data" novalidate>

                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Book Title *</label>
                        <input type="text" id="title" name="title" placeholder="e.g. Atomic Habits" required
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                        <span class="field-error" id="title-error">Title is required.</span>
                    </div>
                    <div class="form-group">
                        <label for="author">Author *</label>
                        <input type="text" id="author" name="author" placeholder="e.g. James Clear" required
                               value="<?php echo isset($_POST['author']) ? htmlspecialchars($_POST['author']) : ''; ?>">
                        <span class="field-error" id="author-error">Author is required.</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="isbn">ISBN *</label>
                        <input type="text" id="isbn" name="isbn" placeholder="e.g. 978-0735211292" required
                               value="<?php echo isset($_POST['isbn']) ? htmlspecialchars($_POST['isbn']) : ''; ?>">
                        <span class="field-error" id="isbn-error">ISBN is required.</span>
                    </div>
                    <div class="form-group">
                        <label for="quantity">Quantity *</label>
                        <input type="number" id="quantity" name="quantity" placeholder="e.g. 5" min="1" required
                               value="<?php echo isset($_POST['quantity']) ? (int)$_POST['quantity'] : ''; ?>">
                        <span class="field-error" id="qty-error">Quantity must be at least 1.</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Book Cover Image</label>
                    <div class="file-upload" onclick="document.getElementById('image').click()">
                        <div style="font-size:32px">📷</div>
                        <p id="file-label">Click to upload (JPG, PNG, max 5MB)</p>
                        <input type="file" id="image" name="image" accept="image/*"
                               onchange="updateFileLabel(this)">
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:8px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">➕ Add Book</button>
                    <a href="view_books.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
function showToast(msg, type = 'success') {
    const c = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3600);
}

<?php if ($error):   ?> showToast(<?php echo json_encode($error);   ?>, 'error');   <?php endif; ?>
<?php if ($success): ?> showToast(<?php echo json_encode($success); ?>, 'success'); <?php endif; ?>

function updateFileLabel(input) {
    document.getElementById('file-label').textContent = input.files[0]
        ? input.files[0].name
        : 'Click to upload (JPG, PNG, max 5MB)';
}

document.getElementById('add-book-form').addEventListener('submit', function(e) {
    let valid = true;
    const fields = [
        { id: 'title',    err: 'title-error',  check: v => v.trim() !== '' },
        { id: 'author',   err: 'author-error', check: v => v.trim() !== '' },
        { id: 'isbn',     err: 'isbn-error',   check: v => v.trim() !== '' },
        { id: 'quantity', err: 'qty-error',    check: v => parseInt(v) >= 1 },
    ];
    fields.forEach(f => {
        const el  = document.getElementById(f.id);
        const err = document.getElementById(f.err);
        el.classList.remove('error');
        err.style.display = 'none';
        if (!f.check(el.value)) {
            el.classList.add('error');
            err.style.display = 'block';
            valid = false;
        }
    });
    if (!valid) e.preventDefault();
});
</script>

</body>
</html>