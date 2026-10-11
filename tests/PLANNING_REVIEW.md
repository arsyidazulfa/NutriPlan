# Review koreksi berdasarkan ketentuan final

## A. File yang diubah

- helpers/rekomendasi.php: energi pagi/siang/malam 25%/30%/25%; R1–R4 dipusatkan; hasil target_energi dan selisih_energi terpisah. target_protein dan selisih_protein null, status PERLU KEPUTUSAN. Tidak ada skor gabungan atau pengurutan gizi.
- classes/RencanaMingguanRepository.php: pencarian plan terakhir dan transaksi autosave dipindahkan dari page. Tanggung jawab tetap akses data planning; tidak menghitung AKG atau menyaring rekomendasi.
- pages/planning_mingguan.php: tidak lagi memiliki SQL planning atau transaksi langsung; memakai repository dan helper. Query user serta variabel tidak terpakai dihapus. Data hari/slot dari repository. Pemberitahuan ranking belum final ditampilkan.
- assets/js/planning_mingguan.js: JavaScript inline dipisahkan; ID plan dibaca dari data-plan-id. Antrean autosave dan penanganan gagal/retry dipertahankan.
- pages/planning_detail.php dan pages/download_jadwal.php: label/kolom anggaran diganti sumber protein.
- pages/menu.php: perlu disentuh karena masih memakai proporsi legacy dan menggandakan filter anggaran. Sekarang menggunakan helper yang sama, sumber protein, serta semua kandidat alfabetis tanpa ranking atau batas top-three semu.
- tests/planning_regression.php dan tests/planning_autosave.cjs: ekspektasi target final, R1–R4, indikator terpisah, tidak ada ranking, dan pemuatan file JS baru.
- tests/PLANNING_REVIEW.md: laporan lama yang memuat asumsi tanpa keputusan diganti dengan hasil koreksi ini.

Review juga mencakup pages/cara_mengolah.php, pages/camilan_sehat.php, index.php, dan assets/js/script.js; tidak diperlukan perubahan tambahan pada keempat file tersebut. Dua helper sumber, akg.php dan antropometri.php, tetap tidak diubah dan tidak disalin rumusnya.

## B. Logika final

Data anak (usia tersimpan dari profil) → AKG dari helpers/akg.php → target energi 25%/30%/25% → hard rule IF–THEN R1 kelompok usia, R2 sumber protein, R3 slot, R4 alergi → kandidat dengan indikator terpisah → ranking PERLU KEPUTUSAN → pilihan orang tua → validasi ulang kandidat oleh helper → autosave transaksional melalui repository.

Semua hard rule harus lolos. Nilai kelompok_usia=semua mempertahankan arti katalog yang berlaku untuk semua kelompok usia yang didukung. Alergi memakai token dipisahkan koma dengan normalisasi huruf dan spasi. Kandidat ditampilkan menurut nama/id hanya untuk konsistensi tampilan, bukan sebagai ranking gizi. Selisih energi adalah nilai absolut energi menu dikurangi target slot, dalam kkal. Protein harian tetap tersedia melalui AKG, tetapi tidak dijadikan target per slot tanpa metodologi.

Planning berisi tujuh hari dan tiga slot utama. Camilan adalah katalog opsional terpisah. Sisa 20% energi tidak dialokasikan otomatis dan tidak menambah slot. Nilai gizi menggunakan total menu yang tersedia; DBMP/TKPI tidak dimasukkan sebagai rule.

## C. Legacy dan kode tidak terpakai

Dihapus dari jalur rekomendasi yang dikoreksi: proporsi lama, bobot sama energi/protein, fungsi peringkatMenuPlanning, ranking energi-only di menu.php, filter anggaran di menu.php, tampilan anggaran pada detail/PDF, query user dan variabel success/menu_wajib_tidak_lengkap yang tidak terpakai di planning, serta JavaScript inline planning.

Tidak ditemukan checklist persiapan atau daftar belanja pada jalur planning yang direview. Tidak ada camilan dalam daftar slot penyimpanan/pembacaan planning. tools/update-planning.cjs sudah tidak ada dan tidak diperlukan; tidak dibuat ulang. Tidak ada data lama yang dihapus dalam pengerjaan ini.

Batas lingkup: menu_acak.php, menu_detail.php, form admin, dan handler .anggaran-option di script.js masih memiliki sisa anggaran di luar jalur planning. Tidak direfactor karena instruksi membatasi koreksi ini; keberadaannya tidak digunakan oleh repository/helper planning. menu_acak masih dapat dibuka melalui route lamanya. Laporan ini tidak menyatakan seluruh aplikasi bebas legacy.

## D. Hasil test pada koreksi ini

- php -l: 10 file lulus (sembilan PHP aplikasi terkait termasuk menu.php, ditambah regression test).
- node --check: script.js dan planning_mingguan.js lulus.
- php tests/planning_regression.php: 24 pemeriksaan lulus; mencakup tanggal, R1–R4, target energi, indikator terpisah, tanpa ranking, dan penolakan camilan.
- node tests/planning_autosave.cjs: antrean, tombol lanjut selama pending/gagal, dan retry lulus.
- Pemeriksaan statis: tidak ada SQL planning atau transaksi langsung di planning_mingguan.php; tidak ada proporsi legacy dalam PHP/JS aplikasi; script refactor temporary tidak ada.
- Test --database tidak dijalankan ulang karena membuat database/tabel sementara. Hasil integrasi database dari pengerjaan sebelumnya tidak diklaim sebagai verifikasi versi ini. Tidak ada DDL, perubahan data, atau penghapusan data yang dijalankan pada koreksi ini. Integrasi browser/database aktual belum diverifikasi.

## E. PERLU KEPUTUSAN

1. Pembagian/metodologi target protein per slot. target_protein dan selisih_protein tetap null (bukan nol).
2. Formula ranking akhir: cara menggabungkan/memprioritaskan energi dan protein, normalisasi, bobot jika digunakan, serta tie-break bila skor sama.

SQL proyek yang tersedia sebelumnya belum mencerminkan tabel/kolom planning dan kategori_protein. Skema aktual tetap perlu diverifikasi sebelum uji integrasi; bukan alasan untuk mengubah database pada koreksi ini.
