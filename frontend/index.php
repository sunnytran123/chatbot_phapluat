<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Trợ Lý Ảo Pháp Luật</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body, html { height: 100%; margin: 0; }
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        .sidebar h4 { font-weight: bold; margin-bottom: 1rem; }
        .sidebar .username { font-size: 0.9rem; margin-bottom: 1rem; opacity: 0.9; }
        .sidebar .history-item {
            background: rgba(255,255,255,0.1);
            padding: 0.5rem;
            border-radius: 5px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: background 0.3s;
        }
        .sidebar .history-item:hover { background: rgba(255,255,255,0.2); }
        .sidebar .btn { margin-top: auto; }
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            background: #f8f9fa;
        }
        .chat-header {
            background: #fff;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: #e9ecef;
        }
        .message { margin-bottom: 1rem; display: flex; }
        .message.user { justify-content: flex-end; }
        .message.ai { justify-content: flex-start; }
        .bubble {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 20px;
            position: relative;
        }
        .bubble.user { background: #0d6efd; color: white; }
        .bubble.ai { background: #fff; border: 1px solid #dee2e6; }
        .chat-input {
            display: flex;
            padding: 1rem;
            background: #fff;
            border-top: 1px solid #dee2e6;
        }
        textarea {
            flex: 1;
            resize: none;
            border-radius: 20px;
        }
        .upload-btn {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border: 2px solid #fff;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            font-size: 24px;
            line-height: 44px;
            text-align: center;
            color: #0d6efd;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .upload-btn:hover { background: #e9ecef; }
    </style>
</head>
<body>

<div class="d-flex h-100">
    <!-- Sidebar -->
    <div class="sidebar">
        <h4>Trợ Lý Pháp Luật</h4>
        <div class="username">Xin chào, <b><?php echo htmlspecialchars($username); ?></b></div>

        <div id="historyList" class="flex-grow-1"></div>

        <button class="btn btn-light w-100 mb-2" id="newChat">+ Cuộc hội thoại mới</button>
        <button class="btn btn-outline-light w-100 mb-2" id="logoutBtn">Đăng xuất</button>
        
        <?php if ($role === 'canbo'): ?>
            <div class="upload-btn" id="uploadBtn">+</div>
            <input type="file" id="fileInput" hidden accept=".pdf,.docx">
        <?php endif; ?>
    </div>

    <!-- Chat Area -->
    <div class="chat-container">
        <div class="chat-header">Chatbot Pháp Luật</div>
        <div class="chat-messages" id="chatArea">
            <p class="text-muted fst-italic">Hãy đặt câu hỏi pháp luật của bạn...</p>
        </div>
        <div class="chat-input">
            <textarea id="userInput" rows="2" class="form-control me-2" placeholder="Nhập câu hỏi..."></textarea>
            <button class="btn btn-primary" id="sendButton">Gửi</button>
        </div>
    </div>
</div>

<script>
const apiUrl = "http://127.0.0.1:5000";
const userId = <?php echo json_encode($user_id); ?>;
const role = <?php echo json_encode($role); ?>;
let currentConversationId = null;

const chatArea = document.getElementById("chatArea");
const userInput = document.getElementById("userInput");
const sendButton = document.getElementById("sendButton");
const newChatBtn = document.getElementById("newChat");
const logoutBtn = document.getElementById("logoutBtn");
const historyList = document.getElementById("historyList");
const uploadBtn = document.getElementById("uploadBtn");
const fileInput = document.getElementById("fileInput");

function renderMessage(role, content) {
    const msg = document.createElement("div");
    msg.className = `message ${role}`;
    msg.innerHTML = `<div class="bubble ${role}">${content}</div>`;
    chatArea.appendChild(msg);
    chatArea.scrollTop = chatArea.scrollHeight;
}

async function loadChatList() {
    const res = await fetch(`${apiUrl}/lich_su/${userId}`);
    const data = await res.json();
    historyList.innerHTML = "";
    data.lich_su.forEach(conv => {
        const item = document.createElement("div");
        item.className = "history-item";
        item.innerHTML = `
            <div>Đoạn #${conv.conversation_id}</div>
            <small>${conv.messages[0]?.time || ""}</small>
        `;
        item.onclick = () => loadConversation(conv.conversation_id);
        historyList.appendChild(item);
    });
}

async function loadConversation(conversationId) {
    const res = await fetch(`${apiUrl}/lich_su/${userId}`);
    const data = await res.json();
    chatArea.innerHTML = "";
    const convo = data.lich_su.find(c => c.conversation_id === conversationId);
    if (convo) {
        convo.messages.forEach(msg => renderMessage(msg.role, msg.content));
        currentConversationId = conversationId;
    }
}

sendButton.onclick = async () => {
    const text = userInput.value.trim();
    if (!text) return;
    renderMessage("user", text);
    userInput.value = "";

    const res = await fetch(`${apiUrl}/hoi_dap`, {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({
            cau_hoi: text,
            user_id: userId,
            conversation_id: currentConversationId,
            role: role
        })
    });
    const data = await res.json();
    renderMessage("ai", data.tra_loi || "Không có câu trả lời phù hợp.");
    if (data.conversation_id) currentConversationId = data.conversation_id;
    loadChatList();
};

newChatBtn.onclick = () => {
    chatArea.innerHTML = `<p class="text-muted fst-italic">Hãy đặt câu hỏi pháp luật của bạn...</p>`;
    currentConversationId = null;
};

logoutBtn.onclick = () => window.location.href = "logout.php";

if (uploadBtn && fileInput) {
    uploadBtn.onclick = () => fileInput.click();
    fileInput.onchange = async () => {
        const file = fileInput.files[0];
        if (!file || (!file.name.endsWith(".pdf") && !file.name.endsWith(".docx"))) {
            return alert("Chỉ hỗ trợ file PDF hoặc DOCX.");
        }
        uploadBtn.innerHTML = `<div class="spinner-border spinner-border-sm text-primary"></div>`;
        try {
            const fakePath = "C:/Users/phuon/Desktop/luat/" + file.name;
            const res = await fetch(`${apiUrl}/them_file`, {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({
                    duong_dan: fakePath,
                    loai: "congkhai",
                    user_id: userId
                })
            });
            const data = await res.json();
            alert(data.thong_bao || "Tải file thành công.");
        } catch {
            alert("Không thể kết nối server.");
        } finally {
            uploadBtn.innerHTML = "+";
            fileInput.value = "";
        }
    };
}

userInput.addEventListener("keypress", e => {
    if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        sendButton.click();
    }
});

loadChatList();
</script>

</body>
</html>
