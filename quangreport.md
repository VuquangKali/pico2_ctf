# Kế hoạch & Báo cáo Pentest: Dự án Picodoc
**Phương pháp:** Gray-box Testing (Kiểm thử hộp xám). 
**Giả định:** Pentester được cấp một tài khoản User hợp lệ (VD: user bình thường) và được cung cấp một phần thông tin về kiến trúc bên dưới (PHP/MySQL) nhưng không có được quyền truy cập vào Server hay Source Code.

Dưới đây là danh sách đánh giá trọng điểm theo WSTG từ góc nhìn của một người dùng đã đăng nhập, tìm cách leo thang đặc quyền hoặc tấn công các người dùng khác.

---

## 1. Information Gathering (WSTG-INFO)

### WSTG-INFO-02: Phân tích Server & Framework
- **Mục tiêu Gray-box trên Picodoc:** Dùng công cụ (Burp Suite/DevTools) khi đã đăng nhập để soi các HTTP Header nhằm biết chính xác phiên bản PHP/Apache đang dùng.
- **Tình trạng:** **Lỗi (High)**
- **Chi tiết:** Máy chủ trả về chính xác chuỗi `Apache/2.4.58` và `PHP/8.2.12`. Kẻ gian dễ dàng dùng nó để tìm kiếm mã khai thác 1-click (CVE) tương ứng.
- **Đề xuất sửa lỗi:** Mở `php.ini` sửa `expose_php = Off`. Mở `httpd.conf` thiết lập `ServerTokens Prod` và `ServerSignature Off`.

## 2. Authentication & Authorization Testing (WSTG-ATHN & WSTG-ATHZ)

### WSTG-ATHZ-02: Bypass Authorization
- **Mục tiêu Gray-box trên Picodoc:** Với quyền là user thường, thử dò tìm các endpoint ẩn của admin (ví dụ: `admin.php`, `config.php`) để xem có bị chặn (Access Control) hay không.
- **Tình trạng:** **Lỗi (High)**
- **Chi tiết:** Kẻ tấn công truy cập và tải thẳng được file `database.sql` lộ toàn bộ kiến trúc DB và mã băm MD5 của admin.
- **Đề xuất sửa lỗi:** Chuyển file `database.sql` ra vùng ngầm bên ngoài thư mục `htdocs`, hoặc tạo file tường lửa `.htaccess` chặn đuôi `*.sql`.

### WSTG-ATHN-04: Kiểm tra Bypass Xác thực
- **Mục tiêu Gray-box trên Picodoc:** Đăng nhập ẩn danh, sau đó thay đổi/giả mạo tham số Cookie bằng tên của `admin` xem có lấy được quyền admin không.
- **Tình trạng:** **An toàn (Pass)**
- **Chi tiết:** Ứng dụng quản lý phiên chính xác trên Server qua `PHPSESSID` chuỗi ngẫu nhiên. Client không thể tự ý ép quyền bằng cách đổi Auth Cookie.
- **Đề xuất tiếp theo:** Tốt. Tiếp tục duy trì xác thực dựa trên Server-side Session.

### WSTG-ATHZ-04: Insecure Direct Object References (IDOR)
- **Mục tiêu Gray-box trên Picodoc:** (Dự phòng) Hiện tại Picodoc không dùng URL chứa ID, nhưng nếu sau này có `profile.php?id=2`, Pentester sẽ sửa số 2 thành số 1 (của admin) xem có lộ dữ liệu không.
- **Tình trạng:** **N/A (Không áp dụng)**
- **Chi tiết:** Hiện tại luồng dữ liệu của web hoàn toàn lấy tự động từ Session (không có tham số ID nào trên URL để giả mạo).
- **Đề xuất tiếp theo:** N/A

## 3. Session Management Testing (WSTG-SESS)

### WSTG-SESS-03: Khai thác Session Fixation
- **Mục tiêu Gray-box trên Picodoc:** Cố tình lừa nạn nhân bè xài chung `PHPSESSID` của mình, sau đó đợi tài khoản đó đăng nhập để chiếm đoạt tài khoản.
- **Tình trạng:** **Lỗi (High)**
- **Chi tiết:** Hacker có thể chiếm đoạt hoàn toàn tài khoản của người khác mà không cần biết mật khẩu do lỗi Server không thay đổi Session ID sau khi đăng nhập.
- **Đề xuất sửa lỗi:** Bổ sung hàm lệnh: `session_regenerate_id(true);` vào file `login.php` ngay mốc sau khi kiểm tra xong mật khẩu thành công.

### WSTG-SESS-06: Kiểm tra vòng đời Session
- **Mục tiêu Gray-box trên Picodoc:** Đăng xuất (`logout.php`), sau đó copy dán lại chính `PHPSESSID` cũ bằng DevTools (F12) để xem có thể "hồi sinh" phiên truy cập cũ không.
- **Tình trạng:** **An toàn (Pass)**
- **Chi tiết:** Mã nguồn thiết lập `session_destroy()` rất tốt. Dù Cookie không thay đổi nhưng hồ sơ máy chủ đã bị tiêu hủy sạch sẽ.
- **Đề xuất tiếp theo:** Tốt. (Cần sửa thêm tính năng điều hướng `login.html` thành `login.php` ở `donelogin.php`).

