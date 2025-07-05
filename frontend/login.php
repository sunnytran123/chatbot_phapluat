<?php
session_start();
require_once 'config.php';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $hashed);
        $stmt->fetch();

        if (password_verify($password, $hashed)) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            header("Location: testdndkfull.php");
            exit();
        } else {
            $error = "Sai mật khẩu!";
        }
    } else {
        $error = "Tài khoản không tồn tại!";
    }
}
?>

<form method="POST">
    <h2>Đăng nhập</h2>
    <input type="text" name="username" placeholder="Tài khoản" required><br>
    <input type="password" name="password" placeholder="Mật khẩu" required><br>
    <button type="submit" name="login">Đăng nhập</button>
</form>

<?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
<p><a href="register.php">Chưa có tài khoản? Đăng ký</a></p>
