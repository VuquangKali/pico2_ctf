<?php
// Tải cấu hình từ file .env
if (file_exists(__DIR__ . '/.env')) {
    $env_vars = parse_ini_file(__DIR__ . '/.env');
    foreach ($env_vars as $key => $value) {
        $_ENV[$key] = $value;
    }
}

$host = '127.0.0.1'; // Khai báo địa chỉ IP của máy chủ cơ sở dữ liệu
$db = 'picoctf_clone'; // Khai báo tên cơ sở dữ liệu sẽ kết nối
$user = 'root'; // Khai báo tên người dùng cơ sở dữ liệu MySQL
$pass = $_ENV['DB_PASS'] ?? ''; // Sử dụng mật khẩu DB từ file .env (rỗng nếu không có)

try {
    // Sử dụng PDO để kết nốivìan toàn và hỗ trợ Prepared Statements giúp chống SQL Injection.
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass); // Tạo kết nối PDO tới MySQL với các thông số cấu hình và charset utf8
    //chế độ báo lỗi (Exception)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Cài đặt thuộc tính PDO để ném ngoại lệ khi có lỗi xảy ra
    
    // Tự động cập nhật cấu trúc DB cho các tính năng mới
    $pdo->exec("CREATE TABLE IF NOT EXISTS otp_attempts (id INT AUTO_INCREMENT PRIMARY KEY, identifier VARCHAR(100) NOT NULL, attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY (identifier))");
    
    // Auto upgrade legacy login_attempts
    $res = $pdo->query("SHOW COLUMNS FROM login_attempts LIKE 'username'");
    if ($res->rowCount() == 0) {
        $pdo->exec("ALTER TABLE login_attempts CHANGE ip_address username VARCHAR(50) NOT NULL");
    }

    // Force username to be case-sensitive (utf8mb4_bin) to allow Quanganh and quanganh distinct
    $pdo->exec("ALTER TABLE users MODIFY username VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL");
    
    // Thêm cột role cho Admin
    $res = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($res->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('normal user', 'admin') DEFAULT 'normal user'");
    } else {
        // Cập nhật lại ENUM nếu user muốn đổi tên thành normal user
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'normal user', 'admin') DEFAULT 'normal user'");
        $pdo->exec("UPDATE users SET role='normal user' WHERE role='user' OR role IS NULL");
    }
    // Cấp quyền Admin cho tài khoản mặc định và tài khoản Gmail của bạn
    $pdo->exec("UPDATE users SET role='admin' WHERE username='admin' OR email LIKE 'vuquang30102003@%'");

    // Các bảng CTF
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        category_id INT NOT NULL,
        points INT DEFAULT 0,
        flag VARCHAR(255) NOT NULL,
        hints TEXT,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        challenge_id INT NOT NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_correct BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        challenge_id INT NOT NULL,
        rating INT NOT NULL CHECK(rating >= 1 AND rating <= 5),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, challenge_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {
    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage()); // Bắt ngoại lệ và kết thúc kịch bản, in ra thông báo lỗi chi tiết
}
?>
