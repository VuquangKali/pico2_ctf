<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['user_id'] = $user['id'];
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$chal_id = $_GET['id'] ?? 0;

// Lấy thông tin challenge
$stmt = $pdo->prepare("SELECT ch.*, c.name as category_name FROM challenges ch LEFT JOIN categories c ON ch.category_id = c.id WHERE ch.id = ?");
$stmt->execute([$chal_id]);
$challenge = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$challenge) {
    die("Không tìm thấy bài tập này.");
}

// Lấy trạng thái submit
$stmt = $pdo->prepare("SELECT is_correct FROM submissions WHERE user_id = ? AND challenge_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id, $chal_id]);
$submission = $stmt->fetch();
$is_solved = $submission && $submission['is_correct'];

// Lấy vote trung bình
$stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(id) as total_votes FROM ratings WHERE challenge_id = ?");
$stmt->execute([$chal_id]);
$rating_data = $stmt->fetch();
$avg_rating = round($rating_data['avg_rating'] ?? 0, 1);
$total_votes = $rating_data['total_votes'];

// Lấy vote của user hiện tại
$stmt = $pdo->prepare("SELECT rating FROM ratings WHERE user_id = ? AND challenge_id = ?");
$stmt->execute([$user_id, $chal_id]);
$user_rating = $stmt->fetchColumn();

$message = '';
$error = '';

// Tạo CSRF token nếu chưa có
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Kiểm tra CSRF token cho tất cả các request POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Hệ thống phát hiện có dấu hiệu giả mạo request (CSRF).");
    }
}

// Xử lý nộp flag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_flag') {
    $submitted_flag = trim($_POST['flag'] ?? '');
    
    // Nếu đã làm bài xong thì không chấm lại, nhưng vẫn cho nộp thử
    $is_correct = ($submitted_flag === $challenge['flag']);
    
    // Lưu lịch sử nộp
    $stmt = $pdo->prepare("INSERT INTO submissions (user_id, challenge_id, is_correct) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $chal_id, $is_correct ? 1 : 0]);
    
    if ($is_correct) {
        $message = "Chính xác! Bạn đã giành được " . $challenge['points'] . " điểm.";
        $is_solved = true;
    } else {
        $error = "Flag không chính xác. Hãy thử lại!";
    }
}

