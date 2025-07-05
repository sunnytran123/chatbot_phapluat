<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(["error" => "Chưa đăng nhập."]));
}

require_once 'config.php';

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);

$content = trim($data['content'] ?? "");
$role = $data['role'] ?? "";
$conversation_id = intval($data['conversation_id'] ?? 0);

if (!$content || !in_array($role, ["user", "ai"])) {
    http_response_code(400);
    exit(json_encode(["error" => "Thiếu dữ liệu cần thiết."]));
}

if ($conversation_id <= 0) {
    // Tạo conversation_id mới
    $sql = "SELECT MAX(conversation_id) FROM chat_history WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($max_conv);
    $stmt->fetch();
    $conversation_id = $max_conv + 1;
    $stmt->close();
}

$sql = "INSERT INTO chat_history (user_id, conversation_id, role, content) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiss", $user_id, $conversation_id, $role, $content);
$stmt->execute();

echo json_encode(["success" => true, "conversation_id" => $conversation_id]);
