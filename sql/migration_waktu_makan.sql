-- ============================================================
--  MIGRATION: tambah kolom waktu_makan ke tabel menu
--  Jalankan jika DB sudah ada datanya & tidak mau re-import full.
--
--  Cara jalankan:
--    [A] phpMyAdmin -> pilih DB db_nutriplan -> tab SQL -> paste -> Go
--    [B] Terminal:
--        mysql -u root -p db_nutriplan < sql/migration_waktu_makan.sql
-- ============================================================

USE db_nutriplan;

-- 1) Tambah kolom waktu_makan (default: cocok untuk semua slot)
ALTER TABLE menu
  ADD COLUMN waktu_makan SET('pagi','siang','malam')
    NOT NULL DEFAULT 'pagi,siang,malam'
    AFTER kelompok_usia;

-- 2) Backfill 12 sample menu bawaan dengan slot waktu yang sesuai.
--    (Aman dijalankan ulang; berbasis nama_menu.)
UPDATE menu SET waktu_makan = 'pagi,malam'  WHERE nama_menu = 'Nasi Goreng Sayur Telur';
UPDATE menu SET waktu_makan = 'siang,malam' WHERE nama_menu = 'Ayam Kukus Brokoli';
UPDATE menu SET waktu_makan = 'malam'       WHERE nama_menu = 'Sup Tahu Wortel';
UPDATE menu SET waktu_makan = 'malam'       WHERE nama_menu = 'Ikan Bakar Bumbu Kecap';
UPDATE menu SET waktu_makan = 'siang,malam' WHERE nama_menu = 'Tumis Tempe Kangkung';
UPDATE menu SET waktu_makan = 'malam'       WHERE nama_menu = 'Pasta Bolognese Ayam';
UPDATE menu SET waktu_makan = 'pagi'        WHERE nama_menu = 'Bubur Ayam Sayuran';
UPDATE menu SET waktu_makan = 'pagi,siang'  WHERE nama_menu = 'Telur Dadar Sayur';
UPDATE menu SET waktu_makan = 'pagi,malam'  WHERE nama_menu = 'Soto Ayam';
UPDATE menu SET waktu_makan = 'pagi,siang'  WHERE nama_menu = 'Sandwich Tuna';
UPDATE menu SET waktu_makan = 'siang,malam' WHERE nama_menu = 'Pepes Ikan Mas';
UPDATE menu SET waktu_makan = 'siang,malam' WHERE nama_menu = 'Capcay Sayuran Tahu';

-- Selesai. Verifikasi:
--   SELECT id_menu, nama_menu, waktu_makan FROM menu;