### WSTG-SESS-01: Độ an toàn của Cookie
- **Mục tiêu Gray-box trên Picodoc:** Kiểm tra cờ `HttpOnly`, `Secure` trên cookie `PHPSESSID` bằng F12 -> Application -> Cookies.
- **Tình trạng:** **Lỗi (Medium)**
- **Chi tiết:** Cookie thiếu các cờ bảo vệ (`HttpOnly`, `SameSite`). Trình duyệt cho phép Javascript thoải mái ăn cắp Cookie này nếu web dính lỗi XSS.
- **Đề xuất sửa lỗi:** Chèn câu cấu hình `session_set_cookie_params(['httponly' => true]);` trước câu `session_start();` trong mọi file PHP.

## 4. Input Validation & Business Logic (WSTG-INPV & WSTG-BUSL)

### WSTG-INPV-02: Stored XSS bên trong Dashboard
- **Mục tiêu Gray-box trên Picodoc:** Dùng quyền Gray-box đi đăng ký một account "lạ" (Trộn thẻ script `<script>alert('Hack')</script>` vào Username). Đăng nhập vào để kiểm tra Dashboard `donelogin.php` có dính độc não không.
- **Tình trạng:** **An toàn (Pass)**
- **Chi tiết:** Đoạn mã độc nằm ngoan ngoãn trên màn hình do đã bị vô hiệu hóa, không có bảng thông báo (Alert popup) nào hiện lên.
- **Đề xuất tiếp theo:** Tốt. Hệ thống đã xử lý hoàn hảo nhờ hàm `htmlspecialchars()` bọc quanh `$_SESSION['username']` khi in ra HTML. Cần duy trì thói quen này.

### WSTG-BUSL-02: Phân tích Luồng Logic
- **Mục tiêu Gray-box trên Picodoc:** Lợi dụng file `register.php`, cố gắng đăng ký đè lên tên `admin` đã tồn tại bằng nhiều thủ thuật (thêm dấu cách đặc biệt đằng sau chữ admin, hoặc viết hoa `Admin`) xem hệ thống có nhận diện sai và sửa đè dữ liệu mật khẩu cũ hay không.
- **Tình trạng:** **An toàn (Pass)**
- **Chi tiết:** Hệ thống nhận diện thành công sự tồn tại của tên gốc và chặn đứng ý đồ đăng ký đè/ẩn của Hacker.
- **Đề xuất tiếp theo:** Tốt. Sự kết hợp giữa lệnh `trim()` trong PHP và tính năng tự động không phân biệt chữ hoa/thường (Case-insensitive) của MySQL đã khóa chặt lỗ hổng này.

---

> [!TIP]
> **Quy trình Gray-Box:** Bạn hãy đóng vai là một hacker nội bộ. Đăng ký một tài khoản tên là HackerX. Sau đó, chúng ta sẽ xem xét xem từ bên trong tài khoản này, bạn có thể quậy phá được những gì. Bạn muốn test mục nào đầu tiên?

## 5. Giai đoạn 2 (Bổ sung từ WSTG Excel)

Sau khi rà soát lại ma trận WSTG, đây là những mục còn lại hoàn toàn có thể áp dụng cho kiến trúc hiện tại của dự án:

### WSTG-ATHN-03: Brute Force (Vét cạn)
- **Mục tiêu trên Picodoc:** Thử đăng nhập liên tục 100 lần bằng tài khoản `admin` xem có bị khóa IP hay khóa tài khoản không
- **Tình trạng:** **Lỗi (High)**
- **Chi tiết:** Ứng dụng không có cơ chế Rate Limiting hoặc CAPTCHA. Hacker có thể treo máy tự động thử mật khẩu hàng triệu lần cho đến khi chiếm được tài khoản.
- **Đề xuất sửa lỗi:** Thêm logic đếm số lần sai vào Database hoặc Session và chặn 15 phút.

### WSTG-SESS-08: CSRF (Cross-Site Request Forgery)
- **Mục tiêu trên Picodoc:** Kiểm tra xem Form `register.php` và `login.php` có mã Token sinh ngẫu nhiên để chống gian lận Request không.
- **Tình trạng:** **Lỗi (Medium)**
- **Chi tiết:** Các form không có thẻ `input type="hidden"` chứa token bảo vệ. Kẻ xấu có thể tạo web giả mạo ép người dùng gửi request tạo hàng ngàn tài khoản rác.
- **Đề xuất sửa lỗi:** Tạo CSRF Token bằng `bin2hex(random_bytes(32))` lưu vào Session và nhúng vào HTML Form.

### WSTG-INPV-05: SQL Injection (SQLi)
- **Mục tiêu trên Picodoc:** Gõ Username hoặc Pass là `' OR '1'='1` để xem liệu cách viết PDO của bạn có thực sự chống được SQLi không.
- **Tình trạng:** **An toàn (Pass)**
- **Chi tiết:** Ứng dụng dửng dưng từ chối đăng nhập. Mã độc không thể phá quy tắc lệnh gốc được định nghĩa sẵn.
- **Đề xuất tiếp theo:** Rất tốt. Căn nguyên sự an toàn này nằm ở tính năng Prepared Statements mặc định của PDO. Nên duy trì!
