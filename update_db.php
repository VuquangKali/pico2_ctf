<?php
session_start();
require 'config.php';

// Kiểm tra quyền (chỉ admin mới được phép truy cập)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("Access denied. Vui lòng đăng nhập.");
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin') {
    die("Access denied. Bạn không có quyền Admin.");
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL, attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY (username))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS otp_attempts (id INT AUTO_INCREMENT PRIMARY KEY, identifier VARCHAR(100) NOT NULL, attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY (identifier))");
    
    // Convert existing ip_address column to username if it exists and username doesn't
    $res = $pdo->query("SHOW COLUMNS FROM login_attempts LIKE 'username'");
    if ($res->rowCount() == 0) {
        $pdo->exec("ALTER TABLE login_attempts ADD COLUMN username VARCHAR(50) NOT NULL");
        $pdo->exec("ALTER TABLE login_attempts DROP COLUMN ip_address");
        $pdo->exec("ALTER TABLE login_attempts ADD KEY (username)");
    }
    echo "DB Updated successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
