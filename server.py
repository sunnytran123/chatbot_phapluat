import os
import json
import faiss
import numpy as np
import mysql.connector
from flask import Flask, request, jsonify
from flask_cors import CORS
from docx import Document
import pytesseract
from pdf2image import convert_from_path
from openai import OpenAI
import bcrypt
from datetime import datetime

# ===== Cấu hình =====
client = OpenAI(api_key="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")

app = Flask(__name__)
CORS(app)

# ===== Đường dẫn dữ liệu =====
du_lieu_txt = r"C:\Users\phuon\Desktop\luat\toanvanbanpdf.txt"
path_faiss = r"D:\python\chatbot_phapluat\faiss_data\vanban.index"
path_faiss_history = r"D:\python\chatbot_phapluat\faiss_data\lichsu.index"
path_history_json = r"D:\python\chatbot_phapluat\faiss_data\lichsu.json"
poppler_path = r"C:\poppler\Release-24.08.0-0\poppler-24.08.0\Library\bin"
pytesseract.pytesseract.tesseract_cmd = r"C:\Program Files\Tesseract-OCR\tesseract.exe"

similarity_threshold = 0.85
chunk_size = 2500
chunk_overlap = 300

# ===== Kết nối MySQL =====
conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="chatbot_phapluat"
)
cursor = conn.cursor()

# ===== Tạo bảng nếu chưa có =====
cursor.execute("""
CREATE TABLE IF NOT EXISTS vanban (
    id INT PRIMARY KEY,
    noidung TEXT,
    vector TEXT
)
""")

cursor.execute("""
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255)
)
""")

cursor.execute("""
CREATE TABLE IF NOT EXISTS chat_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    conversation_id INT,
    role ENUM('user', 'ai'),
    content TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)
""")
conn.commit()

# ===== Hàm lấy embedding =====
def get_embedding(text):
    response = client.embeddings.create(input=text, model="text-embedding-3-small")
    return response.data[0].embedding

# ===== Hàm OCR PDF =====
def ocr_pdf(pdf_path):
    images = convert_from_path(pdf_path, poppler_path=poppler_path)
    return "\n".join(pytesseract.image_to_string(img, lang="vie") for img in images)

# ===== Xử lý thêm dữ liệu từ file =====
def xu_ly_file_moi(path_file):
    ext = os.path.splitext(path_file)[1].lower()
    if ext == ".docx":
        text_full = "\n".join(p.text for p in Document(path_file).paragraphs if p.text.strip())
    elif ext == ".pdf":
        text_full = ocr_pdf(path_file)
    else:
        raise ValueError("Chỉ hỗ trợ file .docx hoặc .pdf")

    with open(du_lieu_txt, "a", encoding="utf-8") as f:
        f.write(f"\n\n=== FILE MỚI: {os.path.basename(path_file)} ===\n{text_full}")

    cursor.execute("SELECT MAX(id) FROM vanban")
    id_count = (cursor.fetchone()[0] or 0) + 1

    start = 0
    while start < len(text_full):
        chunk = text_full[start:start + chunk_size]
        if chunk.strip():
            vector = get_embedding(chunk)
            index.add_with_ids(np.array([vector], dtype=np.float32), np.array([id_count]))
            cursor.execute("INSERT INTO vanban (id, noidung, vector) VALUES (%s, %s, %s)",
                           (id_count, chunk, json.dumps(vector)))
            conn.commit()
            id_count += 1
        start += chunk_size - chunk_overlap

    faiss.write_index(index, path_faiss)

# ===== FAISS chính =====
os.makedirs(os.path.dirname(path_faiss), exist_ok=True)
index = faiss.read_index(path_faiss) if os.path.exists(path_faiss) else faiss.IndexIDMap(faiss.IndexFlatL2(1536))

# ===== FAISS lịch sử =====
os.makedirs(os.path.dirname(path_faiss_history), exist_ok=True)
index_history = faiss.read_index(path_faiss_history) if os.path.exists(path_faiss_history) else faiss.IndexIDMap(faiss.IndexFlatL2(1536))

# ===== Lịch sử JSON =====
history = json.load(open(path_history_json, encoding="utf-8")) if os.path.exists(path_history_json) else {}

# ===== Lưu lịch sử vào MySQL =====
def luu_lich_su(user_id, role, content, conversation_id):
    cursor.execute("INSERT INTO chat_history (user_id, conversation_id, role, content) VALUES (%s, %s, %s, %s)",
                   (user_id, conversation_id, role, content))
    conn.commit()

