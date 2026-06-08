<?php
session_start();
include "../database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if ($_SESSION['role'] != "admin") { header("Location: ../dashboard.php"); exit(); }

$admin_name = $_SESSION['user_name'];

if (!isset($_GET['book_id']) || !is_numeric($_GET['book_id'])) {
    header("Location: view_books.php?error=Invalid+book.");
    exit();
}

$book_id = (int) $_GET['book_id'];
$error   = "";
$success = "";

$stmt = $connection->prepare("SELECT * FROM books WHERE id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
    header("Location: view_books.php?error=Book+not+found.");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $title    = trim($_POST['title']);
    $author   = trim($_POST['author']);
    $isbn     = trim($_POST['isbn']);
    $quantity = (int) $_POST['quantity'];

    if (empty($title) || empty($author) || empty($isbn) || $quantity < 0) {
        $error = "All fields are required and quantity cannot be negative.";
    } else {
        $image_name = $book['image'];

        if (!empty($_FILES['image']['name'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($_FILES['image']['type'], $allowed_types)) {
                $error = "Only JPG, PNG, GIF, WEBP images are allowed.";
            } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                $error = "Image must be smaller than 5MB.";
            } else {
                $ext        = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $new_image  = uniqid('book_', true) . '.' . $ext;
                $upload_path = $_SERVER['DOCUMENT_ROOT'] . "/library_MS/image/" . $new_image;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    if (!empty($book['image'])) {
                        $old = $_SERVER['DOCUMENT_ROOT'] . "/library_MS/image/" . $book['image'];
                        if (file_exists($old)) unlink($old);
                    }
                    $image_name = $new_image;
                } else {
                    $error = "Failed to upload image.";
                }
            }
        }

        if (empty($error)) {
            $stmt2 = $connection->prepare("UPDATE books SET title=?, author=?, isbn=?, image=?, quantity=? WHERE id=?");
            $stmt2->bind_param("ssssii", $title, $author, $isbn, $image_name, $quantity, $book_id);
            if ($stmt2->execute()) {
                $success = "Book updated successfully!";
                $stmt3 = $connection->prepare("SELECT * FROM books WHERE id = ?");
                $stmt3->bind_param("i", $book_id);
                $stmt3->execute();
                $book = $stmt3->get_result()->fetch_assoc();
                $stmt3->close();
            } else {
                $error = "Failed to update. ISBN might already exist.";
            }
            $stmt2->close();
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
    <title>Edit Book — Library</title>
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
            <a href="view_books.php" class="active"><span class="icon">📖</span> View Books</a>
            <a href="add_book.php"><span class="icon">➕</span> Add Book</a>
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
            <h1>Edit Book</h1>
            <p>Update the details of this book.</p>
        </div>
        <div class="admin-form-card">
            <form id="edit-form" action="edit_book.php?book_id=<?php echo $book_id; ?>" method="POST" enctype="multipart/form-data" novalidate>
                <div style="text-align:center;margin-bottom:24px;">
                    <?php if (!empty($book['image']) && file_exists("../image/".$book['image'])): ?>
                        <img id="img-preview" src="../image/<?php echo htmlspecialchars($book['image']); ?>"
                             style="width:120px;height:160px;object-fit:cover;border-radius:8px;box-shadow:var(--shadow-md);">
                    <?php else: ?>
                        <div id="img-preview" style="width:120px;height:160px;background:var(--gray-200);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:48px;margin:0 auto;">📖</div>
                    <?php endif; ?>
                    <p style="font-size:12px;color:var(--gray-600);margin-top:8px;">Current Cover</p>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Book Title *</label>
                        <input type="text" name="title" id="title" required value="<?php echo htmlspecialchars($book['title']); ?>">
                        <span class="field-error" id="title-error">Title is required.</span>
                    </div>
                    <div class="form-group">
                        <label>Author *</label>
                        <input type="text" name="author" id="author" required value="<?php echo htmlspecialchars($book['author']); ?>">
                        <span class="field-error" id="author-error">Author is required.</span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>ISBN *</label>
                        <input type="text" name="isbn" id="isbn" required value="<?php echo htmlspecialchars($book['isbn']); ?>">
                        <span class="field-error" id="isbn-error">ISBN is required.</span>
                    </div>
                    <div class="form-group">
                        <label>Quantity *</label>
                        <input type="number" name="quantity" id="quantity" min="0" required value="<?php echo $book['quantity']; ?>">
                        <span class="field-error" id="qty-error">Cannot be negative.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Replace Cover Image (optional)</label>
                    <div class="file-upload" onclick="document.getElementById('image').click()">
                        <div style="font-size:28px;">📷</div>
                        <p id="file-label">Click to upload new image</p>
                        <input type="file" id="image" name="image" accept="image/*" onchange="previewImg(this)">
                    </div>
                </div>
                <div style="display:flex;gap:12px;margin-top:16px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">💾 Save Changes</button>
                    <a href="view_books.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>
<script>
function showToast(msg, type='success'){const c=document.getElementById('toast-container');const t=document.createElement('div');t.className=`toast ${type}`;t.textContent=msg;c.appendChild(t);setTimeout(()=>t.remove(),3600);}
<?php if($error): ?>showToast(<?php echo json_encode($error); ?>,'error');<?php endif; ?>
<?php if($success): ?>showToast(<?php echo json_encode($success); ?>,'success');<?php endif; ?>
function previewImg(input){
    if(input.files&&input.files[0]){
        const r=new FileReader();
        r.onload=e=>{
            const p=document.getElementById('img-preview');
            if(p.tagName==='IMG'){p.src=e.target.result;}
            else{const img=document.createElement('img');img.id='img-preview';img.src=e.target.result;img.style='width:120px;height:160px;object-fit:cover;border-radius:8px;box-shadow:var(--shadow-md);';p.replaceWith(img);}
        };
        r.readAsDataURL(input.files[0]);
        document.getElementById('file-label').textContent=input.files[0].name;
    }
}
document.getElementById('edit-form').addEventListener('submit',function(e){
    let valid=true;
    [{id:'title',err:'title-error',check:v=>v.trim()!==''},{id:'author',err:'author-error',check:v=>v.trim()!==''},{id:'isbn',err:'isbn-error',check:v=>v.trim()!==''},{id:'quantity',err:'qty-error',check:v=>parseInt(v)>=0}]
    .forEach(f=>{const el=document.getElementById(f.id);const er=document.getElementById(f.err);el.classList.remove('error');er.style.display='none';if(!f.check(el.value)){el.classList.add('error');er.style.display='block';valid=false;}});
    if(!valid)e.preventDefault();
});
</script>
</body>
</html>
