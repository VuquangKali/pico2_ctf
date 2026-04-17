<?php
session_start();

// Hủy bỏ tất cả các biến session
$_SESSION = array();

// Nếu muốn hủy toàn bộ session, cũng phải xóa cả cookie session.
// Lưu ý: Việc này sẽ khai tử session chứ không chỉ xóa dữ liệu trong session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Cuối cùng, phá bỏ session.
session_destroy();

// Điều hướng người dùng về trang login
header("Location: login.php");
exit;
?>
