<?php
session_start();
require_once 'config.php';

// Kiểm tra quyền
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$user = $stmt->fetch();
if ($user['role'] !== 'admin') {
    die("Bạn không có quyền truy cập trang này.");
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bg_style'])) {
        $style = $_POST['bg_style'];
        $allowedStyles = ['cover', 'contain', '100% 100%'];
        if (in_array($style, $allowedStyles)) {
            file_put_contents('uploads/bg_style.txt', $style);
            $message = "Đã lưu tuỳ chọn hiển thị ảnh nền!";
        }
    }

    if (isset($_FILES['bg_image']) && $_FILES['bg_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['bg_image'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($file['type'], $allowedTypes)) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                if ($ext == 'jpeg') $ext = 'jpg';
                
                // Xóa file cũ
                if (file_exists('uploads/bg.jpg')) unlink('uploads/bg.jpg');
                if (file_exists('uploads/bg.png')) unlink('uploads/bg.png');
                if (file_exists('uploads/bg.gif')) unlink('uploads/bg.gif');
                
                $dest = 'uploads/bg.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $message = "Đã tải lên ảnh nền thành công và lưu tùy chọn!";
                } else {
                    $error = "Có lỗi xảy ra khi lưu file.";
                }
            } else {
                $error = "Chỉ chấp nhận file ảnh (JPG, PNG, GIF).";
            }
        } else {
            $error = "Lỗi tải lên: " . $file['error'];
        }
    }
}

$current_style = 'cover';
if (file_exists('uploads/bg_style.txt')) {
    $current_style = file_get_contents('uploads/bg_style.txt');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tải Ảnh Nền</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert-error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <h2>Tải Lên & Chỉnh Sửa Ảnh Nền</h2>
    <p><a href="donelogin.php">← Quay lại trang chính</a></p>
    <?php if ($message): ?>
        <div class="alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>1. Chọn ảnh mới (JPG, PNG, GIF) - Không bắt buộc:</label>
            <input type="file" name="bg_image" accept="image/jpeg, image/png, image/gif">
            <small style="color: #666; display: block; margin-top: 5px;">Bỏ trống nếu bạn chỉ muốn đổi chế độ hiển thị của ảnh hiện tại.</small>
        </div>
        <div class="form-group">
            <label>2. Chế độ hiển thị (Tỷ lệ ảnh):</label>
            <select name="bg_style">
                <option value="cover" <?= $current_style == 'cover' ? 'selected' : '' ?>>Cover (Cắt ảnh cho vừa kín màn hình - Khuyên dùng)</option>
                <option value="contain" <?= $current_style == 'contain' ? 'selected' : '' ?>>Contain (Giữ nguyên tỷ lệ, hiển thị toàn bộ ảnh)</option>
                <option value="100% 100%" <?= $current_style == '100% 100%' ? 'selected' : '' ?>>Fill (Kéo giãn ảnh lấp đầy khung hình, có thể méo ảnh)</option>
            </select>
        </div>
        <button type="submit" class="btn">Lưu thay đổi</button>
    </form>
    
    <?php
    $current_bg = '';
    if (file_exists('uploads/bg.jpg')) $current_bg = 'uploads/bg.jpg';
    elseif (file_exists('uploads/bg.png')) $current_bg = 'uploads/bg.png';
    elseif (file_exists('uploads/bg.gif')) $current_bg = 'uploads/bg.gif';
    
    if ($current_bg):
    ?>
    <div style="margin-top: 30px;">
        <h3>Bản xem trước:</h3>
        <div style="width: 100%; height: 300px; border: 1px solid #ccc; margin-top: 10px; border-radius: 4px; background-image: url('<?= $current_bg ?>?<?= time() ?>'); background-size: <?= $current_style ?>; background-position: center; background-repeat: no-repeat; background-color: #333;"></div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
