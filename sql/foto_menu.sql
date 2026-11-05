-- Foto untuk 6 menu, dari "Indonesian Food Dataset" (Rizky Yanuar K, Kaggle, 2024):
-- https://www.kaggle.com/datasets/rizkyyk/dataset-food-classification
-- Lisensi yang tertulis di Kaggle: MIT. Menurut deskripsinya, gambar dikumpulkan dari web.
-- File foto ada di assets/images/menu/ (sudah diperkecil, maks. 800 px).
-- Menu lain belum punya foto yang cocok dan tetap memakai ikon jenis lauk.
-- Aman dijalankan lebih dari sekali.

UPDATE menu SET gambar = 'menu/ayam-goreng-bumbu-kuning.jpg' WHERE nama_menu = 'Ayam Goreng Bumbu Kuning'; -- Ayam Goreng/Ayam Goreng_300.jpg
UPDATE menu SET gambar = 'menu/ayam-kampung-bakar-madu.jpg'  WHERE nama_menu = 'Ayam Kampung Bakar Madu';  -- Ayam Goreng/Ayam Goreng_089.jpg
UPDATE menu SET gambar = 'menu/ikan-mas-goreng.jpg'          WHERE nama_menu = 'Ikan Mas Goreng';          -- Ikan Goreng/Ikan Goreng_045.jpg
UPDATE menu SET gambar = 'menu/ikan-mujair-bakar.jpg'        WHERE nama_menu = 'Ikan Mujair Bakar';        -- Ikan Goreng/Ikan Goreng_161.jpg
UPDATE menu SET gambar = 'menu/sate-sapi.jpg'                WHERE nama_menu = 'Sate Sapi';                -- Sate/Sate_254.jpg
UPDATE menu SET gambar = 'menu/sate-kambing.jpg'             WHERE nama_menu = 'Sate Kambing';             -- Sate/Sate_272.jpg
