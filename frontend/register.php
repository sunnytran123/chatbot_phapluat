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

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex justify-content-center align-items-center" style="height:100vh;">

<div class="card p-4" style="min-width: 300px;">
<h4 class="mb-3 text-center" style="color:#333;">Đăng ký</h4>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Tài khoản</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Mật khẩu</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Nhập lại mật khẩu</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <button type="submit" name="register" class="btn btn-success w-100">Đăng ký</button>

        <div class="text-center mt-3">
            <span>Đã có tài khoản? <a href="login.php">Đăng nhập tại đây</a></span>
        </div>
    </form>
</div>

</body>
</html>