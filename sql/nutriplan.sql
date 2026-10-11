CREATE DATABASE IF NOT EXISTS db_nutriplan;
USE db_nutriplan;

-- ==========================================
-- TABEL USERS
-- ==========================================
CREATE TABLE IF NOT EXISTS users (
  id_user           INT AUTO_INCREMENT PRIMARY KEY,
  nama              VARCHAR(100) NOT NULL,
  email             VARCHAR(100) NOT NULL UNIQUE,
  password          VARCHAR(255) NOT NULL,
  foto              VARCHAR(255) DEFAULT NULL,
  role              ENUM('ortu', 'admin') DEFAULT 'ortu',
  kategori_anggaran ENUM('Hemat', 'Standar', 'Premium') DEFAULT NULL,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- TABEL DATA_ANAK
-- ==========================================
CREATE TABLE IF NOT EXISTS data_anak (
  id_anak        INT AUTO_INCREMENT PRIMARY KEY,
  id_user        INT NOT NULL,
  nama_anak      VARCHAR(100) NOT NULL,
  usia           INT NOT NULL,
  jenis_kelamin  ENUM('Laki-laki', 'Perempuan') NOT NULL,
  berat_badan    FLOAT DEFAULT NULL,
  tinggi_badan   FLOAT DEFAULT NULL,
  aktivitas      ENUM('Ringan', 'Sedang', 'Berat') DEFAULT 'Sedang',
  alergi         VARCHAR(255) DEFAULT NULL,
  alergi_lainnya VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
);

-- ==========================================
-- TABEL MENU
-- ==========================================
CREATE TABLE IF NOT EXISTS menu (
  id_menu           INT AUTO_INCREMENT PRIMARY KEY,
  nama_menu         VARCHAR(255) NOT NULL,
  deskripsi         TEXT,
  cara_masak        TEXT,
  gambar            VARCHAR(255) DEFAULT NULL,
  energi            FLOAT NOT NULL,
  protein           FLOAT NOT NULL,
  lemak             FLOAT DEFAULT 0,
  karbohidrat       FLOAT DEFAULT 0,
  serat             FLOAT DEFAULT 0,
  harga             DECIMAL(10,2) DEFAULT 0,
  kategori_anggaran ENUM('Hemat', 'Standar', 'Premium') NOT NULL,
  kelompok_usia     ENUM('6-9', '10-12', 'semua') DEFAULT 'semua',
  waktu_makan       SET('pagi','siang','malam') NOT NULL DEFAULT 'pagi,siang,malam',
  alergi_kandungan  VARCHAR(255) DEFAULT NULL,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- TABEL EDUKASI
-- ==========================================
CREATE TABLE IF NOT EXISTS edukasi (
  id_edukasi INT AUTO_INCREMENT PRIMARY KEY,
  judul      VARCHAR(255) NOT NULL,
  isi        TEXT NOT NULL,
  gambar     VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- SEED: Admin & User Ortu (password: admin123)
-- ==========================================
INSERT INTO users (nama, email, password, role, kategori_anggaran) VALUES
('Admin NutriPlan', 'admin@nutriplan.com', '$2y$12$JNl56IH.keSTHXAvQ7yL..vKLeEvVnCcgUtpDHT5tMvmI0o./tSby', 'admin', NULL),
('Ibu Sari',        'ibu@nutriplan.com',   '$2y$12$JNl56IH.keSTHXAvQ7yL..vKLeEvVnCcgUtpDHT5tMvmI0o./tSby', 'ortu',  'Standar');

-- Data anak default untuk Ibu Sari (biar langsung bisa lihat rekomendasi)
INSERT INTO data_anak (id_user, nama_anak, usia, jenis_kelamin, berat_badan, tinggi_badan, aktivitas)
SELECT id_user, 'Adik', 8, 'Laki-laki', 24.5, 125, 'Sedang'
FROM users WHERE email = 'ibu@nutriplan.com';

-- ==========================================
-- SEED: Menu sample
-- ==========================================
INSERT INTO menu (nama_menu, deskripsi, cara_masak, energi, protein, lemak, karbohidrat, serat, harga, kategori_anggaran, kelompok_usia, waktu_makan, alergi_kandungan) VALUES
('Nasi Goreng Sayur Telur', NULL, 'Panaskan minyak, tumis bawang putih dan bawang merah hingga harum. Masukkan sayuran (wortel, kacang panjang), aduk rata. Tambahkan nasi putih, bumbui dengan kecap asin dan garam. Buat orak-arik telur di sisi wajan, campur dengan nasi.', 350, 12, 8, 55, 3, 12000, 'Hemat', 'semua', 'pagi,malam', 'telur'),
('Ayam Kukus Brokoli', NULL, 'Bumbui ayam dengan garam, bawang putih, dan jahe. Kukus selama 20-25 menit hingga matang. Rebus brokoli 3 menit, tiriskan. Sajikan ayam bersama brokoli dan sambal kecap.', 280, 25, 6, 20, 4, 22000, 'Standar', 'semua', 'siang,malam', NULL),
('Sup Tahu Wortel', NULL, 'Rebus air hingga mendidih, masukkan kaldu. Tambahkan wortel dan kentang yang dipotong dadu. Masak 10 menit, tambahkan tahu putih potong dadu. Bumbui dengan garam, merica, dan daun seledri.', 200, 10, 5, 25, 5, 10000, 'Hemat', 'semua', 'malam', NULL),
('Ikan Bakar Bumbu Kecap', NULL, 'Bersihkan ikan, beri irisan lemon dan garam, diamkan 15 menit. Buat bumbu: kecap manis, bawang putih, cabai, dan jahe. Bakar ikan di atas pan anti lengket sambil diolesi bumbu kecap hingga matang dan sedikit gosong di pinggirnya.', 250, 28, 7, 15, 2, 25000, 'Standar', 'semua', 'malam', 'ikan,seafood'),
('Tumis Tempe Kangkung', NULL, 'Potong tempe dadu kecil, goreng sebentar hingga kekuningan. Tumis bawang merah, bawang putih, dan cabai. Masukkan kangkung, aduk cepat. Tambahkan tempe goreng, bumbui kecap asin dan garam.', 180, 14, 6, 18, 6, 8000, 'Hemat', 'semua', 'siang,malam', NULL),
('Pasta Bolognese Ayam', NULL, 'Rebus pasta hingga al dente, tiriskan. Tumis bawang bombay dan bawang putih, masukkan ayam cincang, masak hingga kecoklatan. Tambahkan saus tomat, oregano, dan garam. Campur dengan pasta, taburi keju parut.', 420, 22, 10, 60, 4, 35000, 'Premium', 'semua', 'malam', 'gluten'),
('Bubur Ayam Sayuran', NULL, 'Masak nasi dengan air lebih banyak (1:6) hingga menjadi bubur. Suwir daging ayam rebus. Tumis jahe dan bawang putih, masukkan wortel parut dan seledri. Sajikan bubur dengan topping ayam suwir dan sayuran.', 220, 15, 4, 38, 3, 10000, 'Hemat', '6-9', 'pagi', NULL),
('Telur Dadar Sayur', NULL, 'Kocok telur, tambahkan irisan daun bawang, wortel parut, dan bayam cincang. Bumbui dengan garam dan merica. Goreng dengan sedikit minyak di wajan anti lengket hingga matang kedua sisi.', 180, 13, 10, 8, 2, 8000, 'Hemat', 'semua', 'pagi,siang', 'telur'),
('Soto Ayam', NULL, 'Rebus ayam dengan rempah (jahe, kunyit, sereh, daun jeruk) hingga kaldu harum. Suwir ayam. Sajikan dengan mie/bihun, telur rebus, kol, tomat, dan taoge. Siram kuah soto panas.', 310, 20, 8, 40, 4, 18000, 'Standar', 'semua', 'pagi,malam', 'telur'),
('Sandwich Tuna', NULL, 'Aduk tuna kalengan dengan mayones, perasan lemon, garam, dan merica. Siapkan roti tawar, lapisi dengan selada dan tomat slice. Tambahkan isi tuna, tutup dengan roti.', 380, 24, 14, 42, 3, 28000, 'Standar', '10-12', 'pagi,siang', 'ikan,seafood,gluten'),
('Pepes Ikan Mas', NULL, 'Haluskan bumbu (bawang merah, putih, kemiri, kunyit, jahe). Lumuri ikan mas dengan bumbu halus, tambahkan daun kemangi dan tomat slice. Bungkus dengan daun pisang, kukus 30 menit.', 230, 26, 7, 10, 1, 20000, 'Standar', 'semua', 'siang,malam', 'ikan,seafood'),
('Capcay Sayuran Tahu', NULL, 'Tumis bawang putih, masukkan wortel, kembang kol, sawi, dan jamur. Tambahkan tahu goreng. Bumbui dengan saus tiram, kecap asin, garam, dan merica. Kentalkan dengan larutan tepung maizena.', 160, 9, 5, 20, 7, 12000, 'Hemat', 'semua', 'siang,malam', NULL);

-- ==========================================
-- SEED: Edukasi sample
-- ==========================================
INSERT INTO edukasi (judul, isi, gambar) VALUES
('Pentingnya Sarapan Bergizi untuk Anak Sekolah', 'Sarapan merupakan waktu makan terpenting bagi anak sekolah. Penelitian menunjukkan bahwa anak yang rutin sarapan memiliki konsentrasi belajar yang lebih baik, daya ingat yang kuat, dan energi yang cukup untuk beraktivitas sepanjang hari.\n\nMenu sarapan ideal untuk anak sekolah harus mengandung karbohidrat kompleks sebagai sumber energi utama, protein untuk membantu perkembangan otot dan otak, serta vitamin dan mineral dari buah atau sayuran.\n\nContoh menu sarapan bergizi: nasi goreng sayur dengan telur, roti gandum dengan selai kacang dan pisang, atau bubur ayam dengan sayuran. Hindari sarapan dengan makanan tinggi gula karena dapat menyebabkan lonjakan energi sesaat yang diikuti rasa lemas.', NULL),
('Kebutuhan Protein Anak Usia Sekolah', 'Protein merupakan zat gizi makro yang sangat penting untuk pertumbuhan dan perkembangan anak usia sekolah (6-12 tahun). Protein berperan dalam pembentukan sel-sel tubuh, jaringan otot, enzim, dan hormon.\n\nMenurut AKG Kemenkes 2023, anak usia 6-9 tahun membutuhkan sekitar 40 gram protein per hari, sementara anak usia 10-12 tahun membutuhkan 50 gram protein per hari.\n\nSumber protein berkualitas tinggi untuk anak antara lain: daging ayam tanpa kulit, ikan, telur, tahu, tempe, susu, dan kacang-kacangan. Variasikan sumber protein setiap hari untuk memastikan asupan asam amino yang lengkap.', NULL),
('Tips Mengatasi Anak yang Pilih-pilih Makanan', 'Anak yang pilih-pilih makanan (picky eater) adalah tantangan umum bagi orang tua. Kondisi ini bisa berdampak pada kecukupan gizi anak jika tidak ditangani dengan tepat.\n\nBeberapa strategi efektif yang bisa diterapkan:\n\n1. Libatkan anak dalam proses memasak - anak lebih semangat makan makanan yang ia ikut buat\n2. Sajikan makanan dalam tampilan menarik - bentuk lucu, warna cerah\n3. Kenalkan makanan baru secara bertahap - mulai dari porsi kecil\n4. Jangan memaksa - tekanan justru membuat anak semakin menolak\n5. Jadikan waktu makan menyenangkan - makan bersama keluarga\n\nJika kondisi berlanjut dan berdampak pada pertumbuhan, konsultasikan dengan ahli gizi atau dokter anak.', NULL);
