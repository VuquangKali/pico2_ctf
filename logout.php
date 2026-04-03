<?php
session_set_cookie_params(['httponly' => true]);
session_start();
// Hủy bỏ tất cả các biến session
$_SESSION = array();

// Phá huỷ Session
session_destroy();

// Điều hướng người dùng về trang login
header("Location: login.php");
exit;
?>
