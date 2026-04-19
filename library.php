<?php
// Protect this page
require_once 'check_session.php';
require_once 'db.php';

$conn = getConnection();

// ================================
// FETCH ALL BOOKS FROM RDS
// ================================
$books_result = $conn->query(
    "SELECT id, title, author, description, 
            category, image_url, book_url,
            total_copies, available_copies,
            download_count
     FROM books
     ORDER BY created_at DESC"
);

$books = [];
while ($row = $books_result->fetch_assoc()) {
    $books[] = $row;
}

// ================================
// FETCH ALL VIDEOS FROM RDS
// ================================
$videos_result = $conn->query(
    "SELECT id, title, description, duration,
            level, thumbnail_url, video_url,
            view_count
     FROM videos
     ORDER BY created_at DESC"
);

$videos = [];
while ($row = $videos_result->fetch_assoc()) {
    $videos[] = $row;
}

// ================================
// TRACK BOOK DOWNLOAD
// ================================
if (isset($_GET['download']) && is_numeric($_GET['download'])) {

    $book_id = (int)$_GET['download'];
    $user_id = $_SESSION['user_id'];

    // Log the download
    $dl = $conn->prepare(
        "INSERT INTO book_downloads 
            (user_id, book_id)
         VALUES (?, ?)"
    );
    $dl->bind_param("ii", $user_id, $book_id);
    $dl->execute();
    $dl->close();

    // Increment download count
    $upd = $conn->prepare(
        "UPDATE books 
         SET download_count = download_count + 1
         WHERE id = ?"
    );
    $upd->bind_param("i", $book_id);
    $upd->execute();
    $upd->close();

    // Get the book URL
    $get_url = $conn->prepare(
        "SELECT book_url FROM books WHERE id = ?"
    );
    $get_url->bind_param("i", $book_id);
    $get_url->execute();
    $url_result = $get_url->get_result();
    $book_data  = $url_result->fetch_assoc();
    $get_url->close();

    // Redirect to actual S3 link
    header("Location: " . $book_data['book_url']);
    exit();
}

// ================================
// TRACK VIDEO VIEW
// ================================
if (isset($_GET['watch']) && is_numeric($_GET['watch'])) {

    $video_id = (int)$_GET['watch'];
    $user_id  = $_SESSION['user_id'];

    // Log the view
    $vw = $conn->prepare(
        "INSERT INTO video_views 
            (user_id, video_id)
         VALUES (?, ?)"
    );
    $vw->bind_param("ii", $user_id, $video_id);
    $vw->execute();
    $vw->close();

    // Increment view count
    $vupd = $conn->prepare(
        "UPDATE videos 
         SET view_count = view_count + 1
         WHERE id = ?"
    );
    $vupd->bind_param("i", $video_id);
    $vupd->execute();
    $vupd->close();
}

$conn->close();

