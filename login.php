<?php
session_start(); // Khởi động session để bắt đầu phiên làm việc và lưu trữ thông tin trạng thái người dùng
// Gọi file cấu hình kết nối DB PDO
require 'config.php'; // Nhúng file config.php vào để sử dụng kết nối cơ sở dữ liệu đã thiết lập sẵn
require 'mailer.php'; // Nhúng mailer để gửi OTP qua email

// Logic 1: Nếu đã đăng nhập thì vào Dashboard, ko vào trang Login nữa
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: donelogin.php");
    exit;
}

$error = '';
$success = '';
$username = '';

// Xử lý Form 
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $username = trim($_POST['username'] ?? '');
    
    // Check rate limit by username
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE username = :username AND attempt_time > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
    $stmt->execute(['username' => $username]);
    if ($stmt->fetchColumn() > 5) {
        $error = "Tài khoản này đã bị nhập sai quá nhiều lần. Vui lòng thử lại sau 2 phút.";
    }

    if (empty($error)) {
        $password = $_POST['password'] ?? '';

        // Binary check for case-insensitive collation, forces exact case match for username
        $stmt = $pdo->prepare("SELECT * FROM users WHERE BINARY username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $email = $user['email'];
            
            // Check OTP Request Rate Limit even if login is successful
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM otp_attempts WHERE identifier = :email AND attempt_time > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetchColumn() >= 3) {
                // Return an error and don't proceed to MFA
                $error = "Bạn đã yêu cầu gửi OTP đăng nhập quá nhiều lần. Vui lòng thử lại sau 2 phút.";
            } else {
                // Log the OTP attempt
                $stmt = $pdo->prepare("INSERT INTO otp_attempts (identifier) VALUES (:email)");
                $stmt->execute(['email' => $email]);

                // Xóa lịch sử đăng nhập sai của tài khoản này
                $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE username = :username");
                $stmt->execute(['username' => $username]);

                // Sinh OTP 8 ký tự và lưu vào session tạm
                $otp = generateStrongOTP();
                $_SESSION['mfa_user_id'] = $user['id'];
                $_SESSION['mfa_code'] = $otp;
                $_SESSION['mfa_expires'] = date('Y-m-d H:i:s', time() + 300); // Hết hạn sau 5 phút

            // Gửi OTP tới email của user
            $emailBody = "
                <div style='font-family:Roboto,sans-serif;max-width:480px;margin:auto;background:#1a1a1a;color:#fff;border-radius:10px;padding:32px;'>
                    <h2 style='color:#b196b6;margin-top:0'>Xác thực đăng nhập picoCTF cho con vợ <strong>" . htmlspecialchars($user['username']) . "</strong>,</h2>
                    <p>Xin chào <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>
                    <p>Mã OTP của bạn là:</p>
                    <div style='font-size:2.5rem;font-weight:700;letter-spacing:12px;color:#5d68eb;text-align:center;padding:20px 0'>$otp</div>
                    <p style='color:#888;font-size:0.85rem'>Mã có hiệu lực trong <strong>5 phút</strong>. Không chia sẻ mã này cho bất kỳ ai.</p>
                </div>";

            if (!sendMail($user['email'], 'Mã xác thực đăng nhập picoCTF', $emailBody)) {
                $error = 'Không thể gửi email OTP. Vui lòng thử lại.';
            } else {
                header('Location: mfa.php');
                exit;
            }
            }
        } else {
            // Log failed attempt by username
            if (!empty($username)) {
                $stmt = $pdo->prepare("INSERT INTO login_attempts (username) VALUES (:username)");
                $stmt->execute(['username' => $username]);
            }

            $error = "Tài khoản hoặc Mật khẩu không chính xác!";
        }
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
                    <input type="text" name="username" placeholder="Username" class="form-control"
                        value="<?php echo htmlspecialchars($username); ?>" required>
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
                    <a href="forgot_password.php" class="footer-link">Forgot Password?</a>
                </div>
            </form>
        </div>
    </main>
</body>

</html>