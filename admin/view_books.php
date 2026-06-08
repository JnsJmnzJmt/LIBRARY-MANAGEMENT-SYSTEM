<?php
session_start();
include "../database.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if ($_SESSION['role'] != "admin") { header("Location: ../dashboard.php"); exit(); }

$admin_name = $_SESSION['user_name'];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    $stmt = $connection->prepare("SELECT * FROM books WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ? ORDER BY title ASC");
    $like = "%$search%";
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $connection->query("SELECT * FROM books ORDER BY title ASC");
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
    <title>View Books — Library</title>
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
            <a href="view_books.php" class="active"><span class="icon">📖</span> View Books</a>
            <a href="add_book.php"><span class="icon">➕</span> Add Book</a>
            <span class="nav-label">Users</span>
            <a href="manage_users.php"><span class="icon">👥</span> Manage Users</a>
            <span class="nav-label">Transactions</span>
            <a href="view_transactions.php"><span class="icon">📋</span> Transactions</a>
        </nav>
        <div class="sidebar-footer"><a href="../logout.php">🚪 Logout</a></div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Book Collection</h1>
            <p>Manage all books in the library.</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>All Books (<?php echo $result->num_rows; ?>)</h2>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <form method="GET" style="display:flex;gap:8px;">
                        <div class="search-bar">
                            <span>🔍</span>
                            <input type="text" name="search" placeholder="Search books..."
                                   value="<?php echo htmlspecialchars($search); ?>" id="search-input">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Search</button>
                        <?php if ($search): ?><a href="view_books.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
                    </form>
                    <a href="add_book.php" class="btn btn-success btn-sm">➕ Add Book</a>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Cover</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>ISBN</th>
                            <th>Quantity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="6"><div class="empty-state"><div class="icon">📭</div><p>No books found.</p></div></td></tr>
                        <?php else: ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($row['image']) && file_exists("../image/" . $row['image'])): ?>
                                        <img src="../image/<?php echo htmlspecialchars($row['image']); ?>"
                                             style="width:50px;height:65px;object-fit:cover;border-radius:4px;">
                                    <?php else: ?>
                                        <div style="width:50px;height:65px;background:var(--gray-200);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:20px;">📖</div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['author']); ?></td>
                                <td><code style="font-size:12px;"><?php echo htmlspecialchars($row['isbn']); ?></code></td>
                                <td>
                                    <span class="badge <?php echo $row['quantity'] > 0 ? 'badge-returned' : 'badge-overdue'; ?>">
                                        <?php echo $row['quantity']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="delete_book.php?book_id=<?php echo $row['id']; ?>"
                                       class="btn btn-danger btn-sm delete-btn"
                                       data-title="<?php echo htmlspecialchars($row['title']); ?>">
                                       🗑️ Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="delete-modal">
    <div class="modal">
        <h3>Delete Book</h3>
        <p id="delete-msg">Are you sure you want to delete this book?</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="cancelDelete()">Cancel</button>
            <a href="#" id="delete-confirm-btn" class="btn btn-danger">Yes, Delete</a>
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
}

<?php if ($success): ?> showToast(<?php echo json_encode($success); ?>, 'success'); <?php endif; ?>
<?php if ($error):   ?> showToast(<?php echo json_encode($error);   ?>, 'error');   <?php endif; ?>

function cancelDelete() {
    document.getElementById('delete-modal').classList.remove('active');
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.delete-btn').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const href  = this.href;
            const title = this.dataset.title || 'this book';
            document.getElementById('delete-msg').textContent = `Delete "${title}"? This cannot be undone.`;
            document.getElementById('delete-confirm-btn').href = href;
            document.getElementById('delete-modal').classList.add('active');
        });
    });
});

document.getElementById('search-input').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>

</body>
</html>