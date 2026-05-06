<?php
session_start();
require_once 'config.php';

// Kiểm tra quyền
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT id, role FROM users WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$user = $stmt->fetch();
$_SESSION['user_id'] = $user['id']; // Tự động vá lỗi thiếu session user_id cũ


if ($user['role'] !== 'admin') {
    die("Bạn không có quyền truy cập trang này.");
}

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

// Xử lý tạo Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $id = trim($_POST['id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Server-side validation
    if (empty($id) || empty($name)) {
        $error = "ID và Tên danh mục không được để trống!";
    } elseif (!is_numeric($id) || $id <= 0) {
        $error = "ID danh mục phải là một số nguyên dương!";
    } elseif (mb_strlen($name) > 100) {
        $error = "Tên danh mục không được vượt quá 100 ký tự!";
    } else {
        $stmt_check_id = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt_check_id->execute([$id]);
        if ($stmt_check_id->rowCount() > 0) {
            $error = "ID danh mục '$id' đã tồn tại. Vui lòng chọn ID khác!";
        } else {
            $stmt_check = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?)");
            $stmt_check->execute([$name]);
            if ($stmt_check->rowCount() > 0) {
                $error = "Tên danh mục '$name' đã tồn tại. Vui lòng chọn tên khác!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (id, name, description) VALUES (?, ?, ?)");
                $stmt->execute([$id, $name, $description]);
                $success = "Thêm danh mục thành công!";
            }
        }
    }
}


// Xử lý tạo Challenge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_challenge') {
    $id = trim($_POST['id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $points = trim($_POST['points'] ?? '');
    $flag = trim($_POST['flag'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $hints = trim($_POST['hints'] ?? '');

    // Server-side Validation
    if (empty($id) || empty($title) || empty($flag) || $category_id === '' || $points === '') {
        $error = "Vui lòng điền đầy đủ các trường bắt buộc (ID, Tên bài, Danh mục, Điểm số, Flag)!";
    } elseif (!is_numeric($id) || $id <= 0) {
        $error = "ID bài CTF phải là một số nguyên dương!";
    } elseif (mb_strlen($title) > 255) {
        $error = "Tên bài CTF không được vượt quá 255 ký tự!";
    } elseif (!is_numeric($points) || $points < 0 || floor($points) != $points) {
        $error = "Điểm số phải là một số nguyên dương hợp lệ!";
    } else {
        // Validation: Kiểm tra danh mục có tồn tại thực sự trong Database không
        $stmt_cat = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt_cat->execute([$category_id]);
        if ($stmt_cat->rowCount() == 0) {
            $error = "Danh mục bạn chọn không tồn tại trong hệ thống!";
        } else {
            $stmt_check_id = $pdo->prepare("SELECT id FROM challenges WHERE id = ?");
            $stmt_check_id->execute([$id]);
            if ($stmt_check_id->rowCount() > 0) {
                $error = "ID bài CTF '$id' đã tồn tại. Vui lòng chọn ID khác!";
            } else {
                $stmt_check = $pdo->prepare("SELECT id FROM challenges WHERE LOWER(title) = LOWER(?)");
                $stmt_check->execute([$title]);
                if ($stmt_check->rowCount() > 0) {
                    $error = "Tên bài CTF '$title' đã tồn tại. Vui lòng chọn tên khác!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO challenges (id, title, description, category_id, points, flag, hints) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id, $title, $description, $category_id, (int)$points, $flag, $hints]);
                    $success = "Thêm bài CTF thành công!";
                }
            }
        }
    }
}

// Xử lý xoá Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    $id = $_POST['id'];
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);

    $success = "Đã xoá danh mục!";
}

// Xử lý xoá Challenge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_challenge') {
    $id = $_POST['id'];
    $pdo->prepare("DELETE FROM challenges WHERE id = ?")->execute([$id]);

    $success = "Đã xoá bài CTF!";
}

// Lấy danh sách Categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách Challenges
$challenges = $pdo->query("SELECT ch.*, c.name as category_name FROM challenges ch LEFT JOIN categories c ON ch.category_id = c.id ORDER BY ch.id DESC")->fetchAll(PDO::FETCH_ASSOC);

$tab = $_GET['tab'] ?? 'challenge';

