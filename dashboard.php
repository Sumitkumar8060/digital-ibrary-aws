<?php
require_once 'check_session.php';
require_once 'db.php';

// Only admin can access
if ($_SESSION['role'] !== 'admin') {
    header("Location: library.php");
    exit();
}

$conn = getConnection();

// ================================
// GET ALL STATS
// ================================

// Total users
$total_users = $conn->query(
    "SELECT COUNT(*) as count FROM users"
)->fetch_assoc()['count'];

// Total books
$total_books = $conn->query(
    "SELECT COUNT(*) as count FROM books"
)->fetch_assoc()['count'];

// Total downloads
$total_downloads = $conn->query(
    "SELECT SUM(download_count) as count FROM books"
)->fetch_assoc()['count'] ?? 0;

// Total videos
$total_videos = $conn->query(
    "SELECT COUNT(*) as count FROM videos"
)->fetch_assoc()['count'];

// Total video views
$total_views = $conn->query(
    "SELECT SUM(view_count) as count FROM videos"
)->fetch_assoc()['count'] ?? 0;

// Total logins today
$total_logins_today = $conn->query(
    "SELECT COUNT(*) as count FROM login_logs 
     WHERE DATE(login_time) = CURDATE() 
     AND status = 'success'"
)->fetch_assoc()['count'];

// ================================
// GET ALL BOOKS WITH STATS
// ================================
$books = $conn->query(
    "SELECT b.id, b.title, b.author, b.category,
            b.total_copies, b.available_copies,
            b.download_count, b.created_at,
            u.username as added_by
     FROM books b
     LEFT JOIN users u ON b.added_by = u.id
     ORDER BY b.created_at DESC"
);

// ================================
// GET ALL USERS
// ================================
$users = $conn->query(
    "SELECT id, username, email, role,
            created_at, last_login
     FROM users
     ORDER BY created_at DESC"
);

// ================================
// GET RECENT LOGINS
// ================================
$recent_logins = $conn->query(
    "SELECT l.username, l.login_time,
            l.ip_address, l.status
     FROM login_logs l
     ORDER BY l.login_time DESC
     LIMIT 10"
);

// ================================
// GET MOST DOWNLOADED BOOKS
// ================================
$top_books = $conn->query(
    "SELECT title, author, download_count
     FROM books
     ORDER BY download_count DESC
     LIMIT 5"
);

