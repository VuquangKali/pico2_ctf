<?php
session_start();
require 'config.php';
require 'mailer.php';

// Nếu đã đăng nhập thì về dashboard
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: donelogin.php");
    exit;
}

$step   = $_SESSION['fp_step'] ?? 1;  // 1=nhập email, 2=nhập OTP, 3=đặt mật khẩu mới:VV
$error  = '';
$success = '';


// Bước 1: Nhập email

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Địa chỉ email không hợp lệ.';
    } else {
        // Kiểm tra email tồn tại trong DB
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Trả về thông báo giống như thành công để tránh user enumeration
            $success = 'Nếu email tồn tại, mã OTP đã được gửi. Vui lòng kiểm tra hộp thư.';
        } else {
            // Rate limit OTP
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM otp_attempts WHERE identifier = :email AND attempt_time > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetchColumn() >= 3) {
                $error = 'Bạn đã yêu cầu gửi OTP quá nhiều lần. Vui lòng thử lại sau 2 phút.';
                $_SESSION['fp_step'] = 1;
            } else {
                $stmt = $pdo->prepare("INSERT INTO otp_attempts (identifier) VALUES (:email)");
                $stmt->execute(['email' => $email]);

                // Sinh OTP 8 ký tự
                $otp = generateStrongOTP();
                $_SESSION['fp_user_id'] = $user['id'];
                $_SESSION['fp_email']   = $email;
                $_SESSION['fp_otp']     = $otp;
                $_SESSION['fp_expires'] = time() + 300; // Hết hạn sau 5 phút
                $_SESSION['fp_step']    = 2;

            $emailBody = "
                <div style='font-family:Roboto,sans-serif;max-width:480px;margin:auto;background:#1a1a1a;color:#fff;border-radius:10px;padding:32px;'>
                    <h2 style='color:#b196b6;margin-top:0'>Đặt lại mật khẩu picoCTF</h2>
                    <p>Xin chào <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>
                    <p>Mã OTP để đặt lại mật khẩu của bạn:</p>
                    <div style='font-size:2.5rem;font-weight:700;letter-spacing:12px;color:#b2182b;text-align:center;padding:20px 0'>$otp</div>
                    <p style='color:#888;font-size:0.85rem'>Mã có hiệu lực trong <strong>5 phút</strong>. Nếu bạn không yêu cầu, hãy bỏ qua email này.</p>
                </div>";

            if (!sendMail($email, 'Đặt lại mật khẩu picoCTF', $emailBody)) {
                $error = 'Không thể gửi email. Vui lòng thử lại sau.';
                $_SESSION['fp_step'] = 1;
            } else {
                $step = 2;
            }
            } // End of rate limit check
        }
    }
}


// Bước 2: Xác thực OTP

elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $entered_otp = trim($_POST['otp'] ?? '');

    if (isset($_SESSION['fp_lockout']) && $_SESSION['fp_lockout'] > time()) {
        $error = 'Bạn đã nhập sai quá 5 lần. Vui lòng chờ 2 phút để thử lại.';
        $step = 2;
    } elseif (empty($_SESSION['fp_otp']) || empty($_SESSION['fp_expires'])) {
        $error = 'Phiên xác thực hết hạn. Vui lòng thử lại.';
        $_SESSION['fp_step'] = 1;
        $step = 1;
    } elseif (time() > $_SESSION['fp_expires']) {
        $error = 'Mã OTP đã hết hạn. Vui lòng yêu cầu lại.';
        unset($_SESSION['fp_otp'], $_SESSION['fp_expires']);
        $_SESSION['fp_step'] = 1;
        $step = 1;
    } elseif ($entered_otp !== $_SESSION['fp_otp']) {
        if (!isset($_SESSION['fp_failures'])) $_SESSION['fp_failures'] = 0;
        $_SESSION['fp_failures']++;
        if ($_SESSION['fp_failures'] >= 5) {
            $_SESSION['fp_lockout'] = time() + 120;
            $error = 'Bạn đã nhập sai quá 5 lần. Vui lòng chờ 2 phút để thử lại.';
        } else {
            $error = 'Mã OTP không đúng. Vui lòng kiểm tra lại.';
        }
        $step = 2;
    } else {
        // OTP đúng → bước 3
        unset($_SESSION['fp_otp'], $_SESSION['fp_failures'], $_SESSION['fp_lockout']); // Xóa OTP ngay sau khi dùng
        $_SESSION['fp_verified'] = true;
        $_SESSION['fp_step']     = 3;
        $step = 3;
    }
}


