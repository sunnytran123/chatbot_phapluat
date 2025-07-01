import os
import json
import faiss
import numpy as np
import mysql.connector
from docx import Document
from openai import OpenAI

# ===== Cấu hình =====
client = OpenAI(api_key="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")

duong_dan_word = r"C:\Users\phuon\Desktop\luat\luat_hinhsu.docx"
duong_dan_txt = r"C:\Users\phuon\Desktop\luat\toanvan.txt"
duong_dan_faiss = r"D:\python\chatbot_phapluat\faiss_data\vanban.index"

# ===== Kết nối MySQL =====
conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="chatbot_phapluat"
)
cursor = conn.cursor()

# Tạo bảng nếu chưa có
cursor.execute("""
CREATE TABLE IF NOT EXISTS vanban (
    id INT PRIMARY KEY,
    noidung TEXT,
    vector TEXT
)
""")

# ===== Xử lý tạo file TXT từ Word =====
if not os.path.exists(duong_dan_txt):
    print("⚠️ Chưa có file TXT, tạo từ file Word...")
    if not os.path.exists(duong_dan_word):
        raise FileNotFoundError(f"Không tìm thấy file Word tại {duong_dan_word}")

    doc = Document(duong_dan_word)
    text_full = ""

    for para in doc.paragraphs:
        if para.text.strip():
            text_full += para.text.strip() + " "

    with open(duong_dan_txt, "w", encoding="utf-8") as f:
        f.write(text_full)

    print(f"✅ Đã lưu toàn văn vào {duong_dan_txt}")
else:
    print(f"✅ Đã có file TXT sẵn: {duong_dan_txt}")

# ===== Đọc lại file TXT =====
with open(duong_dan_txt, "r", encoding="utf-8") as f:
    text_full = f.read()

print(f"✅ Tổng số ký tự: {len(text_full)}")

# ===== Khởi tạo hoặc load FAISS =====
os.makedirs(os.path.dirname(duong_dan_faiss), exist_ok=True)

if os.path.exists(duong_dan_faiss):
    index = faiss.read_index(duong_dan_faiss)
    print("✅ Đã load FAISS từ file")
else:
    index = faiss.IndexIDMap(faiss.IndexFlatL2(1536))  # 1536 chiều
    print("⚠️ Chưa có FAISS, tạo mới...")

# ===== Hàm vector hóa văn bản =====
def get_embedding(text):
    response = client.embeddings.create(
        input=text,
        model="text-embedding-3-small"
    )
    return response.data[0].embedding

# ===== Tách đoạn + Vector hóa + Lưu DB + FAISS =====
chunk_size = 2500
chunk_overlap = 100  #khoảng 300 nếu sửa

start = 0
id_dem = 1

# Đếm số đoạn đã có trong DB để tránh trùng lặp
cursor.execute("SELECT COUNT(*) FROM vanban")
so_dong = cursor.fetchone()[0]

# Nếu chưa có dữ liệu thì tiến hành xử lý
if so_dong == 0 or not os.path.exists(duong_dan_faiss):
    print("⚠️ Chưa có dữ liệu, bắt đầu tách đoạn và lưu...")
    while start < len(text_full):
        end = start + chunk_size
        chunk = text_full[start:end]

        if chunk.strip():
            print(f"\n--- Xử lý đoạn ID {id_dem} ---")
            print(chunk[:200] + " ...")

            vector = get_embedding(chunk)
            vector_np = np.array([vector], dtype=np.float32)

            index.add_with_ids(vector_np, np.array([id_dem]))
            vector_str = json.dumps(vector)

            cursor.execute("INSERT INTO vanban (id, noidung, vector) VALUES (%s, %s, %s)", (id_dem, chunk, vector_str))
            conn.commit()

        start += chunk_size - chunk_overlap
        id_dem += 1

    faiss.write_index(index, duong_dan_faiss)
    print(f"\n✅ Đã lưu FAISS vào {duong_dan_faiss}")
    print(f"✅ Tổng {id_dem - 1} đoạn đã xử lý")
else:
    print("✅ Dữ liệu đã đầy đủ, không cần xử lý lại")

# ===== Hỏi đáp test kết hợp OpenAI trả lời =====
while True:
    cau_hoi = input("\n❓ Nhập câu hỏi test (hoặc 'exit' để thoát): ")
    if cau_hoi.lower() == "exit":
        break

    # Vector hóa câu hỏi
    response = client.embeddings.create(
        input=cau_hoi,
        model="text-embedding-3-small"
    )
    vector_hoi = response.data[0].embedding
    vector_np = np.array([vector_hoi], dtype=np.float32)

    # Tìm top 5
    k = 5
    D, I = index.search(vector_np, k)

    print(f"\n🔎 Top {k} đoạn phù hợp nhất:")

    noi_dung_ghep = ""  # Biến lưu toàn bộ nội dung top 5

    for idx, id_chunk in enumerate(I[0], 1):
        if id_chunk == -1:
            continue

        cursor.execute("SELECT noidung FROM vanban WHERE id = %s", (int(id_chunk),))
        row = cursor.fetchone()

        if row:
            print(f"\n--- Đoạn {idx} (ID {id_chunk}) ---")
            print(row[0][:500] + " ...")  # In giới hạn 500 ký tự đầu tiên
            noi_dung_ghep += row[0] + "\n"

    if not noi_dung_ghep.strip():
        print("⚠️ Không tìm thấy dữ liệu phù hợp.")
        continue

    # Ghép vào prompt và gọi OpenAI GPT trả lời
    prompt = f"""
Dựa trên các thông tin sau đây, hãy trả lời câu hỏi dưới đây. Chỉ sử dụng thông tin từ tài liệu được cung cấp để trả lời, không cần tìm kiếm thêm dữ liệu bên ngoài. Trả lời ngắn gọn, chính xác và đúng trọng tâm.
{noi_dung_ghep}
Câu hỏi: {cau_hoi}
"""

    response = client.chat.completions.create(
        model="gpt-4o-mini-2024-07-18",
        messages=[
            {"role": "user", "content": prompt}
        ]
    )

    tra_loi = response.choices[0].message.content
    print(f"\n✅ Trợ lý trả lời: {tra_loi}\n")
