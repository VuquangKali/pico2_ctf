<?php
$host = '127.0.0.1'; // Khai báo địa chỉ IP của máy chủ cơ sở dữ liệu
$db = 'picoctf_clone'; // Khai báo tên cơ sở dữ liệu sẽ kết nối
$user = 'root'; // Khai báo tên người dùng cơ sở dữ liệu MySQL
$pass = ''; // Mật khẩu root mặc định của XAMPP là rỗng, khai báo mật khẩu

try {
    // Sử dụng PDO để kết nốivìan toàn và hỗ trợ Prepared Statements giúp chống SQL Injection.
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass); // Tạo kết nối PDO tới MySQL với các thông số cấu hình và charset utf8
    //chế độ báo lỗi (Exception)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Cài đặt thuộc tính PDO để ném ngoại lệ khi có lỗi xảy ra
} catch (PDOException $e) {
    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage()); // Bắt ngoại lệ và kết thúc kịch bản, in ra thông báo lỗi chi tiết
}
?>
