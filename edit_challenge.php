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
if ($user['role'] !== 'admin') {
    die("Bạn không có quyền truy cập trang này.");
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM challenges WHERE id = ?");
$stmt->execute([$id]);
$challenge = $stmt->fetch();

if (!$challenge) {
    die("Bài CTF không tồn tại.");
}

// Lấy danh sách Categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_id = trim($_POST['new_id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $points = trim($_POST['points'] ?? '');
    $flag = trim($_POST['flag'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $hints = trim($_POST['hints'] ?? '');

    if (empty($new_id) || empty($title) || empty($flag) || $category_id === '' || $points === '') {
        $error = "Vui lòng điền đầy đủ các trường bắt buộc!";
    } elseif (!is_numeric($new_id) || $new_id <= 0) {
        $error = "ID bài CTF phải là một số nguyên dương!";
    } elseif (!is_numeric($points) || $points < 0 || floor($points) != $points) {
        $error = "Điểm số phải là một số nguyên dương hợp lệ!";
    } else {
        $stmt_check_id = $pdo->prepare("SELECT id FROM challenges WHERE id = ? AND id != ?");
        $stmt_check_id->execute([$new_id, $id]);
        if ($stmt_check_id->rowCount() > 0) {
            $error = "ID bài CTF '$new_id' đã tồn tại. Vui lòng chọn ID khác!";
        } else {
            $stmt_check = $pdo->prepare("SELECT id FROM challenges WHERE LOWER(title) = LOWER(?) AND id != ?");
            $stmt_check->execute([$title, $id]);
            if ($stmt_check->rowCount() > 0) {
                $error = "Tên bài CTF '$title' đã tồn tại. Vui lòng chọn tên khác!";
            } else {
                try {
                    $pdo->beginTransaction();
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                    $stmt = $pdo->prepare("UPDATE challenges SET id=?, title=?, description=?, category_id=?, points=?, flag=?, hints=? WHERE id=?");
                    $stmt->execute([$new_id, $title, $description, $category_id, (int)$points, $flag, $hints, $id]);
                    
                    if ($new_id != $id) {
                        $pdo->prepare("UPDATE submissions SET challenge_id = ? WHERE challenge_id = ?")->execute([$new_id, $id]);
                        $pdo->prepare("UPDATE ratings SET challenge_id = ? WHERE challenge_id = ?")->execute([$new_id, $id]);
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                    $pdo->commit();
                    header("Location: admin.php?tab=challenge");
                    exit();
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Lỗi khi cập nhật bài CTF: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa Bài CTF</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .alert-error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <h2>Sửa Bài CTF</h2>
    <p><a href="admin.php?tab=challenge">← Quay lại</a></p>
    <?php if (!empty($error)): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>ID Bài CTF</label>
            <input type="number" name="new_id" value="<?= htmlspecialchars($challenge['id']) ?>" required>
        </div>
        <div class="form-group">
            <label>Tên bài</label>
            <input type="text" name="title" value="<?= htmlspecialchars($challenge['title']) ?>" required>
        </div>
        <div class="form-group">
            <label>Danh mục</label>
            <select name="category_id" required>
                <option value="">-- Chọn danh mục --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $challenge['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Điểm số (Points)</label>
            <input type="number" name="points" value="<?= $challenge['points'] ?>" required>
        </div>
        <div class="form-group">
            <label>Flag (đáp án)</label>
            <input type="text" name="flag" value="<?= htmlspecialchars($challenge['flag']) ?>" required>
        </div>
        <div class="form-group">
            <label>Mô tả Lab / Task</label>
            <textarea name="description" rows="4"><?= htmlspecialchars($challenge['description']) ?></textarea>
        </div>
        <div class="form-group">
            <label>Gợi ý (Hints)</label>
            <textarea name="hints" rows="2"><?= htmlspecialchars($challenge['hints']) ?></textarea>
        </div>
        <button type="submit" class="btn">Cập nhật</button>
    </form>
</div>
</body>
</html>
