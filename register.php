<?php
session_set_cookie_params(['httponly' => true]); // Cấu hình cookie session ở chế độ HTTP Only để thiết lập bảo mật, chống XSS
session_start(); // Bắt đầu phiên session để được phép lưu trữ và lấy các biến như CSRF token hay thông tin login
// Gọi file cấu hình kết nối DB PDO
require 'config.php'; // Nhúng file cấu hình kết nối database vào kịch bản hiện tại cho phép truy vấn DB
require_once 'mailer.php'; // Nhúng thư viện gửi email

// Khởi tạo CSRF Token nếu chưa có
if (empty($_SESSION['csrf_token'])) { // Kiểm tra xem biến csrf_token trong session đã được tạo hay chưa
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Nếu chưa, tạo một chuỗi ngẫu nhiên 32 byte, mã hóa sang hex và gán vào session làm token
}

// Logic 1: Khóa cổng màn đăng ký nếu đã vào tài khoản
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { // Kiểm tra vòng lặp nếu người dùng đã đăng nhập theo session trước đó
    header("Location: donelogin.php"); // Mặc định chuyển hướng người dùng sang trang donelogin.php để ngăn vào lại trang đăng ký
    exit; // Dừng kịch bản ngay lập tức tránh cho code dưới được chạy
}

$error = ''; // Khởi tạo biến lưu trữ thông báo lỗi thao tác dưới dạng chuỗi rỗng
$success = ''; // Khởi tạo biến lưu trữ thông báo nếu có gì đó thành công dưới dạng chuỗi rỗng

if ($_SERVER["REQUEST_METHOD"] == "POST") { // Kiểm tra sự kiện người dùng tải dữ liệu bằng phương thức POST (ấn nút submit)
    // Xác thực CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { // Kiểm tra tính hợp lệ của csrf token gửi lên so với trên session
        die("Hệ thống phát hiện có dấu hiệu giả mạo request (CSRF)."); // Dừng kịch bản ngay lập tức và in thông báo lỗi nếu như token sai
    }

    $username = trim($_POST['username'] ?? ''); // Lấy dữ liệu tên người dùng từ POST gửi lên và thực hiện cắt bỏ khoảng trống 2 đầu
    $email = trim($_POST['email'] ?? ''); // Lấy email người dùng
    $full_name = trim($_POST['full_name'] ?? ''); // Lấy họ tên người dùng
    $password = $_POST['password'] ?? ''; // Lấy dữ liệu mật khẩu mà người dung đã nhập từ POST
    $confirm_password = $_POST['confirm_password'] ?? ''; // Lấy dữ liệu xác nhận mật khẩu từ POST để kiểm chứng lại

    // Rà các trường hợp gõ linh tinh:)))
    if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
        $error = "Vui lòng điền đầy đủ thông tin!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Định dạng email không hợp lệ!";
    } elseif ($password !== $confirm_password) {
        $error = "Mật khẩu nhập lại không khớp!";
    } elseif (strlen($password) < 8) {
        $error = "Mật khẩu phải có ít nhất 8 ký tự!";
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[@!#%^&*()_+-=$]/', $password)) {
        $error = "Mật khẩu phải bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt (@!#%^&*()_+-=$)!";
    } else {
        // Kiểm tra xem user hoặc email này đã tồn tại chưa
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = :username OR email = :email");
        $stmt->execute(['username' => $username, 'email' => $email]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            if ($existingUser['username'] === $username) {
                $error = "Tên tài khoản này đã có người sử dụng!";
            } else {
                $error = "Email này đã được sử dụng!";
            }
        } else {
            // INSERT dữ liệu an toàn bằng BCRYPT
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name) VALUES (:username, :email, :password, :full_name)");
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'full_name' => $full_name
            ]);

            // Gửi email chào mừng sau khi đăng ký
            $subject = "Chào mừng con vợ đến với nền tảng PicoCTF!";
            $body = "<h2>Chào " . htmlspecialchars($username) . ",</h2><p>Đăng ký tài khoản thành công! Cùng bắt đầu thử sức với các thử thách ngay thôi.</p>";
            sendMail($email, $subject, $body);

            // Cập nhật thông báo thành công và không chuyển hướng
            $success = "Đăng ký thành công! Vui lòng kiểm tra email của bạn để xem lời chào mừng.";
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
            background-color: #6366f1;
            /* Đổi màu nút đăng ký cho khác login */
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
            color: #b2182b;
            font-size: 0.95em;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 500;
        }

        .msg-success {
            color: #28a745;
            font-size: 0.95em;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 500;
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
                <li><a href="#">Learn <i class="fas fa-caret-down" style="font-size: 0.8em; margin-left: 4px;"></i></a>
                </li>
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


                <?php if ($success): ?>
                    <div class="msg-success" style="font-size: 1.2rem; margin-bottom: 20px;"><i
                            class="fas fa-check-circle"></i> <?php echo $success; ?></div>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="login.php" class="btn-register"
                            style="display: inline-block; text-decoration: none; padding: 12px 20px; box-sizing: border-box;">Đăng
                            Nhập Ngay</a>
                    </div>
                <?php else: ?>
                    <div class="input-group">
                        <i class="far fa-user input-icon"></i>
                        <input type="text" name="username" placeholder="Tên Đăng Nhập" class="form-control" required
                            value="<?php echo htmlspecialchars($username ?? ''); ?>">
                    </div>

                    <div class="input-group">
                        <i class="far fa-id-card input-icon"></i>
                        <input type="text" name="full_name" placeholder="Họ và Tên" class="form-control" required
                            value="<?php echo htmlspecialchars($full_name ?? ''); ?>">
                    </div>

                    <div class="input-group">
                        <i class="far fa-envelope input-icon"></i>
                        <input type="email" name="email" placeholder="Email (ví dụ: name@example.com)" class="form-control"
                            required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>

                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" placeholder="Mật Khẩu" class="form-control" required>
                    </div>

                    <div class="input-group">
                        <i class="fas fa-key input-icon"></i>
                        <input type="password" name="confirm_password" placeholder="Nhập Lại Mật Khẩu" class="form-control"
                            required>
                    </div>

                    <!-- Hiện thông báo PHP nếu có lỗi -->
                    <?php if ($error): ?>
                        <div class="msg-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
                    <?php endif; ?>

                    <button type="submit" class="btn-register">Create Account</button>

                    <div class="card-footer" style="justify-content: center;">
                        <span style="color: #666; font-size: 0.9em;">Đã có tài khoản? </span>
                        <a href="login.php" class="footer-link" style="margin-left: 5px;"> Đăng nhập ngay</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </main>

</body>

</html>