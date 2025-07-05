<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Trợ lý ảo Hỏi đáp Pháp luật</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body, html { height: 100%; overflow: hidden; }
    .sidebar { width: 260px; background: #f8f9fa; border-right: 1px solid #ddd; overflow-y: auto; padding: 1rem; height: 100vh; position: relative; }
    .chat-container { flex: 1; display: flex; flex-direction: column; height: 100vh; }
    .chat-messages { flex: 1; overflow-y: auto; padding: 1rem; background: #f1f3f5; }
    .message { margin-bottom: 1rem; }
    .message.user { text-align: right; }
    .message.ai { text-align: left; }
    .history-item { cursor: pointer; padding: 0.5rem; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
    .history-item:hover { background: #e9ecef; }
    .history-text { flex-grow: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .history-time { font-size: 0.8rem; color: #6c757d; }
    .upload-btn { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: #fff; border: 1px solid #ddd; border-radius: 50%; width: 42px; height: 42px; font-size: 24px; line-height: 40px; text-align: center; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.2); }
    .upload-btn:hover { background: #e9ecef; }
  </style>
</head>
<body>

<div class="d-flex h-100">
  <div class="sidebar d-flex flex-column">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-primary mb-0">Lịch sử Chat</h5>
        <span class="text-muted small">Xin chào, <?php echo htmlspecialchars($username); ?></span>
    </div>
    <div id="historyList" class="flex-grow-1 mb-3"></div>
    <button class="btn btn-primary mb-2" id="newChat">Đoạn chat mới</button>
    <button class="btn btn-secondary mb-2" id="logoutBtn">Đăng xuất</button>
    
    <div class="upload-btn" id="uploadBtn">+</div>
    <input type="file" id="fileInput" hidden accept=".pdf,.docx">
  </div>

  <div class="chat-container">
    <div class="chat-messages" id="chatArea">
      <p class="text-muted fst-italic">Chào bạn! Hãy đặt câu hỏi về pháp luật...</p>
    </div>

    <div class="border-top p-3 d-flex gap-2">
      <textarea id="userInput" class="form-control" rows="2" placeholder="Nhập câu hỏi..."></textarea>
      <button id="sendButton" class="btn btn-primary">Gửi</button>
    </div>
  </div>
</div>

<script>
const apiUrl = "http://127.0.0.1"; // Địa chỉ server Flask
const userId = <?php echo json_encode($user_id); ?>;
const username = <?php echo json_encode($username); ?>;
let currentConversationId = null;

const chatArea = document.getElementById('chatArea');
const userInput = document.getElementById('userInput');
const sendButton = document.getElementById('sendButton');
const newChatBtn = document.getElementById('newChat');
const logoutBtn = document.getElementById('logoutBtn');
const historyList = document.getElementById('historyList');
const uploadBtn = document.getElementById('uploadBtn');
const fileInput = document.getElementById('fileInput');

function renderMessage(role, content) {
    const div = document.createElement('div');
    div.className = `message ${role}`;
    div.innerHTML = `<div class="p-2 rounded bg-${role === 'user' ? 'primary text-white' : 'light'} d-inline-block">${content}</div>`;
    chatArea.appendChild(div);
    chatArea.scrollTop = chatArea.scrollHeight;
}

async function loadChatList() {
    try {
        const res = await fetch('get_conversations.php');
        const data = await res.json();
        historyList.innerHTML = "";
        data.data.forEach(conv => {
            const item = document.createElement('div');
            item.className = 'history-item';
            item.innerHTML = `
                <div class="history-text">Đoạn chat #${conv.conversation_id}</div>
                <div class="history-time">${conv.batdau}</div>
            `;
            item.onclick = () => loadConversation(conv.conversation_id);
            historyList.appendChild(item);
        });
    } catch (err) {
        console.error('Lỗi tải lịch sử:', err);
    }
}

async function loadConversation(conversationId) {
    try {
        const res = await fetch(`get_chat_detail.php?conversation_id=${conversationId}`);
        const data = await res.json();
        chatArea.innerHTML = "";
        data.messages.forEach(msg => renderMessage(msg.role, msg.content));
        currentConversationId = conversationId;
    } catch (err) {
        console.error('Lỗi tải đoạn chat:', err);
    }
}

sendButton.addEventListener('click', async () => {
    const question = userInput.value.trim();
    if (!question) return;

    renderMessage('user', question);
    userInput.value = "";

    try {
        const res = await fetch(`${apiUrl}:5000/hoi_dap`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cau_hoi: question,
                user_id: userId,
                conversation_id: currentConversationId
            })
        });
        const data = await res.json();
        renderMessage('ai', data.tra_loi || 'Không có câu trả lời phù hợp.');
        if (data.conversation_id) currentConversationId = data.conversation_id;
        loadChatList();
    } catch (err) {
        renderMessage('ai', 'Lỗi: Không thể kết nối máy chủ.');
    }
});

newChatBtn.addEventListener('click', () => {
    chatArea.innerHTML = `<p class="text-muted fst-italic">Chào bạn! Hãy đặt câu hỏi về pháp luật...</p>`;
    currentConversationId = null;
});

logoutBtn.addEventListener('click', () => {
    window.location.href = "logout.php";
});

uploadBtn.addEventListener('click', () => fileInput.click());

fileInput.addEventListener('change', async () => {
    const file = fileInput.files[0];
    if (!file || (!file.name.endsWith('.pdf') && !file.name.endsWith('.docx'))) {
        return alert('Chỉ hỗ trợ file .pdf hoặc .docx');
    }
    uploadBtn.innerHTML = `<div class="spinner-border spinner-border-sm text-primary"></div>`;
    try {
        const fakePath = "C:/Users/phuon/Desktop/luat/" + file.name;
        const res = await fetch(`${apiUrl}:5000/them_file`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ duong_dan: fakePath })
        });
        const data = await res.json();
        alert(data.thong_bao || "Tải file thành công.");
    } catch (err) {
        alert('Lỗi: Không thể kết nối máy chủ.');
    } finally {
        uploadBtn.innerHTML = '+';
        fileInput.value = '';
    }
});

userInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendButton.click();
    }
});

loadChatList();
</script>

</body>
</html>
