<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(["error" => "Chưa đăng nhập."]));
}

require_once 'config.php';

$user_id = $_SESSION['user_id'];
$conversation_id = intval($_GET['conversation_id'] ?? 0);

if ($conversation_id <= 0) {
    http_response_code(400);
    exit(json_encode(["error" => "Thiếu conversation_id."]));
}

$sql = "SELECT role, content, created_at 
        FROM chat_history 
        WHERE user_id = ? AND conversation_id = ?
        ORDER BY created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $conversation_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        "role" => $row['role'],
        "content" => $row['content'],
        "time" => $row['created_at']
    ];
}

echo json_encode(["messages" => $messages], JSON_UNESCAPED_UNICODE);
