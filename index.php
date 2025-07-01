<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trợ lý ảo Hỏi đáp Pháp luật</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="min-vh-100 d-flex flex-column align-items-center justify-content-center p-4">
    <h1 class="h3 fw-bold text-primary mb-4">Trợ lý ảo Hỏi đáp Pháp luật</h1>
    
    <div class="w-100" style="max-width: 672px;">
      <div class="card shadow">
        <div class="card-body p-4">
          
          <div id="responseArea" class="border rounded p-3 mb-3 bg-light" style="height: 384px; overflow-y: auto;">
            <p class="text-muted fst-italic">Câu trả lời sẽ hiển thị ở đây...</p>
          </div>
          
          <div class="d-flex flex-column flex-sm-row gap-2">
            <textarea id="userInput" class="form-control" rows="3" placeholder="Nhập câu hỏi về quy định pháp luật..."></textarea>
            <div class="d-flex gap-2">
              <button id="sendButton" class="btn btn-primary">Gửi</button>
              <button id="clearButton" class="btn btn-outline-secondary">Xóa</button>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const userInput = document.getElementById('userInput');
const sendButton = document.getElementById('sendButton');
const clearButton = document.getElementById('clearButton');
const responseArea = document.getElementById('responseArea');

sendButton.addEventListener('click', async () => {
  const question = userInput.value.trim();
  if (!question) return alert('Vui lòng nhập câu hỏi!');

  responseArea.innerHTML += `<p class="text-primary fw-semibold mb-2">Bạn: ${question}</p>`;

  try {
    const res = await fetch('http://127.0.0.1:5000/hoi_dap', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cau_hoi: question })
    });
    const data = await res.json();

    responseArea.innerHTML += `<p class="text-dark mb-3">Trợ lý: ${data.tra_loi || 'Không có câu trả lời phù hợp.'}</p>`;
  } catch (err) {
    responseArea.innerHTML += `<p class="text-danger mb-3">Lỗi: Không thể kết nối tới máy chủ.</p>`;
  }

  responseArea.scrollTop = responseArea.scrollHeight;
  userInput.value = '';
});

clearButton.addEventListener('click', () => {
  userInput.value = '';
  responseArea.innerHTML = '<p class="text-muted fst-italic">Câu trả lời sẽ hiển thị ở đây...</p>';
});
</script>

</body>
</html>
