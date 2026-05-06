<?php
session_start();
require_once 'config.php';

// Cần đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['user_id'] = $user['id'];
$user_id = $user['id'];

// Lấy danh sách các categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Lấy tất cả challenges cùng với trạng thái solve của user hiện tại
$stmt = $pdo->prepare("
    SELECT ch.*, c.name as category_name,
        (SELECT is_correct FROM submissions WHERE user_id = ? AND challenge_id = ch.id ORDER BY id DESC LIMIT 1) as solved
    FROM challenges ch
    LEFT JOIN categories c ON ch.category_id = c.id
    ORDER BY ch.points ASC
");
$stmt->execute([$user_id]);
$challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tổ chức lại mảng cho dễ hiển thị theo Category
$challenges_by_cat = [];
foreach ($categories as $cat) {
    $challenges_by_cat[$cat['id']] = [
        'name' => $cat['name'],
        'challenges' => []
    ];
}
$total_points = 0;
foreach ($challenges as $chal) {
    $cat_id = $chal['category_id'];
    if (isset($challenges_by_cat[$cat_id])) {
        $challenges_by_cat[$cat_id]['challenges'][] = $chal;
    }
    if ($chal['solved']) {
        $total_points += $chal['points'];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>CTF Dashboard</title>
    <link rel="stylesheet" href="stylelogin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="styleprofile.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2, h3 { margin: 0; }
        .cat-group { margin-bottom: 30px; }
        .cat-title { border-bottom: 2px solid #b2182b; padding-bottom: 10px; margin-bottom: 15px; font-size: 24px; color: #b2182b; }
        .chal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; }
        .chal-card { background: #fff; padding: 15px; border-radius: 8px; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; text-align: center; border-left: 5px solid #ccc; box-shadow: 0 2px 4px rgba(0,0,0,0.05); color: #333; }
        .chal-card:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.1); border-left-color: #b2182b; }
        .chal-card.solved { border-left-color: #28a745; background: #e9fce9; }
        .chal-points { font-size: 20px; font-weight: bold; color: #6c757d; margin-top: 10px; }
        .chal-card.solved .chal-points { color: #28a745; }
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
                <li><a href="#" class="active">CTF Dashboard</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt"></i> Thoát</a></li>
            </ul>
        </div>
    </nav>

<div class="container">
    <div class="page-header">
        <h1>Các bài luyện tập (CTF Dashboard)</h1>
        <div style="text-align: right;">
            <div style="font-size: 18px;">Điểm của bạn: <strong style="color: #28a745; font-size: 24px;"><?= $total_points ?></strong></div>
        </div>
    </div>

    <?php foreach ($challenges_by_cat as $cat_id => $group): ?>
        <?php if (count($group['challenges']) > 0): ?>
            <div class="cat-group">
                <h2 class="cat-title"><?= htmlspecialchars($group['name']) ?></h2>
                <div class="chal-grid">
                    <?php foreach ($group['challenges'] as $chal): ?>
                        <a href="challenge.php?id=<?= $chal['id'] ?>" style="text-decoration: none; color: inherit;">
                            <div class="chal-card <?= $chal['solved'] ? 'solved' : '' ?>">
                                <h3><?= htmlspecialchars($chal['title']) ?></h3>
                                <div class="chal-points"><?= $chal['points'] ?> pts</div>
                                <?php if ($chal['solved']): ?>
                                    <div style="margin-top:5px; color:#28a745; font-size:13px; font-weight:500;"><i class="fas fa-check-circle"></i> Hoàn thành</div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
</body>
</html>
