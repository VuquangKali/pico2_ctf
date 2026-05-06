<?php
// Bắt đầu Session để đọc trí nhớ của server
session_set_cookie_params(['httponly' => true]); // Cấu hình cookie session với cờ httponly để ngăn chặn truy cập từ JavaScript (chống XSS)
session_start(); // Bắt đầu session để có thể lấy hoặc lưu trữ dữ liệu người dùng qua các trang


// Nếu chưa có thẻ đăng nhập hợp lệ (chưa qua process_login) thì về login!
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { // Kiểm tra xem biến session 'loggedin' có tồn tại và mang giá trị true hay không
    header("Location: login.php"); // Nếu không phải là người dùng đã đăng nhập, chuyển hướng người dùng về trang đăng nhập
    exit; // Dừng việc tải trang để ngăn người dùng trái phép xem nội dung phía dưới
}

// Lấy thông tin user (Avatar) từ DB
require 'config.php';
$stmt = $pdo->prepare("SELECT avatar, role FROM users WHERE username = :username");
$stmt->execute(['username' => $_SESSION['username']]);
$user_db = $stmt->fetch();
$avatar_src = ($user_db && $user_db['avatar'] != 'default_avatar.png') ? 'uploads/avatars/' . $user_db['avatar'] : 'default_avatar.png';

// Lấy tên ra biến, dùng hàm htmlspecialchars để chống lỗ hổng XSS bị cài cắm mã độc script
$cau_chao_ten = htmlspecialchars($_SESSION['username']); // Lấy tên đăng nhập từ session và xử lý các ký tự đặc biệt thành thực thể HTML để chống XSS

$bg_image = '';
$extensions = ['jpg', 'png', 'gif', 'jpeg'];
foreach ($extensions as $ext) {
    if (file_exists("uploads/bg.$ext")) {
        $bg_image = "uploads/bg.$ext?" . time();
        break;
    }
}

$bg_style = 'cover';
if (file_exists('uploads/bg_style.txt')) {
    $bg_style = file_get_contents('uploads/bg_style.txt');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>picoCTF - My Classrooms</title>
    <!-- Trỏ đến file CSS vừa yêu cầu -->
    <link rel="stylesheet" href="doneloginstyle.css">

    <!-- Font Awesome dùng cho các icon (Chuông, Mũi tên, Plus, FB...) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts: Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        <?php if ($bg_image): ?>
        body {
            background-image: url('<?php echo $bg_image; ?>') !important;
            background-size: <?php echo $bg_style; ?> !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
            background-attachment: fixed !important;
        }
        <?php endif; ?>
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-left">
            <a href="donelogin.php" class="nav-brand">
                <!-- SVG tái tạo logo picoCTF -->
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
            </a>
            <ul class="nav-links">
                <?php if (($user_db['role'] ?? 'user') === 'admin'): ?>
                <li><a href="admin.php?tab=challenge"><i class="fas fa-flag"></i> Quản lý bài CTF</a></li>
                <li><a href="admin.php?tab=category"><i class="fas fa-list"></i> Quản lý danh mục</a></li>
                <li><a href="admin.php?tab=participants"><i class="fas fa-users"></i> Người tham gia</a></li>
                <li><a href="upload_bg.php"><i class="fas fa-image"></i> Tải ảnh nền</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="nav-right">
            <a href="#" class="icon-link"><i class="far fa-bell"></i></a>

            <!-- ====== KHU VỰC ĐÃ SỬA THÀNH TÊN ĐỘNG & AVATAR ====== -->
            <div class="user-menu-container"
                style="position: relative; display: flex; align-items: center; gap: 10px; margin-left: 15px;">
                <img src="<?php echo $avatar_src; ?>" alt="Avatar"
                    style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 2px solid #b196b6;">
                <a href="profile.php" class="user-profile"
                    style="text-decoration: none; color: #fff; font-weight: 500;">
                    <?php echo $cau_chao_ten; ?>
                </a>
            </div>


            <a href="logout.php"
                style="color: #ff6b6b; font-size: 0.95em; text-decoration: none; font-weight: 600; margin-left: 20px;">
                <i class="fas fa-sign-out-alt"></i> Thoát
            </a>

        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content" style="display: flex; align-items: center; justify-content: center;">
        <div class="container" style="text-align: center;">
            <a href="dashboard.php" style="
                display: inline-flex; 
                align-items: center; 
                background-color: #b2182b; 
                color: white; 
                padding: 20px 50px; 
                font-size: 1.8rem; 
                font-weight: bold; 
                border-radius: 50px; 
                text-decoration: none; 
                box-shadow: 0 10px 20px rgba(0,0,0,0.5);
                transition: transform 0.2s, background-color 0.2s;
                text-transform: uppercase;
                letter-spacing: 2px;
            " onmouseover="this.style.transform='scale(1.05)'; this.style.backgroundColor='#8a1321';" onmouseout="this.style.transform='scale(1)'; this.style.backgroundColor='#b2182b';">
                <i class="fas fa-play" style="margin-right: 15px;"></i> Bắt Đầu Giải CTF
            </a>
        </div>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-links">
            <a href="#">PICOCTF</a>
            <a href="#">PRIVACY STATEMENT</a>
            <a href="#">TERMS OF SERVICE</a>
        </div>

        <div class="footer-right">
            <div class="social-icons">
                <a href="#"><i class="fab fa-facebook-square"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-discord"></i></a>
            </div>
            <span class="copyright">© 2026 picoCTF</span>
        </div>
    </footer>

</body>

</html>