// Lấy danh sách người tham gia (nếu đang ở tab participants)
$participants = [];
if ($tab === 'participants') {
    $participants = $pdo->query("
        SELECT u.id, u.username, 
               COALESCE(SUM(ch.points), 0) as total_points,
               GROUP_CONCAT(ch.title SEPARATOR ', ') as solved_challenges
        FROM users u
        JOIN (
            SELECT DISTINCT user_id, challenge_id 
            FROM submissions 
            WHERE is_correct = TRUE
        ) s ON u.id = s.user_id
        JOIN challenges ch ON s.challenge_id = ch.id
        GROUP BY u.id, u.username
        ORDER BY total_points DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: auto; }
        h1, h2 { color: #333; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background-color: #f8f9fa; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert-error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Quản trị viên - Picodoc CTF</h1>
    <p>
        <a href="profile.php" style="margin-right: 10px; color: #007bff; text-decoration: none;">Quay lại Profile</a> | 
        <a href="dashboard.php" style="margin: 0 10px; color: #007bff; text-decoration: none;">Xem giao diện User</a> |
        <a href="admin.php?tab=challenge" style="margin: 0 10px; text-decoration: none; <?= $tab === 'challenge' ? 'font-weight:bold; color:#0056b3; border-bottom: 2px solid #0056b3;' : 'color:#007bff;' ?>">Thêm Bài CTF</a> | 
        <a href="admin.php?tab=category" style="margin: 0 10px; text-decoration: none; <?= $tab === 'category' ? 'font-weight:bold; color:#0056b3; border-bottom: 2px solid #0056b3;' : 'color:#007bff;' ?>">Thêm Danh Mục</a> |
        <a href="admin.php?tab=participants" style="margin-left: 10px; text-decoration: none; <?= $tab === 'participants' ? 'font-weight:bold; color:#0056b3; border-bottom: 2px solid #0056b3;' : 'color:#007bff;' ?>">Người Tham Gia</a>
    </p>

    <?php if (!empty($success)): ?>
        <div class="alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($tab === 'category'): ?>

    <div class="card">
        <h2>Thêm Danh Mục (Category)</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_category">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="form-group">
                <label>ID Danh mục</label>
                <input type="number" name="id" required>
            </div>
            <div class="form-group">
                <label>Tên danh mục</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Mô tả</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            <button type="submit" class="btn">Thêm Danh Mục</button>
        </form>
        
        <table style="margin-top:20px;">
            <tr><th style="width: 50px; text-align: center;">STT</th><th>ID</th><th>Tên</th><th>Mô tả</th><th style="width: 140px; text-align: center;">Hành động</th></tr>
            <?php $stt_cat = 1; foreach ($categories as $cat): ?>
            <tr>
                <td style="text-align: center;"><?= $stt_cat++ ?></td>
                <td><?= $cat['id'] ?></td>
                <td><?= htmlspecialchars($cat['name']) ?></td>
                <td><?= htmlspecialchars($cat['description']) ?></td>
                <td style="text-align: center; white-space: nowrap;">
                    <a href="edit_category.php?id=<?= $cat['id'] ?>" class="btn" style="background:#ffc107; color:#212529; padding:5px 10px; text-decoration:none; margin-right:5px;">Sửa</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc xoá danh mục này? Mọi bài CTF thuộc danh mục sẽ bị xoá theo!');">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="btn" style="background:#dc3545; padding:5px 10px;">Xoá</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'challenge'): ?>
    <div class="card">
        <h2>Thêm Bài CTF (Challenge)</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_challenge">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="form-group">
                <label>ID Bài CTF</label>
                <input type="number" name="id" required>
            </div>
            <div class="form-group">
                <label>Tên bài</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>Danh mục</label>
                <select name="category_id" required>
                    <option value="">-- Chọn danh mục --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Điểm số (Points)</label>
                <input type="number" name="points" value="100" required>
            </div>
            <div class="form-group">
                <label>Flag (đáp án)</label>
                <input type="text" name="flag" placeholder="picoCTF{...}" required>
            </div>
            <div class="form-group">
                <label>Mô tả Lab / Task</label>
                <textarea name="description" rows="4"></textarea>
            </div>
            <div class="form-group">
                <label>Gợi ý (Hints)</label>
                <textarea name="hints" rows="2" placeholder="Gợi ý 1, Gợi ý 2..."></textarea>
            </div>
            <button type="submit" class="btn">Thêm Bài CTF</button>
        </form>

        <table style="margin-top:20px;">
            <tr><th style="width: 50px; text-align: center;">STT</th><th>ID</th><th>Tên bài</th><th>Category</th><th>Points</th><th>Flag</th><th style="width: 140px; text-align: center;">Hành động</th></tr>
            <?php $stt_chal = 1; foreach ($challenges as $chal): ?>
            <tr>
                <td style="text-align: center;"><?= $stt_chal++ ?></td>
                <td><?= $chal['id'] ?></td>
                <td><?= htmlspecialchars($chal['title']) ?></td>
                <td><?= htmlspecialchars($chal['category_name']) ?></td>
                <td><?= $chal['points'] ?></td>
                <td><?= htmlspecialchars($chal['flag']) ?></td>
                <td style="text-align: center; white-space: nowrap;">
                    <a href="edit_challenge.php?id=<?= $chal['id'] ?>" class="btn" style="background:#ffc107; color:#212529; padding:5px 10px; text-decoration:none; margin-right:5px;">Sửa</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xoá bài CTF này?');">
                        <input type="hidden" name="action" value="delete_challenge">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="id" value="<?= $chal['id'] ?>">
                        <button type="submit" class="btn" style="background:#dc3545; padding:5px 10px;">Xoá</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'participants'): ?>
    <div class="card">
        <h2>Danh sách người tham gia</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Người dùng</th>
                <th>Những bài đã làm</th>
                <th>Số điểm</th>
            </tr>
            <?php if (empty($participants)): ?>
            <tr>
                <td colspan="4" style="text-align: center;">Chưa có người dùng nào làm bài.</td>
            </tr>
            <?php else: ?>
                <?php foreach ($participants as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= htmlspecialchars($p['username']) ?></td>
                    <td><?= htmlspecialchars($p['solved_challenges']) ?></td>
                    <td><?= $p['total_points'] ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
