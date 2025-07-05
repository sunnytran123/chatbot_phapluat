<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(["error" => "Chưa đăng nhập."]));
}

require_once 'config.php'; // File kết nối MySQL

$user_id = $_SESSION['user_id'];

$sql = "SELECT conversation_id, MIN(created_at) AS batdau, MAX(created_at) AS ketthuc
        FROM chat_history 
        WHERE user_id = ?
        GROUP BY conversation_id
        ORDER BY ketthuc DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$conversations = [];
while ($row = $result->fetch_assoc()) {
    $conversations[] = [
        "conversation_id" => $row['conversation_id'],
        "batdau" => $row['batdau'],
        "ketthuc" => $row['ketthuc']
    ];
}

echo json_encode(["data" => $conversations], JSON_UNESCAPED_UNICODE);
