<?php
session_start();
// Gọi file cấu hình kết nối DB PDO
require 'config.php';

// Logic 1: Nếu đã đăng nhập thì vào Dashboard, ko vào trang Login nữa
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: donelogin.php");
    exit;
}

$error = '';
$success = '';
$username = ''; // Giữ lại tên để người dùng 

// Xử lý thông báo chuyển hướng thành công từ Register chạy qua
if (isset($_GET['reg']) && $_GET['reg'] === 'success') {
    $success = "Tạo tài khoản thành công! Mời bạn đăng nhập.";
}

// Xử lý Form 
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['password_hash'] === md5($password)) {
        // Đăng nhập thành công
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $user['username'];
        header("Location: donelogin.php");
        exit;
    } else {
        // Đăng nhập thất bại: gán cảnh báo đỏ
        $error = "Tài khoản hoặc Mật khẩu không chính xác!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>picoCTF - Log In</title>
    <link rel="stylesheet" href="stylelogin.css">
    <!-- Font Awesome cho các icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        .msg-error {
            color: #b2182b; font-size: 0.95em; margin-bottom: 15px; text-align: center; font-weight: 500;
        }
        .msg-success {
            color: #28a745; font-size: 0.95em; margin-bottom: 15px; text-align: center; font-weight: 500;
        }
    </style>
</head>

<body>

    <!-- Thanh điều hướng:))), --> 
    <nav class="navbar">
        <div class="nav-brand">
            <div class="logo">
                <svg viewBox="0 0 100 100" class="logo-icon" xmlns="http://www.w3.org/2000/svg">
                    <clipPath id="navbar-circle">
                        <circle cx="50" cy="50" r="50" />
                    </clipPath>
                    <g clip-path="url(#navbar-circle)">
                        <rect width="100" height="100" fill="#b196b6" />
                        <path d="M 45,50 L 100,105 L 105,100 Z" fill="#b2182b" stroke="#b2182b" stroke-width="20" />
                        <path
                            d="M 38,30 h 12 c 8,0 14,5 14,14 c 0,9 -6,14 -14,14 h -4 v 18 h -8 Z M 46,38 v 12 h 3 c 4,0 6,-2 6,-6 c 0,-4 -2,-6 -6,-6 Z"
                            fill="#ffffff" />
                    </g>
                </svg>
                <span class="logo-text">picoCTF</span>
            </div>
        </div>

        <div class="nav-menu">
            <ul class="nav-links">
                <li><a href="#">Learn <i class="fas fa-caret-down" style="font-size: 0.8em; margin-left: 4px;"></i></a>
                </li>
                <li><a href="#">Practice</a></li>
                <li><a href="#">Compete</a></li>
                <li><a href="#">Classrooms</a></li>
                <li><a href="#" class="active">Log In</a></li>
            </ul>
        </div>
    </nav>

    <!-- Nội dung chính (Form Đăng nhập) -->
    <main class="main-content">
        <div class="login-card">
            <div class="card-header">
                <div class="logo-container-large">
                    <svg viewBox="0 0 100 100" class="logo-icon-large" xmlns="http://www.w3.org/2000/svg">
                        <clipPath id="card-circle">
                            <circle cx="50" cy="50" r="50" />
                        </clipPath>
                        <g clip-path="url(#card-circle)">
                            <rect width="100" height="100" fill="#b196b6" />
                            <path d="M 45,50 L 100,105 L 105,100 Z" fill="#b2182b" stroke="#b2182b" stroke-width="20" />
                            <path
                                d="M 38,30 h 12 c 8,0 14,5 14,14 c 0,9 -6,14 -14,14 h -4 v 18 h -8 Z M 46,38 v 12 h 3 c 4,0 6,-2 6,-6 c 0,-4 -2,-6 -6,-6 Z"
                                fill="#ffffff" />
                        </g>
                    </svg>
                    <h1 class="card-title">picoCTF</h1>
                </div>
            </div>

            <!-- Ở ĐÂY SỬ DỤNG LUÔN login.php LÀM NƠI ĐIỀU HƯỚNG ACTION SAU POST -->
            <form class="login-form" action="login.php" method="POST">
                
                <div class="input-group">
                    <i class="far fa-user input-icon"></i>
                    <!-- Biến <?php echo htmlspecialchars($username); ?> giúp NHỚ lại tên đã điền form trước đó -->
                    <input type="text" name="username" placeholder="Username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" required>
                </div>

                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <!-- Mật khẩu rỗng vì dĩ nhiên gõ sai thì phải tự gõ lại mật khẩu cho an toàn -->
                    <input type="password" name="password" placeholder="Password" class="form-control" required>
                </div>
                
                <!-- Hiển thị PHP động (T -->
                <?php if ($error): ?>
                    <div class="msg-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="msg-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
                <?php endif; ?>

                <button type="submit" class="btn-login">Login</button>

                <div class="card-footer">
                    <a href="register.php" class="footer-link">Sign Up</a>
                    <a href="#" class="footer-link">Forgot Password?</a>
                </div>
            </form>
        </div>
    </main>
</body>

</html>
