<?php
session_start();
include "database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['role'] == "admin") {
    header("Location: admin/dashboard.php");
    exit();
}

$user_name = $_SESSION['user_name'];

// Search support
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    $stmt = $connection->prepare("SELECT * FROM books WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ?");
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
    <link rel="stylesheet" type="text/css" href="style.css">
    <title>Browse Books — Library</title>
</head>
<body>

<div id="toast-container"></div>

<!-- TOP BAR -->
<div class="top-bar">
    <h1>📚 Browse Books</h1>
    <div class="top-bar-actions">
        <form method="GET" action="index.php" style="display:flex;gap:8px;align-items:center;">
            <div class="search-bar">
                <span>🔍</span>
                <input type="text" name="search" placeholder="Search title, author, ISBN..."
                       value="<?php echo htmlspecialchars($search); ?>"
                       id="search-input">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if ($search): ?>
                <a href="index.php" class="btn btn-secondary btn-sm">Clear</a>
            <?php endif; ?>
        </form>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">🏠 Dashboard</a>
        <a href="logout.php" onclick="return confirmLogout()" class="btn btn-danger btn-sm">Logout</a>
    </div>
</div>

<!-- BOOKS GRID -->
<div class="books-grid" id="books-grid">
    <?php if ($result->num_rows === 0): ?>
        <div style="grid-column:1/-1;">
            <div class="empty-state">
                <div class="icon">📭</div>
                <p>No books found<?php echo $search ? ' for "' . htmlspecialchars($search) . '"' : ''; ?>.</p>
            </div>
        </div>
    <?php else: ?>
        <?php while ($row = $result->fetch_assoc()): ?>
        <div class="book-card">
            <?php if (!empty($row['image']) && file_exists("image/" . $row['image'])): ?>
                <img class="book-card-img" src="image/<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>">
            <?php else: ?>
                <div class="book-card-img-placeholder">📖</div>
            <?php endif; ?>

            <div class="book-card-body">
                <div class="book-card-title"><?php echo htmlspecialchars($row['title']); ?></div>
                <div class="book-card-author">by <?php echo htmlspecialchars($row['author']); ?></div>
                <div class="book-card-meta">ISBN: <?php echo htmlspecialchars($row['isbn']); ?></div>

                <div class="book-card-footer">
                    <?php if ($row['quantity'] > 0): ?>
                        <span class="quantity-badge available"><?php echo $row['quantity']; ?> available</span>
                        <button class="borrow-btn"
                            onclick="confirmBorrow(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['title'])); ?>')">
                            Borrow
                        </button>
                    <?php else: ?>
                        <span class="quantity-badge unavailable">Out of stock</span>
                        <button class="borrow-btn" disabled>Unavailable</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<!-- BORROW CONFIRM MODAL -->
<div class="modal-overlay" id="borrow-modal">
    <div class="modal">
        <h3>Confirm Borrow</h3>
        <p id="borrow-msg">Are you sure you want to borrow this book?</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal('borrow-modal')">Cancel</button>
            <a href="#" id="borrow-confirm-btn" class="btn btn-primary">Yes, Borrow</a>
        </div>
    </div>
</div>

<!-- LOGOUT CONFIRM MODAL -->
<div class="modal-overlay" id="logout-modal">
    <div class="modal">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to log out?</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal('logout-modal')">Cancel</button>
            <a href="logout.php" class="btn btn-danger">Yes, Logout</a>
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

function confirmBorrow(bookId, title) {
    document.getElementById('borrow-msg').textContent = `Borrow "${title}"?`;
    document.getElementById('borrow-confirm-btn').href = `borrow.php?book_id=${bookId}`;
    document.getElementById('borrow-modal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function confirmLogout() {
    document.getElementById('logout-modal').classList.add('active');
    return false;
}

// Show toast from URL params
<?php if ($success): ?> showToast(<?php echo json_encode($success); ?>, 'success'); <?php endif; ?>
<?php if ($error):   ?> showToast(<?php echo json_encode($error); ?>,   'error');   <?php endif; ?>

// Live search filter (instant client-side filter while typing)
document.getElementById('search-input').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.book-card').forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(q) ? '' : 'none';
    });
});
</script>

</body>
</html>