// ================================
// ADD NEW BOOK
// ================================
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) {

    $title    = trim($_POST['title']    ?? '');
    $author   = trim($_POST['author']   ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $img_url  = trim($_POST['image_url']  ?? '');
    $book_url = trim($_POST['book_url']   ?? '');
    $copies   = (int)($_POST['total_copies'] ?? 1);
    $added_by = $_SESSION['user_id'];

    if (!empty($title) && !empty($author)) {

        $stmt = $conn->prepare(
            "INSERT INTO books 
                (title, author, description, category,
                 image_url, book_url, total_copies,
                 available_copies, added_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "ssssssiis",
            $title, $author, $desc, $category,
            $img_url, $book_url,
            $copies, $copies,
            $added_by
        );

        if ($stmt->execute()) {
            $success_msg = "✅ Book added successfully!";
            // Refresh stats
            $total_books = $conn->query(
                "SELECT COUNT(*) as count FROM books"
            )->fetch_assoc()['count'];
        } else {
            $error_msg = "❌ Error adding book.";
        }
        $stmt->close();
    } else {
        $error_msg = "❌ Title and Author are required.";
    }
}

// ================================
// DELETE BOOK
// ================================
if (isset($_GET['delete_book']) && is_numeric($_GET['delete_book'])) {
    $del_id = (int)$_GET['delete_book'];
    $del    = $conn->prepare("DELETE FROM books WHERE id = ?");
    $del->bind_param("i", $del_id);
    $del->execute();
    $del->close();
    header("Location: dashboard.php?deleted=1");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Dashboard Specific Styles */
        .dash-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* STATS CARDS */
        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 40px;
            justify-content: center;
        }

        .stat-card {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 25px 30px;
            text-align: center;
            color: white;
            min-width: 160px;
            border: 1px solid rgba(255,255,255,0.2);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .stat-icon {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 10px;
        }

        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            display: block;
        }

        .stat-card .stat-label {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 5px;
        }

        /* PANELS */
        .panel {
            background: rgba(255,255,255,0.95);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .panel h3 {
            color: #1a1a2e;
            font-size: 1.2rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        /* FORM */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-row input,
        .form-row select,
        .form-row textarea {
            flex: 1;
            min-width: 200px;
            padding: 10px 14px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.3s;
        }

        .form-row input:focus,
        .form-row textarea:focus {
            border-color: #667eea;
        }

        .btn-add {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-add:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* TABLE */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }

        th {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            color: #444;
            vertical-align: middle;
        }

        tr:hover td {
            background: #f8f6ff;
        }

        .badge-admin {
            background: #e74c3c;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-user {
            background: #3498db;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .btn-del {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.3s;
        }

        .btn-del:hover { background: #c0392b; }

        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .status-success {
            color: #27ae60;
            font-weight: 600;
        }

        .status-failed {
            color: #e74c3c;
            font-weight: 600;
        }
    </style>
</head>

<body>

<!-- HEADER -->
<div class="header">
    <h1>⚙️ Admin Dashboard</h1>
    <p>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</p>
</div>

<!-- NAVBAR -->
<div class="navbar">
    <a href="library.php">📚 Library</a>
    <a href="dashboard.php">⚙️ Dashboard</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<div class="dash-wrapper">

    <!-- ===== STATS ===== -->
    <h2 class="section-title">📊 Overview</h2>
    <div class="section-divider"></div>

    <div class="stats-grid">

        <div class="stat-card">
            <span class="stat-icon">👥</span>
            <span class="stat-number"><?= $total_users ?></span>
            <span class="stat-label">Total Users</span>
        </div>

        <div class="stat-card">
            <span class="stat-icon">📚</span>
            <span class="stat-number"><?= $total_books ?></span>
            <span class="stat-label">Total Books</span>
        </div>

        <div class="stat-card">
            <span class="stat-icon">⬇️</span>
            <span class="stat-number"><?= $total_downloads ?></span>
            <span class="stat-label">Total Downloads</span>
        </div>

        <div class="stat-card">
            <span class="stat-icon">🎬</span>
            <span class="stat-number"><?= $total_videos ?></span>
            <span class="stat-label">Total Videos</span>
        </div>

        <div class="stat-card">
            <span class="stat-icon">👁️</span>
            <span class="stat-number"><?= $total_views ?></span>
            <span class="stat-label">Video Views</span>
        </div>

        <div class="stat-card">
            <span class="stat-icon">🔐</span>
            <span class="stat-number"><?= $total_logins_today ?></span>
            <span class="stat-label">Logins Today</span>
        </div>

    </div>

    <!-- ===== ADD BOOK FORM ===== -->
    <div class="panel">
        <h3>➕ Add New Book</h3>

        <?php if ($success_msg): ?>
            <div class="alert-success"><?= $success_msg ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert-error"><?= $error_msg ?></div>
        <?php endif; ?>

        <form method="POST" action="dashboard.php">

            <div class="form-row">
                <input type="text" 
                       name="title" 
                       placeholder="📖 Book Title *" 
                       required>

                <input type="text" 
                       name="author" 
                       placeholder="✍️ Author *" 
                       required>

                <select name="category">
                    <option value="">-- Category --</option>
                    <option value="Technology">Technology</option>
                    <option value="Science">Science</option>
                    <option value="Business">Business</option>
                    <option value="Mathematics">Mathematics</option>
                    <option value="Literature">Literature</option>
                    <option value="History">History</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-row">
                <input type="url" 
                       name="image_url" 
                       placeholder="🖼️ Image URL (S3 link)">

                <input type="url" 
                       name="book_url" 
                       placeholder="📎 Book URL (S3 link)">

                <input type="number" 
                       name="total_copies" 
                       placeholder="📦 Total Copies" 
                       value="1" min="1">
            </div>

            <div class="form-row">
                <textarea name="description" 
                          placeholder="📝 Book description..." 
                          rows="3" 
                          style="width:100%"></textarea>
            </div>

            <button type="submit" 
                    name="add_book" 
                    class="btn-add">
                ➕ Add Book
            </button>

        </form>
    </div>

    <!-- ===== BOOKS TABLE ===== -->
    <div class="panel">
        <h3>📚 All Books (<?= $books->num_rows ?>)</h3>
        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Category</th>
                    <th>Copies</th>
                    <th>Downloads</th>
                    <th>Added By</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($book = $books->fetch_assoc()): ?>
            <tr>
                <td><?= $book['id'] ?></td>
                <td><strong><?= htmlspecialchars($book['title']) ?></strong></td>
                <td><?= htmlspecialchars($book['author']) ?></td>
                <td><?= htmlspecialchars($book['category']) ?></td>
                <td><?= $book['available_copies'] ?>/<?= $book['total_copies'] ?></td>
                <td>⬇️ <?= $book['download_count'] ?></td>
                <td><?= htmlspecialchars($book['added_by'] ?? 'system') ?></td>
                <td><?= date('d M Y', strtotime($book['created_at'])) ?></td>
                <td>
                    <a href="dashboard.php?delete_book=<?= $book['id'] ?>"
                       onclick="return confirm('Delete this book?')">
                        <button class="btn-del">🗑 Delete</button>
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- ===== USERS TABLE ===== -->
    <div class="panel">
        <h3>👥 All Users</h3>
        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Registered</th>
                    <th>Last Login</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($user = $users->fetch_assoc()): ?>
            <tr>
                <td><?= $user['id'] ?></td>
                <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td>
                    <span class="badge-<?= $user['role'] ?>">
                        <?= strtoupper($user['role']) ?>
                    </span>
                </td>
                <td><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                <td>
                    <?= $user['last_login']
                        ? date('d M Y H:i', strtotime($user['last_login']))
                        : '— Never —' ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- ===== RECENT LOGINS ===== -->
    <div class="panel">
        <h3>🔐 Recent Login Activity</h3>
        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Time</th>
                    <th>IP Address</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($log = $recent_logins->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($log['username']) ?></td>
                <td><?= date('d M Y H:i:s', strtotime($log['login_time'])) ?></td>
                <td><?= htmlspecialchars($log['ip_address']) ?></td>
                <td>
                    <span class="status-<?= $log['status'] ?>">
                        <?= $log['status'] === 'success' ? '✅ Success' : '❌ Failed' ?>
                    </span>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- ===== TOP BOOKS ===== -->
    <div class="panel">
        <h3>🏆 Most Downloaded Books</h3>
        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Downloads</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            $rank = 1;
            while ($tb = $top_books->fetch_assoc()): 
            ?>
            <tr>
                <td><?= $rank++ ?> 🏆</td>
                <td><strong><?= htmlspecialchars($tb['title']) ?></strong></td>
                <td><?= htmlspecialchars($tb['author']) ?></td>
                <td>⬇️ <?= $tb['download_count'] ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>

</div>

<!-- FOOTER -->
<div class="footer">
    © 2024 Digital Library · Admin Panel · AWS EC2 + RDS
</div>

</body>
</html>