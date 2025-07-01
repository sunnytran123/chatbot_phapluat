<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trợ lý ảo Hỏi đáp Pháp luật</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
  <div class="min-vh-100 d-flex flex-column align-items-center justify-content-center p-4">
    <!-- Header -->
    <h1 class="h3 fw-bold text-primary mb-4">Trợ lý ảo Hỏi đáp Pháp luật</h1>
    
    <!-- Chat Container -->
    <div class="w-100" style="max-width: 672px;">
      <div class="card shadow">
        <div class="card-body p-4">
          <!-- Response Area -->
          <div id="responseArea" class="border rounded p-3 mb-3 bg-light" style="height: 384px; overflow-y: auto;">
            <p class="text-muted fst-italic">Câu trả lời sẽ hiển thị ở đây...</p>
          </div>
          
          <!-- Input Area -->
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

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script>
    const userInput = document.getElementById('userInput');
    const sendButton = document.getElementById('sendButton');
    const clearButton = document.getElementById('clearButton');
    const responseArea = document.getElementById('responseArea');

    // Xử lý sự kiện nút Gửi
    sendButton.addEventListener('click', async () => {
      const question = userInput.value.trim();
      if (!question) {
        alert('Vui lòng nhập câu hỏi!');
        return;
      }

      // Thêm câu hỏi của người dùng vào khu vực hiển thị
      const userMessage = document.createElement('p');
      userMessage.className = 'text-primary fw-semibold mb-2';
      userMessage.textContent = `Bạn: ${question}`;
      responseArea.appendChild(userMessage);

      // Gửi yêu cầu tới API (giả lập, bạn cần thay bằng API thật)
      try {
        // Ví dụ gọi API (thay bằng endpoint của bạn)
        const response = await fetch('/api/ask', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ question })
        });
        const data = await response.json();

        // Thêm câu trả lời vào khu vực hiển thị
        const botMessage = document.createElement('p');
        botMessage.className = 'text-dark mb-3';
        botMessage.textContent = `Trợ lý: ${data.answer || 'Đang xử lý...'}`;
        responseArea.appendChild(botMessage);
      } catch (error) {
        const errorMessage = document.createElement('p');
        errorMessage.className = 'text-danger mb-3';
        errorMessage.textContent = 'Lỗi: Không thể kết nối đến máy chủ.';
        responseArea.appendChild(errorMessage);
      }

      // Cuộn xuống cuối
      responseArea.scrollTop = responseArea.scrollHeight;
      userInput.value = ''; // Xóa ô nhập
    });

    // Xử lý sự kiện nút Xóa
    clearButton.addEventListener('click', () => {
      userInput.value = '';
      responseArea.innerHTML = '<p class="text-muted fst-italic">Câu trả lời sẽ hiển thị ở đây...</p>';
    });
  </script>
</body>
</html>