// Xử lý Vote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_rating') {
    $rating = (int)($_POST['rating'] ?? 0);
    if ($rating >= 1 && $rating <= 5) {
        if ($user_rating) {
            $stmt = $pdo->prepare("UPDATE ratings SET rating = ? WHERE user_id = ? AND challenge_id = ?");
            $stmt->execute([$rating, $user_id, $chal_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO ratings (user_id, challenge_id, rating) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $chal_id, $rating]);
        }
        // Refresh lại trang để cập nhật thông số
        header("Location: challenge.php?id=$chal_id&msg=rated");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($challenge['title']) ?> - Picodoc CTF</title>
    <link rel="stylesheet" href="stylelogin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="styleprofile.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #b2182b; }
        .meta { color: #6c757d; margin-bottom: 20px; font-size: 14px; }
        .meta span { background: #e9ecef; padding: 4px 10px; border-radius: 4px; margin-right: 10px; font-weight: 500; }
        .description { background: #f8f9fa; padding: 20px; border-radius: 8px; font-size: 16px; line-height: 1.6; margin-bottom: 25px; white-space: pre-wrap; border-left: 4px solid #b2182b; color: #333; }
        
        .flag-form { margin-bottom: 30px; }
        .flag-input { width: 100%; padding: 12px; border: 1px solid #ced4da; background: #fff; color: #333; border-radius: 4px; font-size: 16px; box-sizing: border-box; margin-bottom: 15px; font-family: monospace; }
        .btn { padding: 12px 20px; background: #b2182b; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; transition: background 0.2s; }
        .btn:hover { background: #8a1321; }
        
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; border: 1px solid transparent; }
        .alert-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        
        .hints { margin-top: 30px; }
        .hint-btn { background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-size: 14px; margin-bottom: 10px;}
        .hint-btn:hover { background: #5a6268; }
        .hint-content { display: none; background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; border-left: 4px solid #ffeeba; white-space: pre-wrap; line-height: 1.5; }
        
        .rating-section { margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; }
        .stars { display: inline-flex; flex-direction: row-reverse; }
        .stars input { display: none; }
        .stars label { font-size: 30px; color: #ccc; cursor: pointer; transition: color 0.2s; }
        .stars label:before { content: '★'; }
        .stars input:checked ~ label, .stars label:hover, .stars label:hover ~ label { color: #fcc419; }
        
        a.back { color: #007bff; text-decoration: none; display: inline-block; margin-bottom: 20px; font-weight: 500; }
        a.back:hover { color: #0056b3; text-decoration: underline; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <a href="donelogin.php" class="logo" style="text-decoration: none;">
                <svg viewBox="0 0 100 100" class="logo-icon" xmlns="http://www.w3.org/2000/svg">
                    <clipPath id="navbar-circle"><circle cx="50" cy="50" r="50" /></clipPath>
                    <g clip-path="url(#navbar-circle)">
                        <rect width="100" height="100" fill="#b196b6" />
                        <path d="M 45,50 L 100,105 L 105,100 Z" fill="#b2182b" stroke="#b2182b" stroke-width="20" />
                        <path d="M 38,30 h 12 c 8,0 14,5 14,14 c 0,9 -6,14 -14,14 h -4 v 18 h -8 Z M 46,38 v 12 h 3 c 4,0 6,-2 6,-6 c 0,-4 -2,-6 -6,-6 Z" fill="#ffffff" />
                    </g>
                </svg>
                <span class="logo-text">picoCTF</span>
            </a>
        </div>
        <div class="nav-menu">
            <ul class="nav-links">
                <li><a href="donelogin.php">CTF Main</a></li>
                <li><a href="dashboard.php" class="active">CTF Dashboard</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt"></i> Thoát</a></li>
            </ul>
        </div>
    </nav>

<div class="container">
    <a href="dashboard.php" class="back">← Quay lại Dashboard</a>
    
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'rated'): ?>
        <div class="alert alert-success">Đánh giá của bạn đã được ghi nhận. Cảm ơn nhé!</div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($is_solved && !$message): ?>
        <div class="alert alert-success">Bạn đã giải thành công bài này!</div>
    <?php endif; ?>

    <h1><?= htmlspecialchars($challenge['title']) ?></h1>
    
    <div class="meta">
        <span>Danh mục: <?= htmlspecialchars($challenge['category_name']) ?></span>
        <span>Điểm: <?= $challenge['points'] ?> pts</span>
        <span>Đánh giá: <?= $avg_rating ?> / 5 (<?= $total_votes ?> votes)</span>
    </div>

    <div class="description">
        <?= htmlspecialchars($challenge['description']) ?>
    </div>

    <form method="POST" class="flag-form">
        <input type="hidden" name="action" value="submit_flag">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="text" name="flag" class="flag-input" placeholder="picoCTF{...}" required <?= $is_solved ? 'disabled' : '' ?>>
        <?php if (!$is_solved): ?>
            <button type="submit" class="btn">Nộp Flag</button>
        <?php endif; ?>
    </form>

    <?php if (!empty($challenge['hints'])): ?>
    <div class="hints">
        <button class="hint-btn" onclick="toggleHint()">Hiển thị Gợi ý (Hints)</button>
        <div class="hint-content" id="hintBox">
            <?= htmlspecialchars($challenge['hints']) ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="rating-section">
        <h3>Đánh giá bài này</h3>
        <form method="POST">
            <input type="hidden" name="action" value="submit_rating">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="stars">
                <?php for($i=5; $i>=1; $i--): ?>
                    <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" <?= ($user_rating == $i) ? 'checked' : '' ?> onchange="this.form.submit()">
                    <label for="star<?= $i ?>" title="<?= $i ?> sao"></label>
                <?php endfor; ?>
            </div>
            <p style="font-size: 12px; color: #777;">(Hãy chọn số sao tương ứng)</p>
        </form>
    </div>
</div>

<script>
    function toggleHint() {
        var box = document.getElementById("hintBox");
        if (box.style.display === "block") {
            box.style.display = "none";
        } else {
            box.style.display = "block";
        }
    }
</script>
</body>
</html>
