CREATE DATABASE IF NOT EXISTS picoctf_clone;
USE picoctf_clone;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE,
    full_name VARCHAR(100),
    phone VARCHAR(20),
    avatar VARCHAR(255) DEFAULT 'default_avatar.png',
    role ENUM('normal user', 'admin') DEFAULT 'normal user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (username)
);

CREATE TABLE IF NOT EXISTS otp_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (identifier)
);

-- Mật khẩu mặc định 'Admin@123' đã được băm bằng BCRYPT thay vì MD5
INSERT INTO users (username, password_hash, email, full_name)
VALUES ('admin', '$2y$10$e0MYzXyjpJS7Pd0RVvOxGu6rJ.SNozJvF6J6vF.fF2T6Q0u3.3.0m', 'admin@picoctf.local', 'Pico Administrator')
ON DUPLICATE KEY UPDATE id=id;
