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

cursor.execute("""
CREATE TABLE IF NOT EXISTS vanban (
    id INT PRIMARY KEY,
    noidung TEXT,
    vector TEXT
)
""")

# ===== Hàm lấy embedding =====
def get_embedding(text):
    response = client.embeddings.create(
        input=text,
        model="text-embedding-3-small"
    )
    return response.data[0].embedding

# ===== Hàm OCR PDF =====
def ocr_pdf(pdf_path):
    images = convert_from_path(pdf_path, poppler_path=poppler_path)
    full_text = ""
    for i, image in enumerate(images):
        text = pytesseract.image_to_string(image, lang="vie")
        full_text += f"\n--- Trang {i+1} ---\n{text}\n"
    return full_text

# ===== Hàm xử lý thêm dữ liệu mới từ file =====
def xu_ly_file_moi(path_file):
    extension = os.path.splitext(path_file)[1].lower()

    if extension == ".docx":
        doc = Document(path_file)
        text_full = "\n".join(para.text.strip() for para in doc.paragraphs if para.text.strip())
    elif extension == ".pdf":
        text_full = ocr_pdf(path_file)
    else:
        raise ValueError("Định dạng file không hỗ trợ. Chỉ hỗ trợ .docx hoặc .pdf")

    with open(du_lieu_txt, "a", encoding="utf-8") as f:
        f.write(f"\n\n=== FILE MỚI: {os.path.basename(path_file)} ===\n{text_full}")

    cursor.execute("SELECT MAX(id) FROM vanban")
    result = cursor.fetchone()
    id_count = result[0] + 1 if result[0] else 1

    start = 0
    while start < len(text_full):
        chunk = text_full[start:start+chunk_size]
        if chunk.strip():
            vector = get_embedding(chunk)
            index.add_with_ids(np.array([vector], dtype=np.float32), np.array([id_count]))
            cursor.execute("INSERT INTO vanban (id, noidung, vector) VALUES (%s, %s, %s)", (id_count, chunk, json.dumps(vector)))
            conn.commit()
            id_count += 1
        start += chunk_size - chunk_overlap

    faiss.write_index(index, path_faiss)

# ===== Load hoặc khởi tạo FAISS chính =====
os.makedirs(os.path.dirname(path_faiss), exist_ok=True)
if os.path.exists(path_faiss):
    index = faiss.read_index(path_faiss)
else:
    index = faiss.IndexIDMap(faiss.IndexFlatL2(1536))

# ===== Load hoặc khởi tạo FAISS lịch sử =====
def check_index_idmap(index):
    return isinstance(index, faiss.IndexIDMap) or isinstance(index, faiss.IndexIDMap2)

os.makedirs(os.path.dirname(path_faiss_history), exist_ok=True)
if os.path.exists(path_faiss_history):
    index_history = faiss.read_index(path_faiss_history)
    if not check_index_idmap(index_history):
        index_history = faiss.IndexIDMap(faiss.IndexFlatL2(1536))
        faiss.write_index(index_history, path_faiss_history)
else:
    index_history = faiss.IndexIDMap(faiss.IndexFlatL2(1536))
    faiss.write_index(index_history, path_faiss_history)

# ===== Load lịch sử JSON =====
if os.path.exists(path_history_json):
    with open(path_history_json, "r", encoding="utf-8") as f:
        history = json.load(f)
else:
    history = {}

# ===== API hỏi đáp =====
@app.route("/hoi_dap", methods=["POST"])
def hoi_dap():
    try:
        data = request.get_json()
        question = data.get("cau_hoi", "").strip()

        if not question:
            return jsonify({"tra_loi": "Vui lòng nhập câu hỏi."}), 400

        # Lấy vector embedding cho câu hỏi
        vec_q = get_embedding(question)
        vec_q_np = np.array([vec_q], dtype=np.float32)

        # Kiểm tra lịch sử câu hỏi
        if index_history.ntotal > 0:
            D_his, I_his = index_history.search(vec_q_np, 1)
            sim = 1 - (D_his[0][0] / 2)  # FAISS dùng khoảng cách, cần chuyển sang tương đồng
            if sim >= similarity_threshold:
                tra_loi_lich_su = history.get(str(int(I_his[0][0])), "")
                if tra_loi_lich_su:
                    return jsonify({"tra_loi": tra_loi_lich_su, "nguon": "lich_su"})

        # Tìm dữ liệu gần nhất từ FAISS chính
        D, I = index.search(vec_q_np, 5)
        content = ""

        for id_chunk in I[0]:
            if id_chunk != -1:
                cursor.execute("SELECT noidung FROM vanban WHERE id = %s", (int(id_chunk),))
                row = cursor.fetchone()
                if row and row[0]:
                    content += row[0] + "\n"

        # Kiểm tra nếu không có nội dung phù hợp
        if not content.strip():
            return jsonify({"tra_loi": "Không tìm thấy dữ liệu phù hợp."})

        # Tạo prompt từ nội dung tìm được
        prompt = f"""
Dựa trên thông tin sau, hãy trả lời câu hỏi. Chỉ dùng dữ liệu cung cấp:
{content}
Câu hỏi: {question}
"""

        # Gọi OpenAI để lấy câu trả lời
        res = client.chat.completions.create(
            model="gpt-4o-mini-2024-07-18",
            messages=[{"role": "user", "content": prompt}]
        )
        answer = res.choices[0].message.content.strip()

        # Lưu vào lịch sử
        idx_his = index_history.ntotal + 1
        index_history.add_with_ids(vec_q_np, np.array([idx_his]))
        history[str(idx_his)] = answer

        faiss.write_index(index_history, path_faiss_history)
        with open(path_history_json, "w", encoding="utf-8") as f:
            json.dump(history, f, ensure_ascii=False, indent=2)

        return jsonify({"tra_loi": answer})

    except Exception as e:
        print(f"[Lỗi hệ thống]: {e}")  # Log ra terminal cho dễ debug
        return jsonify({"tra_loi": f"Lỗi: {str(e)}"}), 500

# ===== API thêm file mới =====
@app.route("/them_file", methods=["POST"])
def them_file():
    try:
        data = request.get_json()
        path_file_moi = data.get("duong_dan", "").strip()
        if not path_file_moi or not os.path.exists(path_file_moi):
            return jsonify({"thong_bao": "File không tồn tại."}), 400

        xu_ly_file_moi(path_file_moi)
        return jsonify({"thong_bao": "Đã thêm dữ liệu từ file mới thành công."})
    except Exception as e:
        return jsonify({"thong_bao": f"Lỗi: {str(e)}"}), 500

# ===== Khởi động Server =====
if __name__ == "__main__":
    app.run(debug=True)
