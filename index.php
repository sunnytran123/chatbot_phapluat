<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trợ lý ảo Hỏi đáp Pháp luật</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body, html {
      height: 100%;
      overflow: hidden;
    }
    .sidebar {
      width: 260px;
      background: #f8f9fa;
      border-right: 1px solid #ddd;
      overflow-y: auto;
      padding: 1rem;
      height: 100vh;
      position: relative;
    }
    .chat-container {
      flex: 1;
      display: flex;
      flex-direction: column;
      height: 100vh;
    }
    .chat-messages {
      flex: 1;
      overflow-y: auto;
      padding: 1rem;
      background: #f1f3f5;
    }
    .message {
      margin-bottom: 1rem;
    }
    .message.user {
      text-align: right;
    }
    .message.ai {
      text-align: left;
    }
    .history-item {
      cursor: pointer;
      padding: 0.5rem;
      border-radius: 4px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.5rem;
    }
    .history-item:hover {
      background: #e9ecef;
    }
    .history-text {
      flex-grow: 1;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .upload-btn {
      position: absolute;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 50%;
      width: 42px;
      height: 42px;
      font-size: 24px;
      line-height: 40px;
      text-align: center;
      cursor: pointer;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }
    .upload-btn:hover {
      background: #e9ecef;
    }
  </style>
</head>
<body>

<div class="d-flex h-100">

  <div class="sidebar d-flex flex-column">
    <h5 class="fw-bold mb-3 text-primary">Lịch sử Chat</h5>
    <div id="historyList" class="flex-grow-1 mb-3"></div>
    <button class="btn btn-primary mb-2" id="newChat">Đoạn chat mới</button>

    <!-- Icon Upload -->
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
const chatArea = document.getElementById('chatArea');
const userInput = document.getElementById('userInput');
const sendButton = document.getElementById('sendButton');
const newChatBtn = document.getElementById('newChat');
const historyList = document.getElementById('historyList');
const uploadBtn = document.getElementById('uploadBtn');
const fileInput = document.getElementById('fileInput');

let currentChat = [];
let allChats = JSON.parse(localStorage.getItem('chatHistory')) || [];
let currentChatIndex = -1;

function renderHistory() {
  historyList.innerHTML = "";
  allChats.forEach((chat, index) => {
    const item = document.createElement('div');
    item.className = 'history-item';

    const text = document.createElement('div');
    text.className = 'history-text';
    text.textContent = chat.messages?.[0]?.content?.slice(0, 30) || `Đoạn chat ${index + 1}`;

    const delBtn = document.createElement('button');
    delBtn.className = 'btn btn-sm btn-outline-danger';
    delBtn.textContent = 'Xóa';
    delBtn.onclick = (e) => {
      e.stopPropagation();
      if (confirm('Xác nhận xóa đoạn chat này?')) {
        allChats.splice(index, 1);
        localStorage.setItem('chatHistory', JSON.stringify(allChats));
        renderHistory();
      }
    };

    item.appendChild(text);
    item.appendChild(delBtn);
    item.onclick = () => loadChat(index);
    historyList.appendChild(item);
  });
}
renderHistory();

function loadChat(index) {
  const selectedChat = allChats[index];
  if (!selectedChat) return;

  currentChatIndex = index;
  currentChat = JSON.parse(JSON.stringify(selectedChat.messages));
  chatArea.innerHTML = "";
  currentChat.forEach(msg => renderMessage(msg.role, msg.content));
}

function renderMessage(role, content) {
  const div = document.createElement('div');
  div.className = `message ${role}`;
  div.innerHTML = `<div class="p-2 rounded bg-${role === 'user' ? 'primary text-white' : 'light'} d-inline-block">${content}</div>`;
  chatArea.appendChild(div);
  chatArea.scrollTop = chatArea.scrollHeight;
}

sendButton.addEventListener('click', async () => {
  const question = userInput.value.trim();
  if (!question) return;

  renderMessage('user', question);
  currentChat.push({ role: 'user', content: question });
  userInput.value = '';

  try {
    const res = await fetch('http://127.0.0.1:5000/hoi_dap', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cau_hoi: question })
    });
    const data = await res.json();

    const answer = data.tra_loi || 'Không có câu trả lời phù hợp.';
    renderMessage('ai', answer);
    currentChat.push({ role: 'ai', content: answer });

    if (currentChatIndex >= 0) {
      allChats[currentChatIndex].messages = JSON.parse(JSON.stringify(currentChat));
    }

  } catch (err) {
    renderMessage('ai', 'Lỗi: Không thể kết nối tới máy chủ.');
  }

  if (currentChatIndex === -1) {
    allChats.push({ messages: JSON.parse(JSON.stringify(currentChat)) });
    currentChatIndex = allChats.length - 1;
  } else {
    allChats[currentChatIndex].messages = JSON.parse(JSON.stringify(currentChat));
  }

  localStorage.setItem('chatHistory', JSON.stringify(allChats));
  renderHistory();
});

newChatBtn.addEventListener('click', () => {
  if (currentChat.length) {
    if (currentChatIndex === -1) {
      allChats.push({ messages: JSON.parse(JSON.stringify(currentChat)) });
    } else {
      allChats[currentChatIndex].messages = JSON.parse(JSON.stringify(currentChat));
    }
    localStorage.setItem('chatHistory', JSON.stringify(allChats));
    renderHistory();
  }
  currentChat = [];
  currentChatIndex = -1;
  chatArea.innerHTML = `<p class="text-muted fst-italic">Chào bạn! Hãy đặt câu hỏi về pháp luật...</p>`;
});

uploadBtn.addEventListener('click', () => {
  fileInput.click();
});

fileInput.addEventListener('change', async () => {
  const file = fileInput.files[0];
  if (!file) return alert('Vui lòng chọn file PDF hoặc Word!');

  if (!file.name.endsWith('.pdf') && !file.name.endsWith('.docx')) {
    return alert('Chỉ hỗ trợ file .pdf hoặc .docx');
  }

  const fakePath = "C:/Users/phuon/Desktop/luat/" + file.name;

  uploadBtn.innerHTML = `<div class="spinner-border spinner-border-sm text-primary"></div>`;

  try {
    const res = await fetch('http://127.0.0.1:5000/them_file', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ duong_dan: fakePath })
    });
    const data = await res.json();

    if (res.ok) {
      alert(data.thong_bao);
    } else {
      alert('Lỗi: ' + data.thong_bao);
    }
  } catch (err) {
    alert('Lỗi: Không thể kết nối tới máy chủ.');
  } finally {
    uploadBtn.innerHTML = '+';
    fileInput.value = '';
  }
});
</script>

</body>
</html>
