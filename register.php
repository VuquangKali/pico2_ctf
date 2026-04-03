<?php
session_set_cookie_params(['httponly' => true]);
session_start();
// Gọi file cấu hình kết nối DB PDO
require 'config.php';

// Khởi tạo CSRF Token nếu chưa có
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Logic 1: Khóa cổng màn đăng ký nếu đã vào tài khoản
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: donelogin.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Xác thực CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Hệ thống phát hiện có dấu hiệu giả mạo request (CSRF).");
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Rà các trường hợp gõ linh tinh:)))
    if (empty($username) || empty($password)) {
        $error = "Vui lòng điền đầy đủ thông tin!";
    } elseif ($password !== $confirm_password) {
        $error = "Mật khẩu nhập lại không khớp!";
    } else {
        // Kiểm tra xem user này đã tồn tại chưa
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        if ($stmt->fetch()) {
            $error = "Tên tài khoản này đã có người sửa dụng!";
        } else {
            // INSERT dữ liệu an toàn bằng Băm MD5 theo yêu cầu (Mentor approve)
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (:username, :password)");
            $stmt->execute([
                'username' => $username,
                'password' => md5($password)
            ]);
            // Logic 3: Chuyển thẳng về màn Login báo thành công để xóa form đăng ký 
            header("Location: login.php?reg=success");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>picoCTF - Đăng ký thành viên</title>
    <!-- Tái sử dụng CSS của login gốc -->
    <link rel="stylesheet" href="stylelogin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        .btn-register {
            width: 100%;
            padding: 12px;
            background-color: #6366f1; /* Đổi màu nút đăng ký cho khác login */
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-register:hover {
            background-color: #4f46e5;
        }
        .msg-error {
            color: #b2182b; font-size: 0.95em; margin-bottom: 15px; text-align: center; font-weight: 500;
        }
        .msg-success {
            color: #28a745; font-size: 0.95em; margin-bottom: 15px; text-align: center; font-weight: 500;
        }
    </style>
</head>

<body>

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
                <li><a href="#">Learn <i class="fas fa-caret-down" style="font-size: 0.8em; margin-left: 4px;"></i></a></li>
                <li><a href="#">Practice</a></li>
                <li><a href="#">Compete</a></li>
                <li><a href="#">Classrooms</a></li>
                <li><a href="login.php">Log In</a></li> <!-- Trở lại login -->
            </ul>
        </div>
    </nav>

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
                    <h1 class="card-title">Đăng Ký Thành Viên</h1>
                </div>
            </div>

            <form class="login-form" action="register.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="input-group">
                    <i class="far fa-user input-icon"></i>
                    <input type="text" name="username" placeholder="Tên Đăng Nhập" class="form-control" required value="<?php echo htmlspecialchars($username ?? ''); ?>">
                </div>

                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" placeholder="Mật Khẩu" class="form-control" required>
                </div>

                <div class="input-group">
                    <i class="fas fa-key input-icon"></i>
                    <input type="password" name="confirm_password" placeholder="Nhập Lại Mật Khẩu" class="form-control" required>
                </div>

                <!-- Hiện thông báo PHP nếu có lỗi hoặc thành công -->
                <?php if ($error): ?>
                    <div class="msg-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="msg-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
                <?php endif; ?>

                <button type="submit" class="btn-register">Create Account</button>

                <div class="card-footer" style="justify-content: center;">
                    <span style="color: #666; font-size: 0.9em;">Đã có tài khoản? </span>
                    <a href="login.php" class="footer-link" style="margin-left: 5px;"> Đăng nhập ngay</a>
                </div>
            </form>
        </div>
    </main>

</body>
</html>
