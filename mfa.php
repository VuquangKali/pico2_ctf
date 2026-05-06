<?php
session_start();
require 'config.php';
require 'mailer.php';

// Nếu chưa có session MFA thì không được vào
if (!isset($_SESSION['mfa_user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';

// Xử lý xác thực OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $code = trim($_POST['otp'] ?? '');

    if (isset($_SESSION['mfa_lockout']) && $_SESSION['mfa_lockout'] > time()) {
        $error = 'Bạn đã nhập sai quá 5 lần. Vui lòng chờ 2 phút để thử lại.';
    } elseif (empty($_SESSION['mfa_code']) || empty($_SESSION['mfa_expires'])) {
        $error = 'Phiên xác thực không hợp lệ. Vui lòng đăng nhập lại.';
    } elseif (strtotime($_SESSION['mfa_expires']) < time()) {
        $error = 'Mã OTP đã hết hạn. Vui lòng đăng nhập lại để nhận mã mới.';
        // Xóa session MFA để buộc đăng nhập lại
        unset($_SESSION['mfa_user_id'], $_SESSION['mfa_code'], $_SESSION['mfa_expires']);
    } elseif ($code !== $_SESSION['mfa_code']) {
        if (!isset($_SESSION['mfa_failures'])) $_SESSION['mfa_failures'] = 0;
        $_SESSION['mfa_failures']++;
        if ($_SESSION['mfa_failures'] >= 5) {
            $_SESSION['mfa_lockout'] = time() + 120;
            $error = 'Bạn đã nhập sai quá 5 lần. Vui lòng chờ 2 phút để thử lại.';
        } else {
            $error = 'Mã OTP không đúng. Vui lòng kiểm tra email của bạn.';
        }
    } else {
        // Xác thực thành công
        session_regenerate_id(true);
        $_SESSION['loggedin'] = true;
        $stmt = $pdo->prepare('SELECT username FROM users WHERE id = :id');
        $stmt->execute(['id' => $_SESSION['mfa_user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $_SESSION['mfa_user_id'];
        }
        // Dọn dẹp session MFA
        unset($_SESSION['mfa_user_id'], $_SESSION['mfa_code'], $_SESSION['mfa_expires'], $_SESSION['mfa_failures'], $_SESSION['mfa_lockout']);
        header('Location: donelogin.php');
        exit;
    }
}

// Xử lý gửi lại mã OTP
if (isset($_GET['resend']) && isset($_SESSION['mfa_user_id'])) {
    $stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['mfa_user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Kiểm tra Rate Limit trước khi cho phép gửi lại OTP
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM otp_attempts WHERE identifier = :email AND attempt_time > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
        $stmt->execute(['email' => $user['email']]);
        if ($stmt->fetchColumn() >= 3) {
            $error = 'Bạn đã yêu cầu gửi lại OTP quá nhiều lần. Vui lòng thử lại sau 2 phút.';
        } else {
            // Ghi nhận lần yêu cầu OTP
            $stmt = $pdo->prepare("INSERT INTO otp_attempts (identifier) VALUES (:email)");
            $stmt->execute(['email' => $user['email']]);

            $otp = generateStrongOTP();
            $_SESSION['mfa_code']    = $otp;
            $_SESSION['mfa_expires'] = date('Y-m-d H:i:s', time() + 300);

            $emailBody = "
                <div style='font-family:Roboto,sans-serif;max-width:480px;margin:auto;background:#1a1a1a;color:#fff;border-radius:10px;padding:32px;'>
                    <h2 style='color:#b196b6;margin-top:0'>Xác thực đăng nhập picoCTF</h2>
                    <p>Xin chào <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>
                    <p>Mã OTP mới của bạn:</p>
                    <div style='font-size:2.5rem;font-weight:700;letter-spacing:12px;color:#5d68eb;text-align:center;padding:20px 0'>$otp</div>
                    <p style='color:#888;font-size:0.85rem'>Mã có hiệu lực trong <strong>5 phút</strong>.</p>
                </div>";

            sendMail($user['email'], 'Mã xác thực mới - picoCTF', $emailBody);
            header('Location: mfa.php?sent=1');
            exit;
        }
    }
}

$sent = isset($_GET['sent']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>picoCTF - Xác thực MFA</title>
    <meta name="description" content="Nhập mã xác thực OTP để hoàn tất đăng nhập picoCTF.">
    <link rel="stylesheet" href="stylelogin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        .msg-error   { color: #b2182b; font-size: 0.95em; margin-bottom: 15px; text-align: center; font-weight: 500; }
        .msg-success { color: #28a745; font-size: 0.95em; margin-bottom: 15px; text-align: center; font-weight: 500; }

        .otp-description {
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .otp-input {
            text-align: center;
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: 10px;
            padding: 12px 15px !important;
        }
        .resend-link {
            text-align: center;
            margin-top: 12px;
        }
        .resend-link a {
            color: #5d68eb;
            font-size: 0.88rem;
            text-decoration: none;
        }
        .resend-link a:hover { text-decoration: underline; }

        .back-link {
            text-align: center;
            margin-top: 8px;
        }
        .back-link a {
            color: #888;
            font-size: 0.85rem;
            text-decoration: none;
        }
        .back-link a:hover { color: #fff; }

        .mfa-icon {
            font-size: 3rem;
            color: #5d68eb;
            text-align: center;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-brand">
            <div class="logo">
                <svg viewBox="0 0 100 100" class="logo-icon" xmlns="http://www.w3.org/2000/svg">
                    <clipPath id="navbar-circle"><circle cx="50" cy="50" r="50" /></clipPath>
                    <g clip-path="url(#navbar-circle)">
                        <rect width="100" height="100" fill="#b196b6" />
                        <path d="M 45,50 L 100,105 L 105,100 Z" fill="#b2182b" stroke="#b2182b" stroke-width="20" />
                        <path d="M 38,30 h 12 c 8,0 14,5 14,14 c 0,9 -6,14 -14,14 h -4 v 18 h -8 Z M 46,38 v 12 h 3 c 4,0 6,-2 6,-6 c 0,-4 -2,-6 -6,-6 Z" fill="#ffffff" />
                    </g>
                </svg>
                <span class="logo-text">picoCTF</span>
            </div>
        </div>
        <div class="nav-menu">
            <ul class="nav-links">
                <li><a href="login.php">Log In</a></li>
            </ul>
        </div>
    </nav>

    <main class="main-content">
        <div class="login-card">
            <div class="card-header">
                <div class="mfa-icon"><i class="fas fa-shield-alt"></i></div>
                <h1 class="card-title" style="font-size:1.6rem;">Xác thực 2 bước</h1>
            </div>

            <p class="otp-description">
                Mã OTP đã được gửi đến email của bạn.<br>
                Vui lòng nhập mã 8 chữ số để tiếp tục.
            </p>

            <?php if ($error): ?>
                <div class="msg-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($sent): ?>
                <div class="msg-success"><i class="fas fa-check-circle"></i> Mã OTP mới đã được gửi!</div>
            <?php endif; ?>

            <form class="login-form" method="POST" action="mfa.php">
                <div class="input-group">
                    <i class="fas fa-key input-icon"></i>
                    <input type="text" name="otp" placeholder="_ _ _ _ _ _ _ _"
                           class="form-control otp-input"
                           maxlength="8"
                           autocomplete="one-time-code"
                           autofocus required>
                </div>
                <button type="submit" class="btn-login">
                    <i class="fas fa-check-circle"></i> Xác nhận
                </button>
            </form>

            <div class="resend-link">
                <a href="mfa.php?resend=1"><i class="fas fa-redo"></i> Gửi lại mã OTP</a>
            </div>
            <div class="back-link">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Quay lại đăng nhập</a>
            </div>
        </div>
    </main>

    <script>
        // JS helper if you want to force uppercase or similar (removed numeric restriction)
    </script>
</body>
</html>
