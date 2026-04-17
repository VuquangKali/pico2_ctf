<?php
// Simple wrapper around PHPMailer (manual include, no Composer)
require __DIR__ . '/vendor/PHPMailer-master/src/Exception.php';
require __DIR__ . '/vendor/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/vendor/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email via PHPMailer.
 *
 * @param string $to      Recipient email address.
 * @param string $subject Subject line.
 * @param string $body    HTML body.
 * @return bool           True on success, false on failure.
 */
function sendMail(string $to, string $subject, string $body): bool
{
    $mail = new PHPMailer(true);
    try {
        // Cấu hình máy chủ SMTP thật (Ví dụ sử dụng Gmail SMTP)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';     // Máy chủ SMTP của Gmail
        $mail->SMTPAuth = true;                 // Bật xác thực SMTP
        $mail->Username = 'vuquang30102003@gmail.com'; // Thay bằng địa chỉ email của bạn
        $mail->Password = 'vdfjtccleauiocxg';    // Mật khẩu ứng dụng (App Password) của Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Khuyên dùng STARTTLS ở port 587 hoặc SMTPS ở port 465
        $mail->Port = 587;                  // Port kết nối SMTP

        // Cấu hình ngôn ngữ UTF-8 để không bị lỗi font tiếng Việt
        $mail->CharSet = 'UTF-8';

        // Người gửi & Người nhận
        $mail->setFrom('vuquang30102003@gmail.com', 'picoCTF Support'); // Thay bằng email hệ thống của bạn
        $mail->addAddress($to);

        // Nội dung Email
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('Lỗi gửi email thực tế: ' . $e->getMessage());
        return false;
    }
}
?>