// Bước 3: Đặt mật khẩu mới

elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    if (empty($_SESSION['fp_verified']) || empty($_SESSION['fp_user_id'])) {
        $error = 'Phiên không hợp lệ. Vui lòng bắt đầu lại.';
        $_SESSION['fp_step'] = 1;
        $step = 1;
    } else {
        $new_pass     = $_POST['new_password']     ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (strlen($new_pass) < 8) {
            $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
            $step = 3;
        } elseif (!preg_match('/[A-Z]/', $new_pass)) {
            $error = 'Mật khẩu phải chứa ít nhất 1 chữ hoa.';
            $step = 3;
        } elseif (!preg_match('/[0-9]/', $new_pass)) {
            $error = 'Mật khẩu phải chứa ít nhất 1 chữ số.';
            $step = 3;
        } elseif ($new_pass !== $confirm_pass) {
            $error = 'Mật khẩu xác nhận không khớp.';
            $step = 3;
        } else {
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $stmt->execute(['hash' => $hash, 'id' => $_SESSION['fp_user_id']]);

            // Dọn dẹp toàn bộ session quên mật khẩu
            unset($_SESSION['fp_step'], $_SESSION['fp_user_id'], $_SESSION['fp_email'],
                  $_SESSION['fp_verified'], $_SESSION['fp_expires']);

            $success = 'Mật khẩu đã được đặt lại thành công! Bạn có thể đăng nhập ngay bây giờ.';
            $step = 1; // Về bước 1 với thông báo thành công
        }
    }
}

