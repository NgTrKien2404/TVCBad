<?php
// db.php
$host = 'localhost';        // hoặc IP của server CSDL
$dbname = 'badminton_player'; // tên cơ sở dữ liệu bạn tạo
$username = 'root';         // tên đăng nhập MySQL
$password = '';             // mật khẩu MySQL (mặc định XAMPP là rỗng)

$conn = new mysqli($host, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die('Kết nối CSDL thất bại: ' . $conn->connect_error);
}

// Thiết lập charset UTF-8 để hỗ trợ tiếng Việt
$conn->set_charset('utf8');
?>