# ===== API hỏi đáp =====
@app.route("/hoi_dap", methods=["POST"])
def hoi_dap():
    try:
        data = request.get_json()
        question = data.get("cau_hoi", "").strip()
        user_id = data.get("user_id")
        conversation_id = data.get("conversation_id")

        if not question or not user_id:
            return jsonify({"tra_loi": "Thiếu thông tin."}), 400

        if not conversation_id:
            cursor.execute("SELECT MAX(conversation_id) FROM chat_history WHERE user_id=%s", (user_id,))
            res = cursor.fetchone()
            conversation_id = (res[0] if res and res[0] else 0) + 1

        vec_q = np.array([get_embedding(question)], dtype=np.float32)

        if index_history.ntotal > 0:
            D_his, I_his = index_history.search(vec_q, 1)
            if 1 - (D_his[0][0] / 2) >= similarity_threshold:
                answer = history.get(str(int(I_his[0][0])), "")
                luu_lich_su(user_id, "user", question, conversation_id)
                luu_lich_su(user_id, "ai", answer, conversation_id)
                return jsonify({"tra_loi": answer, "conversation_id": conversation_id, "nguon": "lich_su"})

        content = ""
        D, I = index.search(vec_q, 5)
        for idx in I[0]:
            if idx != -1:
                cursor.execute("SELECT noidung FROM vanban WHERE id=%s", (int(idx),))
                row = cursor.fetchone()
                if row and row[0]:
                    content += row[0] + "\n"

        if not content.strip():
            return jsonify({"tra_loi": "Không tìm thấy dữ liệu.", "conversation_id": conversation_id})

        prompt = f"""
Dựa trên thông tin sau, hãy trả lời câu hỏi. Chỉ dùng dữ liệu cung cấp:
{content}
Câu hỏi: {question}
"""
        res = client.chat.completions.create(model="gpt-4o-mini-2024-07-18", messages=[{"role": "user", "content": prompt}])
        answer = res.choices[0].message.content.strip()

        idx_his = index_history.ntotal + 1
        index_history.add_with_ids(vec_q, np.array([idx_his]))
        history[str(idx_his)] = answer
        faiss.write_index(index_history, path_faiss_history)
        json.dump(history, open(path_history_json, "w", encoding="utf-8"), ensure_ascii=False, indent=2)

        luu_lich_su(user_id, "user", question, conversation_id)
        luu_lich_su(user_id, "ai", answer, conversation_id)

        return jsonify({"tra_loi": answer, "conversation_id": conversation_id})

    except Exception as e:
        return jsonify({"tra_loi": f"Lỗi: {str(e)}"}), 500

# ===== API thêm file =====
@app.route("/them_file", methods=["POST"])
def them_file():
    try:
        path_file = request.get_json().get("duong_dan", "").strip()
        if not os.path.exists(path_file):
            return jsonify({"thong_bao": "File không tồn tại."}), 400

        xu_ly_file_moi(path_file)
        return jsonify({"thong_bao": "Đã thêm dữ liệu từ file thành công."})

    except Exception as e:
        return jsonify({"thong_bao": f"Lỗi: {str(e)}"}), 500

# ===== Đăng ký =====
@app.route("/dang_ky", methods=["POST"])
def dang_ky():
    data = request.get_json()
    username, password = data.get("username", "").strip(), data.get("password", "").strip()
    if not username or not password:
        return jsonify({"thong_bao": "Thiếu thông tin."}), 400
    cursor.execute("SELECT id FROM users WHERE username=%s", (username,))
    if cursor.fetchone():
        return jsonify({"thong_bao": "Tài khoản đã tồn tại."}), 400
    hashed_pw = bcrypt.hashpw(password.encode(), bcrypt.gensalt()).decode()
    cursor.execute("INSERT INTO users (username, password) VALUES (%s, %s)", (username, hashed_pw))
    conn.commit()
    return jsonify({"thong_bao": "Đăng ký thành công."})

# ===== Đăng nhập =====
@app.route("/dang_nhap", methods=["POST"])
def dang_nhap():
    data = request.get_json()
    username, password = data.get("username", "").strip(), data.get("password", "").strip()
    cursor.execute("SELECT id, password FROM users WHERE username=%s", (username,))
    row = cursor.fetchone()
    if not row or not bcrypt.checkpw(password.encode(), row[1].encode()):
        return jsonify({"thong_bao": "Sai tài khoản hoặc mật khẩu."}), 400
    return jsonify({"thong_bao": "Đăng nhập thành công.", "user_id": row[0]})

# ===== Lấy lịch sử =====
@app.route("/lich_su/<int:user_id>", methods=["GET"])
def lich_su(user_id):
    cursor.execute("""
    SELECT conversation_id, role, content, created_at 
    FROM chat_history WHERE user_id=%s ORDER BY conversation_id, created_at
    """, (user_id,))
    rows, conversations = cursor.fetchall(), {}
    for conv_id, role, content, created_at in rows:
        conversations.setdefault(conv_id, []).append({"role": role, "content": content, "time": created_at.strftime("%H:%M:%S %d/%m/%Y")})
    return jsonify({"lich_su": [{"conversation_id": k, "messages": v} for k, v in conversations.items()]})

# ===== Run server =====
if __name__ == "__main__":
    app.run(debug=True)