// ================================
// BADGE LABELS
// ================================
function getBadge($index) {
    $badges = ['FREE', 'NEW', 'HOT', 'TOP'];
    return $badges[$index % count($badges)];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Library</title>
    <link rel="stylesheet" href="style.css">

    <script>
        function playVideo(videoId) {
            // Hide all thumbnails & players
            document.querySelectorAll('.video-thumb-card').forEach(t => {
                t.style.display = 'none';
            });
            document.querySelectorAll('.video-player').forEach(p => {
                p.style.display = 'none';
            });

            // Show selected player
            document.getElementById('player' + videoId).style.display = 'block';
            document.getElementById('player' + videoId)
                .scrollIntoView({ behavior: 'smooth' });

            // Track view via URL (without reload)
            fetch('library.php?watch=' + videoId);
        }

        function closeVideo() {
            document.querySelectorAll('.video-player').forEach(p => {
                p.style.display = 'none';
            });
            document.querySelectorAll('video').forEach(v => v.pause());
            document.querySelectorAll('.video-thumb-card').forEach(t => {
                t.style.display = 'block';
            });
        }
    </script>
</head>

<body>

<!-- HEADER -->
<div class="header">
    <h1>📚 Digital Library</h1>
    <p>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>! 👋</p>
</div>

<!-- NAVBAR -->
<div class="navbar">
    <a href="library.php">🏠 Home</a>
    <a href="#books">📖 Books</a>
    <a href="#videos">🎬 Videos</a>
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="dashboard.php">⚙️ Dashboard</a>
    <?php endif; ?>
    <a href="logout.php">🚪 Logout</a>
</div>

<!-- BOOKS SECTION -->
<div class="container" id="books">

    <h2 class="section-title">📖 Book Collection</h2>
    <p class="section-subtitle">
        Total Books: <strong><?= count($books) ?></strong> |
        Click Download to get your copy
    </p>
    <div class="section-divider"></div>

    <div class="books-grid">

    <?php if (empty($books)): ?>
        <p style="color:white; text-align:center;">
            No books available yet.
        </p>
    <?php else: ?>

        <?php foreach ($books as $index => $book): ?>

        <div class="card">
            <span class="card-badge">
                <?= getBadge($index) ?>
            </span>

            <img src="<?= htmlspecialchars($book['image_url']) ?>"
                 alt="<?= htmlspecialchars($book['title']) ?>">

            <div class="card-body">
                <h3><?= htmlspecialchars($book['title']) ?></h3>
                <p><?= htmlspecialchars($book['author']) ?></p>
                <p><?= htmlspecialchars($book['description']) ?></p>
                <small>
                    📦 Copies: <?= $book['available_copies'] ?>/<?= $book['total_copies'] ?>
                    | ⬇️ Downloads: <?= $book['download_count'] ?>
                </small>
            </div>

            <a href="library.php?download=<?= $book['id'] ?>">
                ⬇️ Download Book
            </a>
        </div>

        <?php endforeach; ?>

    <?php endif; ?>

    </div>
</div>

<!-- VIDEOS SECTION -->
<div class="video-section" id="videos">

    <h2 class="section-title">🎬 Video Lectures</h2>
    <p class="section-subtitle">
        Total Videos: <strong><?= count($videos) ?></strong> |
        Click to watch
    </p>
    <div class="section-divider"></div>

    <!-- THUMBNAILS -->
    <div class="video-grid">

    <?php foreach ($videos as $video): ?>

        <div id="thumb<?= $video['id'] ?>" 
             class="video-thumb-card" 
             onclick="playVideo(<?= $video['id'] ?>)">

            <div class="video-thumb-wrapper">
                <img src="<?= htmlspecialchars($video['thumbnail_url']) ?>"
                     alt="<?= htmlspecialchars($video['title']) ?>"
                     class="video-thumbnail">
                <div class="play-overlay">▶️</div>
            </div>

            <div class="video-thumb-info">
                <h4><?= htmlspecialchars($video['title']) ?></h4>
                <span>
                    ⏱ <?= htmlspecialchars($video['duration']) ?> · 
                    <?= htmlspecialchars($video['level']) ?> |
                    👁 <?= $video['view_count'] ?> views
                </span>
            </div>

        </div>

    <?php endforeach; ?>

    </div>

    <!-- VIDEO PLAYERS -->
    <?php foreach ($videos as $video): ?>

    <div id="player<?= $video['id'] ?>" class="video-player">
        <div class="video-player-inner">
            <h3 style="color:white; margin-bottom:15px;">
                🎬 <?= htmlspecialchars($video['title']) ?>
            </h3>
            <video controls>
                <source src="<?= htmlspecialchars($video['video_url']) ?>"
                        type="video/mp4">
            </video>
            <br>
            <button class="close-btn" onclick="closeVideo()">
                ✖ Close Video
            </button>
        </div>
    </div>

    <?php endforeach; ?>

</div>

<!-- FOOTER -->
<div class="footer">
    © 2024 Digital Library · Built on AWS EC2 + S3 + RDS + CloudFront
</div>

</body>
</html>