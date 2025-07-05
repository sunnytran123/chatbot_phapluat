<?php
$servername = "localhost";
$username = "root";
$password = ""; // Sửa theo cấu hình của bạn
$database = "chatbot_phapluat";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Kết nối CSDL thất bại: " . $conn->connect_error);
}
?> 