$step = $_SESSION['fp_step'] ?? $step;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>picoCTF - Quên mật khẩu</title>
    <meta name="description" content="Đặt lại mật khẩu tài khoản picoCTF qua email OTP.">
    <link rel="stylesheet" href="stylelogin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        .msg-error   { color: #b2182b; font-size: 0.95em; margin-bottom: 15px; text-align: center; font-weight: 500; }
        .msg-success { color: #28a745; font-size: 0.95em; margin-bottom: 15px; text-align: center; font-weight: 500; }

        /* Step indicator */
        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-bottom: 28px;
        }
        .step-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: #dcdcdc;
            transition: background 0.3s;
        }
        .step-dot.active { background: #5d68eb; }
        .step-dot.done   { background: #28a745; }

        .step-label {
            font-size: 0.88rem;
            color: #888;
            text-align: center;
            margin-bottom: 20px;
        }
        .otp-hint {
            font-size: 0.82rem;
            color: #888;
            text-align: center;
            margin-top: -10px;
        }
        .password-requirements {
            font-size: 0.8rem;
            color: #888;
            margin-top: -10px;
            padding-left: 5px;
        }
        .password-requirements li { margin-bottom: 2px; }
        .back-link {
            text-align: center;
            margin-top: 12px;
        }
        .back-link a {
            color: #5d68eb;
            font-size: 0.88rem;
            text-decoration: none;
        }
        .back-link a:hover { text-decoration: underline; }
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
                <li><a href="register.php">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <main class="main-content">
        <div class="login-card">
            <div class="card-header">
                <div class="logo-container-large">
                    <svg viewBox="0 0 100 100" class="logo-icon-large" xmlns="http://www.w3.org/2000/svg">
                        <clipPath id="card-circle"><circle cx="50" cy="50" r="50" /></clipPath>
                        <g clip-path="url(#card-circle)">
                            <rect width="100" height="100" fill="#b196b6" />
                            <path d="M 45,50 L 100,105 L 105,100 Z" fill="#b2182b" stroke="#b2182b" stroke-width="20" />
                            <path d="M 38,30 h 12 c 8,0 14,5 14,14 c 0,9 -6,14 -14,14 h -4 v 18 h -8 Z M 46,38 v 12 h 3 c 4,0 6,-2 6,-6 c 0,-4 -2,-6 -6,-6 Z" fill="#ffffff" />
                        </g>
                    </svg>
                    <h1 class="card-title">picoCTF</h1>
                </div>

                <!-- Step indicator dots -->
                <div class="step-indicator">
                    <div class="step-dot <?= ($step > 1) ? 'done' : ($step == 1 ? 'active' : '') ?>"></div>
                    <div class="step-dot <?= ($step > 2) ? 'done' : ($step == 2 ? 'active' : '') ?>"></div>
                    <div class="step-dot <?= ($step == 3 ? 'active' : '') ?>"></div>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="msg-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="msg-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <div class="back-link"><a href="login.php"><i class="fas fa-arrow-left"></i> Quay lại đăng nhập</a></div>

            <?php elseif ($step == 1): ?>
                <!-- BƯỚC 1: Nhập email -->
                <p class="step-label"><i class="far fa-envelope"></i> Nhập email để nhận mã OTP</p>
                <form class="login-form" action="forgot_password.php" method="POST">
                    <input type="hidden" name="action" value="send_otp">
                    <div class="input-group">
                        <i class="far fa-envelope input-icon"></i>
                        <input type="email" name="email" placeholder="Email đã đăng ký" class="form-control"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <button type="submit" class="btn-login">Gửi mã OTP</button>
                </form>
                <div class="back-link"><a href="login.php"><i class="fas fa-arrow-left"></i> Quay lại đăng nhập</a></div>

            <?php elseif ($step == 2): ?>
                <!-- BƯỚC 2: Nhập OTP -->
                <p class="step-label"><i class="fas fa-shield-alt"></i> Nhập mã OTP đã được gửi tới email của bạn</p>
                <form class="login-form" action="forgot_password.php" method="POST">
                    <input type="hidden" name="action" value="verify_otp">
                    <div class="input-group">
                        <i class="fas fa-key input-icon"></i>
                        <input type="text" name="otp" placeholder="Mã 8 ký tự" class="form-control"
                               maxlength="8" autocomplete="one-time-code" required>
                    </div>
                    <p class="otp-hint">Mã có hiệu lực trong 5 phút.</p>
                    <button type="submit" class="btn-login">Xác nhận OTP</button>
                </form>
                <div class="back-link">
                    <a href="forgot_password.php?reset=1"><i class="fas fa-redo"></i> Gửi lại mã mới</a>
                </div>

            <?php elseif ($step == 3): ?>
                <!-- BƯỚC 3: Đặt mật khẩu mới -->
                <p class="step-label"><i class="fas fa-lock"></i> Đặt mật khẩu mới cho tài khoản</p>
                <form class="login-form" action="forgot_password.php" method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="new_password" id="new_password" placeholder="Mật khẩu mới" class="form-control" required>
                    </div>
                    <ul class="password-requirements">
                        <li>Ít nhất 8 ký tự</li>
                        <li>Ít nhất 1 chữ hoa (A-Z)</li>
                        <li>Ít nhất 1 chữ số (0-9)</li>
                    </ul>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="confirm_password" placeholder="Xác nhận mật khẩu" class="form-control" required>
                    </div>
                    <button type="submit" class="btn-login">Đặt lại mật khẩu</button>
                </form>
            <?php endif; ?>

        </div>
    </main>

    <?php
    // Xử lý reset về bước 1 khi người dùng muốn gửi lại mã
    if (isset($_GET['reset'])) {
        unset($_SESSION['fp_step'], $_SESSION['fp_otp'], $_SESSION['fp_expires'],
              $_SESSION['fp_user_id'], $_SESSION['fp_email'], $_SESSION['fp_verified']);
        header('Location: forgot_password.php');
        exit;
    }
    ?>
</body>
</html>
