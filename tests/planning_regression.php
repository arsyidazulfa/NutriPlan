<?php
require_once __DIR__ . '/../classes/RencanaMingguanRepository.php';
require_once __DIR__ . '/../helpers/rekomendasi.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
function rejects(callable $action, string $message): void {
    try { $action(); } catch (Throwable $e) { echo "PASS: $message\n"; return; }
    throw new RuntimeException($message);
}

$repo = new RencanaMingguanRepository(new mysqli());
check($repo->validasiTanggalMulai('2026-10-05')->format('Y-m-d') === '2026-10-05', 'Tanggal Senin diterima');
foreach (['2026-10-06', '2026-02-30', 'next monday', ''] as $tanggal) {
    rejects(fn() => $repo->validasiTanggalMulai($tanggal), "Tanggal tidak valid ditolak: $tanggal");
}
check(!menuAmanUntukAnak(['alergi_kandungan' => 'Susu, Telur'], ['alergi' => 'telur']), 'Token alergi dinormalisasi');
check(!menuAmanUntukAnak(['alergi_kandungan' => 'udang'], ['alergi_lainnya' => 'Udang']), 'Alergi lainnya ikut disaring');
check(menuAmanUntukAnak(['alergi_kandungan' => null], ['alergi' => 'telur']), 'Kandungan alergi kosong diproses');
$anak = ['usia' => 8, 'jenis_kelamin' => 'Laki-laki'];
$menu = ['id_menu' => 1, 'nama_menu' => 'Menu utama', 'energi' => 400, 'protein' => 10,
    'kelompok_usia' => '6-9', 'kategori_protein' => 'hewani', 'waktu_makan' => 'pagi,malam'];
check(menuLolosHardRule($menu, $anak, 'hewani', 'pagi'), 'Kandidat lolos seluruh hard rule');
foreach ([
    'R1 usia' => ['kelompok_usia' => '10-12'],
    'R2 protein' => ['kategori_protein' => 'nabati'],
    'R3 slot' => ['waktu_makan' => 'siang'],
    'camilan' => ['waktu_makan' => 'cemilan'],
] as $rule => $ganti) {
    check(!menuLolosHardRule(array_replace($menu, $ganti), $anak, 'hewani', 'pagi'), "$rule ditolak");
}
check(!menuLolosHardRule($menu + ['alergi_kandungan' => 'telur'], $anak + ['alergi' => 'Telur'], 'hewani', 'pagi'), 'R4 alergi ditolak');
check(menuLolosHardRule(array_replace($menu, ['kelompok_usia' => 'semua']), $anak, 'hewani', 'pagi'), 'Kelompok semua berlaku untuk usia anak');
foreach (['pagi' => 412.5, 'siang' => 495.0, 'malam' => 412.5] as $slot => $energi) {
    check(abs(targetMenuPlanning($anak, $slot)['target_energi'] - $energi) < 0.00001, "Target energi final $slot");
}
$hasil = kandidatMenuPlanning([$menu, array_replace($menu, ['id_menu' => 2, 'energi' => 412.5])], $anak, 'hewani', 'pagi');
check(array_column($hasil, 'id_menu') === [1,2], 'Tidak mengurutkan kandidat berdasarkan formula ranking baru');
check($hasil[0]['target_energi'] === 412.5 && $hasil[0]['selisih_energi'] === 12.5, 'Target dan selisih energi terpisah');
check($hasil[0]['target_protein'] === null && $hasil[0]['selisih_protein'] === null, 'Tidak mengarang target atau selisih protein per slot');
check($hasil[0]['status_ranking'] === 'PERLU KEPUTUSAN' && !isset($hasil[0]['skor_rekomendasi']), 'Ranking belum ditetapkan dan tanpa skor gabungan');
rejects(fn() => targetMenuPlanning($anak, 'camilan'), 'Camilan bukan slot utama');
rejects(fn() => targetMenuPlanning(['usia' => 5, 'jenis_kelamin' => 'Laki-laki'], 'pagi'), 'Usia di luar cakupan AKG ditolak');

