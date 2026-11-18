# 🎓 Soal Generator Lokal (Laravel 12) + Multi-Materi Tanpa Batas + Skill Anti-Slop

Generator soal pembelajaran yang berjalan **100% lokal** di komputer Anda, dirancang khusus dengan framework **Laravel 12**. Mendukung input materi **lebih dari 1 (Multi-Materi) tanpa batasan jumlah berkas atau panjang teks**, berbagai format berkas nyata: **DOC, DOCX, PDF, PPT, PPTX, XLS, XLSX, CSV, RAR, ZIP, hingga Gambar (OCR)**, penentuan **persentase porsi materi yang masuk (1–100%)**, hingga **100 butir soal**, serta penegakan **Skill Anti-Slop Pedagogis**.

---

## 🚀 Fitur Baru: Multi-Materi Tanpa Batas (Unlimited)

1. **Unggah Banyak Berkas Sekaligus (Multi-File)**:
   - Anda dapat memilih 2, 5, 10, atau puluhan dokumen berbeda sekaligus (misal: gabungan antara `modul.pdf`, `catatan.docx`, `slide.pptx`, dan `tabel.csv`).
   - Sistem secara otomatis mengekstrak semua teks di dalamnya dan menggabungkannya menjadi satu korpus materi utuh.
2. **Tambah Bab / Catatan Teks Tanpa Batas**:
   - Tersedia tombol **"+ Tambah Bab Teks Lain"** di antarmuka web untuk memasukkan banyak sesi/bab teks terpisah tanpa batas panjang kata.
3. **Porsi Materi (% Coverage) Lintas Seluruh Dokumen**:
   - Slider persentase materi akan mendistribusikan sampling representatif ke seluruh dokumen yang diunggah secara proporsional.
4. **Bebas Batasan Memori & Waktu**:
   - Backend diatur tanpa batas waktu eksekusi (`max_execution_time = 0`, `memory_limit = -1`) sehingga aman memproses buku tebal atau puluhan dokumen sekaligus.

---

## 📁 Daftar Format Berkas yang Didukung

| Kategori | Format Ekstensi | Metode Ekstraksi Lokal |
| :--- | :--- | :--- |
| **Dokumen PDF** | `.pdf` | Parser teks PDF internal (`smalot/pdfparser`) |
| **Dokumen Word** | `.docx`, `.doc` | Parsing OpenXML Document (`ZipArchive` & XML) + binary stream extractor |
| **Presentasi PowerPoint** | `.pptx`, `.ppt` | Parsing slide XML per lembar presentasi |
| **Spreadsheet Excel** | `.xlsx`, `.xls`, `.csv` | Parsing sharedStrings XML & tabular delimiter |
| **Arsip Terkompresi** | `.zip`, `.rar` | Dekompresi arsip dan pemindaian rekursif semua dokumen materi di dalamnya |
| **Gambar & Infografis** | `.png`, `.jpg`, `.jpeg`, `.webp`, `.bmp` | **Windows Native OCR Engine** bawaan (tanpa internet) atau **Local Ollama Vision** |
| **Teks Mentah** | `.txt`, `.md`, `.rtf`, `.json` | Ekstraksi teks langsung |

---

## ⚙️ Pilihan Mesin Generator Lokal

- **⚡ Local Semantic Heuristic**: Menitikberatkan kausalitas (sebab-akibat), fungsi, dan konsep esensial.
- **🧠 Local Bloom Taxonomy Engine**: Berbasis matriks Taksonomi Bloom (C2 Pemahaman, C3 Penerapan, C4 Analisis Kritis, C5 Evaluasi).
- **🏢 Local Case-Study & Scenario**: Menghasilkan soal berbasis simulasi kasus nyata dan mitigasi operasional.
- **🔄 Hybrid Multi-Engine Ensemble**: **(Rekomendasi untuk 50–100 soal)** Menggabungkan seluruh engine di atas secara bergantian untuk variasi maksimal.
- **🖥️ Local LM Studio / Jan**: Terhubung ke endpoint OpenAI lokal di port `1234`.
- **🤖 Ollama Local LLM**: Otomatis mendeteksi model lokal yang terpasang (`llama3`, `mistral`, `qwen2.5`, `deepseek-r1`, dll.).

---

## 🚀 Panduan Menjalankan

### 1. Melalui Web Dashboard
Jalankan server lokal Laravel:
```powershell
php artisan serve
```
Buka browser di **`http://127.0.0.1:8000`**.  
- Tarik banyak file sekaligus ke kotak upload.
- Klik tombol **"+ Tambah Bab Teks Lain"** jika ingin menambah sesi teks manual.
- Tentukan persentase materi & jumlah soal (hingga 100 butir).
- Klik **"Buat Soal Dari Seluruh Materi"**.

### 2. Melalui Terminal (CLI Artisan)
```powershell
# Contoh memasukkan banyak file sekaligus (dipisah koma)
php artisan soal:generate --input=bab1.docx,bab2.pdf,data.csv --percent=80 --count=20 --model=hybrid_ensemble

# Contoh membuat hingga 100 butir soal dari banyak materi
php artisan soal:generate --input=sample_materi.docx,sample_materi.csv,materi_contoh.txt --percent=100 --count=100 --output=bank_soal_lengkap.txt
```
