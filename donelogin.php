<?php
// Bắt đầu Session để đọc trí nhớ của server
session_set_cookie_params(['httponly' => true]);
session_start();


// Nếu chưa có thẻ đăng nhập hợp lệ (chưa qua process_login) thì về login!
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Lấy tên ra biến, dùng hàm htmlspecialchars để chống lỗ hổng XSS bị cài cắm mã độc script
$cau_chao_ten = htmlspecialchars($_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>picoCTF - My Classrooms</title>
    <!-- Trỏ đến file CSS bạn vừa yêu cầu -->
    <link rel="stylesheet" href="doneloginstyle.css">
    
    <!-- Font Awesome dùng cho các icon (Chuông, Mũi tên, Plus, FB...) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts: Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
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
                <li>
                    <a href="#">Learn <i class="fas fa-caret-down" style="font-size: 0.8em; margin-left: 2px;"></i></a>
                </li>
                <li><a href="#">Practice</a></li>
                <li><a href="#">Compete</a></li>
                <li><a href="#" class="active">Classrooms</a></li>
            </ul>
        </div>
        
        <div class="nav-right">
            <a href="#" class="icon-link"><i class="far fa-bell"></i></a>
            
            <!-- ====== KHU VỰC ĐÃ SỬA THÀNH TÊN ĐỘNG ====== -->
            <a href="#" class="user-profile">
                <?php echo $cau_chao_ten; ?> <i class="fas fa-user" style="margin-left: 5px;"></i>
            </a>
            
            <!-- NÚT LOGOUT VỪA ĐƯỢC THÊM VÀO KẾ HOẠCH -->
            <a href="logout.php" style="color: #ff6b6b; font-size: 0.95em; text-decoration: none; font-weight: 600; margin-left: 20px;">
                <i class="fas fa-sign-out-alt"></i> Thoát
            </a>
            
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="container">
            <div class="classroom-card">
                <h1 class="card-title">My Classrooms</h1>
                <p class="card-subtitle">Join or create a classroom to get custom event scoreboards and track classroom members' progress.</p>
                
                <div class="actions-row">
                    <a href="#" class="action-link">
                        <i class="fas fa-sign-in-alt"></i> Join a Classroom
                    </a>
                    <a href="#" class="action-link">
                        <i class="fas fa-plus-circle"></i> Create New Classroom
                    </a>
                </div>

                <table class="classroom-table">
                    <thead>
                        <tr>
                            <th>CLASSROOM NAME</th>
                            <th style="text-align: center;">STATUS</th>
                            <th style="text-align: right;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="3" style="text-align: center;">You are not a member of any classrooms.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- NÚT WEBSHELL CỐ ĐỊNH -->
        <a href="#" class="webshell-btn">
            >_ Webshell
        </a>
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