// Opsional: jalankan pada server uji tersendiri. Tidak mengakses db_nutriplan.
if (!in_array('--database', $argv, true)) exit(0);
$conn = new mysqli('127.0.0.1', 'root', '', '', 13317);
$conn->query('CREATE DATABASE IF NOT EXISTS nutriplan_regression');
$conn->select_db('nutriplan_regression');
// Tabel sementara menggunakan kolom yang dipanggil kode; bukan migrasi database aplikasi.
$conn->query('CREATE TEMPORARY TABLE planning_mingguan (
    id_plan INT AUTO_INCREMENT PRIMARY KEY, id_user INT, id_anak INT,
    tanggal_mulai DATE, tanggal_selesai DATE, kategori_protein VARCHAR(20)
) ENGINE=InnoDB');
$conn->query("CREATE TEMPORARY TABLE planning_detail (
    id_plan INT, hari_ke INT, nama_hari VARCHAR(20), tanggal DATE,
    waktu_makan VARCHAR(20), id_menu INT, CHECK (id_menu > 0 AND id_menu < 100)
) ENGINE=InnoDB");
$repo = new RencanaMingguanRepository($conn);
$plan = $repo->buatPlan(1, 2, '2026-10-05', 'hewani');
check($repo->cariPlan(1, 2, '2026-10-05', 'HEWANI') === $plan, 'Plan ditemukan berdasarkan identitas dan kategori');
check($repo->cariPlan(9, 2, '2026-10-05', 'hewani') === 0, 'Plan pengguna lain tidak ditemukan');
$repo->simpanPilihan($plan, 1, 'pagi', 1, '2026-10-05');
$repo->simpanPilihan($plan, 1, 'pagi', 2, '2026-10-05');
$repo->simpanPilihan($plan, 7, 'malam', 3, '2026-10-05');
check($repo->ambilPilihan($plan) === [1 => ['pagi' => 2], 7 => ['malam' => 3]], 'Simpan, ganti, dan ambil pilihan termasuk Minggu');
check((int) $conn->query('SELECT COUNT(*) n FROM planning_detail')->fetch_assoc()['n'] === 2, 'Penggantian menghapus pilihan lama tanpa duplikasi');
rejects(fn() => $repo->simpanPilihan($plan, 1, 'pagi', 101, '2026-10-05'), 'Simulasi INSERT gagal');
check($repo->ambilPilihan($plan)[1]['pagi'] === 2, 'Rollback mengembalikan pilihan lama saat INSERT gagal');
$conn->begin_transaction();
$repo->simpanPilihan($plan, 1, 'pagi', 4, '2026-10-05', false);
$conn->rollback();
check($repo->ambilPilihan($plan)[1]['pagi'] === 2, 'Repository tidak commit transaksi milik pemanggil');
rejects(fn() => $repo->simpanPilihan($plan, 1, 'cemilan', 3, '2026-10-05'), 'Penyimpanan camilan pada planning ditolak');
rejects(fn() => $repo->simpanPilihan($plan, 8, 'pagi', 3, '2026-10-05'), 'Hari kedelapan ditolak');
rejects(fn() => $repo->simpanPilihan($plan, 1, 'pagi', 3, '2026-10-12'), 'Tanggal tidak sesuai plan ditolak');
$conn->query("INSERT INTO planning_detail VALUES ($plan, 1, 'Senin', '2026-10-05', 'cemilan', 4)");
check(!isset($repo->ambilPilihan($plan)[1]['cemilan']), 'Data camilan lama tidak dibaca sebagai makan utama');
$row = $conn->query("SELECT tanggal,nama_hari FROM planning_detail WHERE hari_ke=7")->fetch_assoc();
check($row === ['tanggal' => '2026-10-11', 'nama_hari' => 'Minggu'], 'Tanggal dan nama hari ketujuh konsisten');
$conn->query("CREATE TEMPORARY TABLE menu (
    id_menu INT, nama_menu VARCHAR(100), kategori_protein VARCHAR(20), kelompok_usia VARCHAR(20),
    waktu_makan VARCHAR(40), alergi_kandungan VARCHAR(100), energi FLOAT, protein FLOAT
) ENGINE=InnoDB");
$conn->query("INSERT INTO menu VALUES
    (1, 'Pilihan aman', 'hewani', 'semua', 'pagi,malam', NULL, 462, 11.2),
    (2, 'Alergen', 'hewani', 'semua', 'pagi', 'Susu, Telur', 462, 11.2),
    (3, 'Sumber lain', 'nabati', 'semua', 'pagi', NULL, 462, 11.2),
    (4, 'Slot lain', 'hewani', 'semua', 'siang', NULL, 462, 11.2),
    (5, 'Camilan', 'hewani', 'semua', 'cemilan', NULL, 462, 11.2),
    (6, 'Usia lain', 'hewani', '10-12', 'pagi', NULL, 462, 11.2),
    (7, 'Pilihan kedua', 'hewani', '6-9', 'pagi', NULL, 400, 10)");
$anak['alergi'] = 'telur';
check(array_column(ambilMenuPlanning($conn, 'hewani', $anak, 'pagi'), 'id_menu') === [1,7], 'Kandidat disaring dengan R1–R4, ditampilkan alfabetis');
