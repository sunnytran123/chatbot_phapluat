<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";

    $apiUrl = "http://127.0.0.1:5000/dang_nhap";
    $data = ["username" => $username];

    $options = [
        "http" => [
            "header"  => "Content-Type: application/json",
            "method"  => "POST",
            "content" => json_encode($data),
            "ignore_errors" => true
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($apiUrl, false, $context);

    if ($result !== false) {
        $resData = json_decode($result, true);
        
        if (isset($resData["user_id"]) && isset($resData["hashed_password"])) {
            if (password_verify($password, $resData["hashed_password"])) {
                
                $_SESSION["user_id"] = $resData["user_id"];
                $_SESSION["username"] = $username;
                $_SESSION["role"] = $resData["role"];

                header("Location: indexnew.php");
                exit();
            } else {
                $thong_bao = "Sai tài khoản hoặc mật khẩu.";
            }
        } else {
            $thong_bao = $resData["thong_bao"] ?? "Đăng nhập thất bại.";
        }
    } else {
        $thong_bao = "Không thể kết nối server.";
    }
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex justify-content-center align-items-center" style="height:100vh;">

<div class="card p-4" style="min-width: 300px;">
    <h4 class="mb-3">Đăng nhập</h4>
    <?php if (isset($thong_bao)): ?>
        <div class="alert alert-danger"><?php echo $thong_bao; ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Tài khoản</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Mật khẩu</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-primary w-100">Đăng nhập</button>
    </form>
</div>

</body>
</html>
