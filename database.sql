CREATE DATABASE IF NOT EXISTS picoctf_clone;
USE picoctf_clone;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tạo một tài khoản admin mặc định với mật khẩu là 'Admin@123'
-- Lưu ý: Hàm MD5 thường không được khuyên dùng trong thực tế (rủi ro bảo mật)
-- Nhưng để bạn dễ hiệu luồng lập trình chay ở mức cơ bản, chúng ta sẽ bắt đầu với mã băm này.
INSERT INTO users (username, password_hash)
VALUES ('admin', md5('Admin@123'))
ON DUPLICATE KEY UPDATE id=id;
