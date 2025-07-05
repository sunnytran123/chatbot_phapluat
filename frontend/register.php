<?php
session_start();
require_once 'config.php';

$error = ""; // Khởi tạo biến báo lỗi

if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $error = "Mật khẩu nhập lại không khớp.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hashed);

        try {
            if ($stmt->execute()) {
                header("Location: login.php");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                $error = "Tài khoản đã tồn tại.";
            } else {
                $error = "Đã có lỗi xảy ra: " . $e->getMessage();
            }
        }
    }
}
?>

<form method="POST">
    <h2>Đăng ký</h2>
    <input type="text" name="username" placeholder="Tài khoản" required><br>
    <input type="password" name="password" placeholder="Mật khẩu" required><br>
    <input type="password" name="confirm_password" placeholder="Nhập lại mật khẩu" required><br>
    <button type="submit" name="register">Đăng ký</button>
</form>

<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<p><a href="login.php">Đã có tài khoản? Đăng nhập</a></p>
