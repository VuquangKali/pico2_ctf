<?php
session_set_cookie_params(['httponly' => true]);
session_start();
require 'config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$username   = $_SESSION['username'];
$error      = '';
$success    = '';
$pw_error   = '';
$pw_success = '';

// Tạo CSRF token nếu chưa có
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Lấy thông tin hiện tại từ DB
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
$stmt->execute(['username' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Không tìm thấy người dùng.");
}

// ==============================
// Xử lý cập nhật thông tin cá nhân
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_info') {
    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Yêu cầu không hợp lệ (CSRF).');
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Định dạng email không hợp lệ.';
    } elseif ($email !== $user['email']) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
        $stmt->execute(['email' => $email, 'id' => $user['id']]);
        if ($stmt->fetch()) {
            $error = 'Email này đã được sử dụng bởi tài khoản khác.';
        }
    }

    // Xử lý upload Avatar
    $avatar_name = $user['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0 && empty($error)) {
        $allowed  = ['jpg', 'jpeg', 'png', 'gif'];
        $ext      = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF).';
        } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            $error = 'Dung lượng ảnh không được vượt quá 2MB.';
        } else {
            $avatar_name = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
            $upload_path = 'uploads/avatars/' . $avatar_name;
            if (!is_dir('uploads/avatars')) {
                mkdir('uploads/avatars', 0755, true);
            }
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                if ($user['avatar'] !== 'default_avatar.png' && file_exists('uploads/avatars/' . $user['avatar'])) {
                    unlink('uploads/avatars/' . $user['avatar']);
                }
            } else {
                $error = 'Lỗi khi tải ảnh lên.';
                $avatar_name = $user['avatar'];
            }
        }
    }

    if (empty($error)) {
        $stmt = $pdo->prepare('UPDATE users SET full_name=:full_name, phone=:phone, email=:email, avatar=:avatar WHERE id=:id');
        $stmt->execute([
            'full_name' => $full_name,
            'phone'     => $phone,
            'email'     => $email,
            'avatar'    => $avatar_name,
            'id'        => $user['id']
        ]);
        $success = 'Cập nhật thông tin thành công!';
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// ==============================
// Xử lý đổi mật khẩu
// ==============================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Yêu cầu không hợp lệ (CSRF).');
    }

    $current_pass = $_POST['current_password'] ?? '';
    $new_pass     = $_POST['new_password']     ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (!password_verify($current_pass, $user['password_hash'])) {
        $pw_error = 'Mật khẩu hiện tại không chính xác.';
    } elseif (strlen($new_pass) < 8) {
        $pw_error = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    } elseif (!preg_match('/[A-Z]/', $new_pass)) {
        $pw_error = 'Mật khẩu mới phải chứa ít nhất 1 chữ hoa.';
    } elseif (!preg_match('/[0-9]/', $new_pass)) {
        $pw_error = 'Mật khẩu mới phải chứa ít nhất 1 chữ số.';
    } elseif ($new_pass !== $confirm_pass) {
        $pw_error = 'Mật khẩu xác nhận không khớp.';
    } elseif (password_verify($new_pass, $user['password_hash'])) {
        $pw_error = 'Mật khẩu mới không được trùng với mật khẩu cũ.';
    } else {
        $hash = password_hash($new_pass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $user['id']]);
        $pw_success = 'Mật khẩu đã được thay đổi thành công!';
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>picoCTF - Thông tin cá nhân</title>
    <meta name="description" content="Quản lý thông tin cá nhân và mật khẩu tài khoản picoCTF.">
    <link rel="stylesheet" href="stylelogin.css">
    <link rel="stylesheet" href="styleprofile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Password input wrapper */
        .pw-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .pw-input-wrap .form-control {
            padding-right: 44px;
        }
        .pw-toggle {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: #888;
            cursor: pointer;
            font-size: 0.95rem;
            padding: 4px;
            transition: color 0.2s;
        }
        .pw-toggle:hover { color: #b196b6; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <a href="donelogin.php" class="logo" style="text-decoration: none;">
                <svg viewBox="0 0 100 100" class="logo-icon" xmlns="http://www.w3.org/2000/svg">
                    <clipPath id="navbar-circle"><circle cx="50" cy="50" r="50" /></clipPath>
                    <g clip-path="url(#navbar-circle)">
                        <rect width="100" height="100" fill="#b196b6" />
                        <path d="M 45,50 L 100,105 L 105,100 Z" fill="#b2182b" stroke="#b2182b" stroke-width="20" />
                        <path d="M 38,30 h 12 c 8,0 14,5 14,14 c 0,9 -6,14 -14,14 h -4 v 18 h -8 Z M 46,38 v 12 h 3 c 4,0 6,-2 6,-6 c 0,-4 -2,-6 -6,-6 Z" fill="#ffffff" />
                    </g>
                </svg>
                <span class="logo-text">picoCTF</span>
            </a>
        </div>
        <div class="nav-menu">
            <ul class="nav-links">
                <li><a href="donelogin.php">Classrooms</a></li>
                <li><a href="#" class="active">Profile</a></li>
                <li><a href="logout.php" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt"></i> Thoát</a></li>
            </ul>
        </div>
    </nav>

    <main class="main-content">
        <div class="profile-container">
            <div class="profile-card">

                <!-- ========================
                     Form 1: Thông tin cá nhân
                     ======================== -->
                <div class="card-header">
                    <h2>Thông tin cá nhân</h2>
                </div>

                <form action="profile.php" method="POST" enctype="multipart/form-data" id="form-info">
                    <input type="hidden" name="action" value="update_info">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="avatar-section">
                        <div class="avatar-preview">
                            <img src="<?= ($user['avatar'] === 'default_avatar.png') ? 'default_avatar.png' : 'uploads/avatars/' . htmlspecialchars($user['avatar']) ?>" alt="Avatar" id="avatarImg">
                            <label for="avatarInput" class="avatar-edit" title="Thay đổi ảnh đại diện">
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="file" name="avatar" id="avatarInput" hidden accept="image/jpeg,image/png,image/gif">
                        </div>
                        <p class="username-display">@<?= htmlspecialchars($user['username']) ?></p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Họ và tên</label>
                        <input type="text" name="full_name" class="form-control"
                               value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" placeholder="Họ và tên của bạn">
                    </div>

                    <div class="form-group">
                        <label><i class="far fa-envelope"></i> Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>" required placeholder="user@example.com">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Số điện thoại</label>
                        <input type="text" name="phone" id="phone" class="form-control"
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="09xx xxx xxx">
                    </div>

                    <button type="submit" class="btn-save">Lưu thay đổi</button>
                    <a href="donelogin.php" class="btn-back">Quay lại Dashboard</a>
                </form>

                <!-- Divider -->
                <hr style="border-color:#2d2d2d;margin:32px 0;">

                <!-- ========================
                     Form 2: Đổi mật khẩu
                     ======================== -->
                <div class="card-header" style="margin-bottom:20px;">
                    <h2 style="font-size:1.3rem;"><i class="fas fa-key" style="color:#b2182b;margin-right:8px;"></i>Đổi mật khẩu</h2>
                </div>

                <?php if ($pw_error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($pw_error) ?></div>
                <?php endif; ?>
                <?php if ($pw_success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($pw_success) ?></div>
                <?php endif; ?>

                <form action="profile.php" method="POST" id="form-password">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Mật khẩu hiện tại</label>
                        <div class="pw-input-wrap">
                            <input type="password" name="current_password" id="cur_pass"
                                   class="form-control" placeholder="Nhập mật khẩu hiện tại" required>
                            <button type="button" class="pw-toggle" onclick="togglePw('cur_pass',this)" title="Hiện/Ẩn mật khẩu">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Mật khẩu mới</label>
                        <div class="pw-input-wrap">
                            <input type="password" name="new_password" id="new_pass"
                                   class="form-control" placeholder="Mật khẩu mới (≥8 ký tự, 1 hoa, 1 số)" required>
                            <button type="button" class="pw-toggle" onclick="togglePw('new_pass',this)" title="Hiện/Ẩn mật khẩu">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Xác nhận mật khẩu mới</label>
                        <div class="pw-input-wrap">
                            <input type="password" name="confirm_password" id="confirm_pass"
                                   class="form-control" placeholder="Nhập lại mật khẩu mới" required>
                            <button type="button" class="pw-toggle" onclick="togglePw('confirm_pass',this)" title="Hiện/Ẩn mật khẩu">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-save" style="background:#b2182b;">Đổi mật khẩu</button>
                </form>

            </div>
        </div>
    </main>

    <script>
        // Preview ảnh trước khi upload
        document.getElementById('avatarInput').addEventListener('change', function () {
            const [file] = this.files;
            if (file) {
                document.getElementById('avatarImg').src = URL.createObjectURL(file);
            }
        });

        // Toggle hiển thị / ẩn mật khẩu
        function togglePw(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon  = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
