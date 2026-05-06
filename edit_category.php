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
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    die("Danh mục không tồn tại.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_id = trim($_POST['new_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($new_id) || empty($name)) {
        $error = "ID và Tên danh mục không được để trống!";
    } elseif (!is_numeric($new_id) || $new_id <= 0) {
        $error = "ID danh mục phải là một số nguyên dương!";
    } elseif (mb_strlen($name) > 100) {
        $error = "Tên danh mục không được vượt quá 100 ký tự!";
    } else {
        $stmt_check_id = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND id != ?");
        $stmt_check_id->execute([$new_id, $id]);
        if ($stmt_check_id->rowCount() > 0) {
            $error = "ID danh mục '$new_id' đã tồn tại. Vui lòng chọn ID khác!";
        } else {
            $stmt_check = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) AND id != ?");
            $stmt_check->execute([$name, $id]);
            if ($stmt_check->rowCount() > 0) {
                $error = "Tên danh mục '$name' đã tồn tại. Vui lòng chọn tên khác!";
            } else {
                try {
                    $pdo->beginTransaction();
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                    $stmt = $pdo->prepare("UPDATE categories SET id = ?, name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$new_id, $name, $description, $id]);
                    
                    if ($new_id != $id) {
                        $stmt_update_chal = $pdo->prepare("UPDATE challenges SET category_id = ? WHERE category_id = ?");
                        $stmt_update_chal->execute([$new_id, $id]);
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                    $pdo->commit();
                    header("Location: admin.php?tab=category");
                    exit();
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Lỗi khi cập nhật danh mục: " . $e->getMessage();
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
    <title>Sửa Danh Mục</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .alert-error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <h2>Sửa Danh Mục</h2>
    <p><a href="admin.php?tab=category">← Quay lại</a></p>
    <?php if (!empty($error)): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>ID Danh mục</label>
            <input type="number" name="new_id" value="<?= htmlspecialchars($category['id']) ?>" required>
        </div>
        <div class="form-group">
            <label>Tên danh mục</label>
            <input type="text" name="name" value="<?= htmlspecialchars($category['name']) ?>" required>
        </div>
        <div class="form-group">
            <label>Mô tả</label>
            <textarea name="description" rows="3"><?= htmlspecialchars($category['description']) ?></textarea>
        </div>
        <button type="submit" class="btn">Cập nhật</button>
    </form>
</div>
</body>
</html>
