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
duong_dan_lich_su_faiss = r"D:\python\chatbot_phapluat\faiss_data\lichsu.index"
duong_dan_lich_su_json = r"D:\python\chatbot_phapluat\faiss_data\lichsu.json"

nguong_trung_khop = 0.85  # 85%

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

# ===== Tạo file TXT từ Word =====
if not os.path.exists(duong_dan_txt):
    print("⚠️ Chưa có file TXT, tạo từ Word...")
    if not os.path.exists(duong_dan_word):
        raise FileNotFoundError(f"Không tìm thấy file Word tại {duong_dan_word}")

    doc = Document(duong_dan_word)
    text_full = " ".join(para.text.strip() for para in doc.paragraphs if para.text.strip())

    with open(duong_dan_txt, "w", encoding="utf-8") as f:
        f.write(text_full)
    print("✅ Đã tạo file TXT.")
else:
    with open(duong_dan_txt, "r", encoding="utf-8") as f:
        text_full = f.read()
    print("✅ Đã có file TXT.")

print(f"✅ Tổng ký tự trong dữ liệu: {len(text_full)}")

# ===== Load hoặc khởi tạo FAISS chính =====
os.makedirs(os.path.dirname(duong_dan_faiss), exist_ok=True)
if os.path.exists(duong_dan_faiss):
    index = faiss.read_index(duong_dan_faiss)
    print("✅ Đã load FAISS dữ liệu chính.")
else:
    index = faiss.IndexIDMap(faiss.IndexFlatL2(1536))
    print("⚠️ Chưa có FAISS dữ liệu chính, tạo mới.")

# ===== Load hoặc khởi tạo FAISS lịch sử =====
os.makedirs(os.path.dirname(duong_dan_lich_su_faiss), exist_ok=True)
if os.path.exists(duong_dan_lich_su_faiss):
    index_lichsu = faiss.read_index(duong_dan_lich_su_faiss)
    print("✅ Đã load FAISS lịch sử.")
else:
    index_lichsu = faiss.IndexIDMap(faiss.IndexFlatL2(1536))
    print("⚠️ Chưa có FAISS lịch sử, tạo mới.")

# ===== Load lịch sử JSON =====
if os.path.exists(duong_dan_lich_su_json):
    with open(duong_dan_lich_su_json, "r", encoding="utf-8") as f:
        lich_su = json.load(f)
else:
    lich_su = {}

# ===== Xử lý dữ liệu nếu chưa có =====
chunk_size = 2500
chunk_overlap = 300

cursor.execute("SELECT COUNT(*) FROM vanban")
so_dong = cursor.fetchone()[0]

if so_dong == 0 or not os.path.exists(duong_dan_faiss):
    print("⚠️ Chưa có dữ liệu, bắt đầu xử lý...")
    start = 0
    id_dem = 1

    while start < len(text_full):
        chunk = text_full[start:start + chunk_size]
        if chunk.strip():
            print(f"🔧 Đoạn {id_dem}: {chunk[:100]}...")
            vector = get_embedding(chunk)
            vector_np = np.array([vector], dtype=np.float32)

            index.add_with_ids(vector_np, np.array([id_dem]))
            vector_str = json.dumps(vector)
            cursor.execute("INSERT INTO vanban (id, noidung, vector) VALUES (%s, %s, %s)", (id_dem, chunk, vector_str))
            conn.commit()

        start += chunk_size - chunk_overlap
        id_dem += 1

    faiss.write_index(index, duong_dan_faiss)
    print("✅ Đã lưu FAISS dữ liệu chính.")

# ===== Vòng lặp hỏi đáp =====
while True:
    cau_hoi = input("\n❓ Nhập câu hỏi (hoặc 'exit' để thoát): ")
    if cau_hoi.lower() == "exit":
        break

    vector_hoi = get_embedding(cau_hoi)
    vector_np = np.array([vector_hoi], dtype=np.float32)

    # ===== Kiểm tra lịch sử trước =====
    if index_lichsu.ntotal > 0:
        D_lichsu, I_lichsu = index_lichsu.search(vector_np, 1)
        khoang_cach = D_lichsu[0][0]
        do_tuong_dong = 1 - (khoang_cach / 2)  # Chuẩn hóa giá trị

        if do_tuong_dong >= nguong_trung_khop:
            id_ls = int(I_lichsu[0][0])
            cau_tra_loi = lich_su.get(str(id_ls), "")
            print(f"\n🕑 Đã tìm thấy câu trả lời từ lịch sử (Tương đồng {do_tuong_dong*100:.2f}%):\n{cau_tra_loi}\n")
            continue

    # ===== Tìm dữ liệu chính nếu lịch sử chưa có =====
    k = 5
    D, I = index.search(vector_np, k)

    noi_dung_ghep = ""
    for id_chunk in I[0]:
        if id_chunk == -1:
            continue
        cursor.execute("SELECT noidung FROM vanban WHERE id = %s", (int(id_chunk),))
        row = cursor.fetchone()
        if row:
            noi_dung_ghep += row[0] + "\n"

    if not noi_dung_ghep.strip():
        print("⚠️ Không tìm thấy dữ liệu phù hợp.")
        continue

    prompt = f"""
Dựa trên các thông tin sau, hãy trả lời câu hỏi. Chỉ dùng thông tin cung cấp, không thêm dữ liệu ngoài:
{noi_dung_ghep}
Câu hỏi: {cau_hoi}
"""

    response = client.chat.completions.create(
        model="gpt-4o-mini-2024-07-18",
        messages=[{"role": "user", "content": prompt}]
    )
    tra_loi = response.choices[0].message.content
    print(f"\n✅ Trợ lý trả lời:\n{tra_loi}\n")

    # ===== Lưu vào lịch sử =====
    id_lich_su = index_lichsu.ntotal + 1
    vector_np_ls = np.array([vector_hoi], dtype=np.float32)

    index_lichsu.add_with_ids(vector_np_ls, np.array([id_lich_su]))
    lich_su[str(id_lich_su)] = tra_loi

    # Lưu FAISS lịch sử và file JSON
    faiss.write_index(index_lichsu.index, duong_dan_lich_su_faiss)
    with open(duong_dan_lich_su_json, "w", encoding="utf-8") as f:
        json.dump(lich_su, f, ensure_ascii=False, indent=2)

    print("💾 Đã lưu câu hỏi và câu trả lời vào lịch sử.")
