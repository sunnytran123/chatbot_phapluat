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

# ===== Đường dẫn =====
duongdan_congkhai = r"D:\python\chatbot_phapluat\faiss_data\vanban.index"
duongdan_mat = r"D:\python\chatbot_phapluat\faiss_data\mat.index"
pytesseract.pytesseract.tesseract_cmd = r"C:\Program Files\Tesseract-OCR\tesseract.exe"
poppler_path = r"C:\poppler\bin"
chunk_size, chunk_overlap = 2500, 300

# ===== FAISS =====
index_congkhai = faiss.read_index(duongdan_congkhai) if os.path.exists(duongdan_congkhai) else faiss.IndexIDMap(
    faiss.IndexFlatL2(1536))
index_mat = faiss.read_index(duongdan_mat) if os.path.exists(duongdan_mat) else faiss.IndexIDMap(
    faiss.IndexFlatL2(1536))

# ===== MySQL =====
conn = mysql.connector.connect(host="localhost", user="root", password="", database="chatbot_phapluat")
cursor = conn.cursor()

# ===== Tạo bảng nếu chưa có =====
cursor.execute("""
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    role ENUM('canbo', 'nguoidan') DEFAULT 'nguoidan'
)
""")
cursor.execute("""
CREATE TABLE IF NOT EXISTS vanban (
    id INT PRIMARY KEY,
    noidung TEXT,
    vector TEXT
)
""")
cursor.execute("""
CREATE TABLE IF NOT EXISTS vanban_mat (
    id INT PRIMARY KEY,
    noidung TEXT,
    vector TEXT
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


# ===== Hàm phụ =====
def get_embedding(text):
    response = client.embeddings.create(input=text, model="text-embedding-3-small")
    return response.data[0].embedding


def ocr_pdf(path):
    images = convert_from_path(path, poppler_path=poppler_path)
    return "\n".join(pytesseract.image_to_string(img, lang="vie") for img in images)


def luu_lich_su(user_id, role, content, conversation_id):
    cursor.execute("INSERT INTO chat_history (user_id, conversation_id, role, content) VALUES (%s, %s, %s, %s)",
                   (user_id, conversation_id, role, content))
    conn.commit()


# ===== API =====

@app.route("/dang_ky", methods=["POST"])
def dang_ky():
    data = request.get_json()
    username = data.get("username")
    password = data.get("password")  # Đã mã hóa từ PHP
    role = data.get("role", "nguoidan")

    if not username or not password:
        return jsonify({"thong_bao": "Thiếu thông tin."}), 400

    cursor.execute("SELECT id FROM users WHERE username=%s", (username,))
    if cursor.fetchone():
        return jsonify({"thong_bao": "Tài khoản đã tồn tại."}), 400

    cursor.execute("INSERT INTO users (username, password, role) VALUES (%s, %s, %s)", (username, password, role))
    conn.commit()
    return jsonify({"thong_bao": "Đăng ký thành công."})



@app.route("/dang_nhap", methods=["POST"])
def dang_nhap():
    data = request.get_json()
    username = data.get("username")

    cursor.execute("SELECT id, password, role FROM users WHERE username=%s", (username,))
    row = cursor.fetchone()
    if not row:
        return jsonify({"thong_bao": "Sai tài khoản hoặc mật khẩu."}), 400

    return jsonify({
        "user_id": row[0],
        "hashed_password": row[1],
        "role": row[2]
    })



@app.route("/them_file", methods=["POST"])
def them_file():
    data = request.get_json()
    duongdan = data.get("duong_dan")
    loai = data.get("loai", "congkhai")
    user_id = data.get("user_id")

    # Kiểm tra quyền
    cursor.execute("SELECT role FROM users WHERE id=%s", (user_id,))
    row = cursor.fetchone()
    if not row or row[0] != "canbo":
        return jsonify({"thong_bao": "Chỉ cán bộ mới có quyền thêm tài liệu."}), 403

    if not os.path.exists(duongdan):
        return jsonify({"thong_bao": "File không tồn tại."}), 400

    if duongdan.endswith(".pdf"):
        text = ocr_pdf(duongdan)
    elif duongdan.endswith(".docx"):
        text = "\n".join(p.text for p in Document(duongdan).paragraphs)
    else:
        return jsonify({"thong_bao": "Chỉ hỗ trợ file PDF hoặc DOCX."}), 400

    if loai == "mat":
        cursor.execute("SELECT MAX(id) FROM vanban_mat")
        id_count = (cursor.fetchone()[0] or 0) + 1
        for i in range(0, len(text), chunk_size - chunk_overlap):
            chunk = text[i:i + chunk_size].strip()
            if chunk:
                vector = get_embedding(chunk)
                index_mat.add_with_ids(np.array([vector], dtype=np.float32), np.array([id_count]))
                cursor.execute("INSERT INTO vanban_mat (id, noidung, vector) VALUES (%s, %s, %s)",
                               (id_count, chunk, json.dumps(vector)))
                id_count += 1
        faiss.write_index(index_mat, duongdan_mat)
    else:
        cursor.execute("SELECT MAX(id) FROM vanban")
        id_count = (cursor.fetchone()[0] or 0) + 1
        for i in range(0, len(text), chunk_size - chunk_overlap):
            chunk = text[i:i + chunk_size].strip()
            if chunk:
                vector = get_embedding(chunk)
                index_congkhai.add_with_ids(np.array([vector], dtype=np.float32), np.array([id_count]))
                cursor.execute("INSERT INTO vanban (id, noidung, vector) VALUES (%s, %s, %s)",
                               (id_count, chunk, json.dumps(vector)))
                id_count += 1
        faiss.write_index(index_congkhai, duongdan_congkhai)

    conn.commit()
    return jsonify({"thong_bao": "Thêm dữ liệu thành công."})


@app.route("/hoi_dap", methods=["POST"])
def hoi_dap():
    data = request.get_json()
    cau_hoi = data.get("cau_hoi")
    user_id = data.get("user_id")
    conversation_id = data.get("conversation_id")

    if not cau_hoi or not user_id:
        return jsonify({"tra_loi": "Thiếu thông tin."}), 400

    # Lấy quyền
    cursor.execute("SELECT role FROM users WHERE id=%s", (user_id,))
    row = cursor.fetchone()
    if not row:
        return jsonify({"tra_loi": "Người dùng không tồn tại."}), 400
    role = row[0]

    if not conversation_id:
        cursor.execute("SELECT MAX(conversation_id) FROM chat_history WHERE user_id=%s", (user_id,))
        res = cursor.fetchone()
        conversation_id = (res[0] if res and res[0] else 0) + 1

    vector_cauhoi = np.array([get_embedding(cau_hoi)], dtype=np.float32)
    content = ""

    # Tìm dữ liệu công khai
    D, I = index_congkhai.search(vector_cauhoi, 5)
    for idx in I[0]:
        if idx != -1:
            cursor.execute("SELECT noidung FROM vanban WHERE id=%s", (int(idx),))
            row = cursor.fetchone()
            if row:
                content += row[0] + "\n"

    # Nếu là cán bộ được xem thêm dữ liệu mật
    if role == "canbo":
        Dm, Im = index_mat.search(vector_cauhoi, 5)
        for idx in Im[0]:
            if idx != -1:
                cursor.execute("SELECT noidung FROM vanban_mat WHERE id=%s", (int(idx),))
                row = cursor.fetchone()
                if row:
                    content += row[0] + "\n"

    if not content.strip():
        return jsonify({"tra_loi": "Không tìm thấy dữ liệu.", "conversation_id": conversation_id})

    prompt = f"Dựa trên thông tin sau, hãy trả lời câu hỏi:\n{content}\nCâu hỏi: {cau_hoi}"
    res = client.chat.completions.create(model="gpt-4o-mini-2024-07-18", messages=[{"role": "user", "content": prompt}])
    answer = res.choices[0].message.content.strip()

    luu_lich_su(user_id, "user", cau_hoi, conversation_id)
    luu_lich_su(user_id, "ai", answer, conversation_id)

    return jsonify({"tra_loi": answer, "conversation_id": conversation_id})


@app.route("/lich_su/<int:user_id>", methods=["GET"])
def lich_su(user_id):
    cursor.execute("""
    SELECT conversation_id, role, content, created_at 
    FROM chat_history WHERE user_id=%s ORDER BY conversation_id, created_at
    """, (user_id,))
    rows = cursor.fetchall()
    conversations = {}
    for conv_id, role, content, created_at in rows:
        conversations.setdefault(conv_id, []).append({
            "role": role,
            "content": content,
            "time": created_at.strftime("%H:%M:%S %d/%m/%Y")
        })
    return jsonify({"lich_su": [{"conversation_id": k, "messages": v} for k, v in conversations.items()]})


if __name__ == "__main__":
    app.run(debug=True)
