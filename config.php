<?php
$host = '127.0.0.1';
$db = 'picoctf_clone';
$user = 'root';
$pass = ''; // Mật khẩu root mặc định của XAMPP là rỗng

try {
    // Sử dụng PDO để kết nốivìan toàn và hỗ trợ Prepared Statements giúp chống SQL Injection.
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    //chế độ báo lỗi (Exception)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
}
?>
