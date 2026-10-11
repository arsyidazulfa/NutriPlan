<?php

require_once ROOT . '/helpers/antropometri.php';
require_once ROOT . '/helpers/akg.php';

/** @var mysqli $conn */

$idUser = (int) ($_SESSION['id_user'] ?? 0);
if ($idUser <= 0) {
    die('Sesi pengguna tidak valid. Silakan login kembali.');
}

function ambilDataAnakProfil(mysqli $conn, int $idUser): ?array
{
    $stmt = mysqli_prepare($conn, 'SELECT * FROM data_anak WHERE id_user = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $idUser);
    mysqli_stmt_execute($stmt);
    $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $data ?: null;
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM users WHERE id_user = ?');
mysqli_stmt_bind_param($stmt, 'i', $idUser);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$anak = ambilDataAnakProfil($conn, $idUser);
$error = '';
$success = '';

$alergiOptions = [
    'susu' => ['Susu', '🥛'],
    'telur' => ['Telur', '🥚'],
    'kacang' => ['Kacang', '🥜'],
    'ikan' => ['Ikan', '🐟'],
    'udang' => ['Udang', '🦐'],
    'gandum' => ['Gandum', '🌾'],
    'kedelai' => ['Kedelai', '🫘'],
    'seafood' => ['Seafood', '🦞'],
];

$form = $anak ?? [];
$savedAlergi = $anak
    ? array_values(array_filter(array_map('trim', explode(',', $anak['alergi'] ?? ''))))
    : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaAnak = trim($_POST['nama_anak'] ?? '');
    $tanggalLahir = trim($_POST['tanggal_lahir'] ?? '');
    $tanggalPengukuran = trim($_POST['tanggal_pengukuran'] ?? '');
    $jenisKelamin = trim($_POST['jenis_kelamin'] ?? '');
    $beratBadan = (float) ($_POST['berat_badan'] ?? 0);
    $tinggiBadan = (float) ($_POST['tinggi_badan'] ?? 0);
    $aktivitas = trim($_POST['aktivitas'] ?? 'Sedang');
    $alergi = is_array($_POST['alergi'] ?? null) ? $_POST['alergi'] : [];
    $alergiLain = trim($_POST['alergi_lainnya'] ?? '');

    $alergi = array_values(array_intersect(
        array_keys($alergiOptions),
        array_map('strval', $alergi)
    ));
    $alergiStr = implode(',', $alergi);
    $savedAlergi = $alergi;

    $form = [
        'nama_anak' => $namaAnak,
        'tanggal_lahir' => $tanggalLahir,
        'tanggal_pengukuran' => $tanggalPengukuran,
        'jenis_kelamin' => $jenisKelamin,
        'berat_badan' => $beratBadan ?: '',
        'tinggi_badan' => $tinggiBadan ?: '',
        'aktivitas' => $aktivitas,
        'alergi' => $alergiStr,
        'alergi_lainnya' => $alergiLain,
    ];

    if ($namaAnak === '') {
        $error = 'Nama anak wajib diisi.';
    } elseif ($tanggalLahir === '') {
        $error = 'Tanggal lahir wajib diisi.';
    } elseif ($tanggalPengukuran === '') {
        $error = 'Tanggal pengukuran wajib diisi.';
    } elseif (!in_array($jenisKelamin, ['Laki-laki', 'Perempuan'], true)) {
        $error = 'Jenis kelamin tidak valid.';
    } elseif ($beratBadan <= 0) {
        $error = 'Berat badan wajib diisi dan harus lebih dari 0 kg.';
    } elseif ($tinggiBadan <= 0) {
        $error = 'Tinggi badan wajib diisi dan harus lebih dari 0 cm.';
    } elseif (!in_array($aktivitas, ['Ringan', 'Sedang', 'Berat'], true)) {
        $error = 'Tingkat aktivitas tidak valid.';
    } else {
        try {
            $hariIni = new DateTime('today');
            $tanggalUkur = new DateTime($tanggalPengukuran);
            if ($tanggalUkur > $hariIni) {
                throw new RuntimeException('Tanggal pengukuran tidak boleh melebihi tanggal hari ini.');
            }

            $antropometri = hitungAntropometriAnak(
                $conn,
                $tanggalLahir,
                $tanggalPengukuran,
                $jenisKelamin,
                $beratBadan,
                $tinggiBadan
            );

            if (!$antropometri) {
                throw new RuntimeException(
                    'Usia anak saat pengukuran harus antara 6 tahun sampai 12 tahun 11 bulan.'
                );
            }

            $usia = (int) $antropometri['umur_tahun'];
            $usiaBulan = (int) $antropometri['umur_bulan'];
            $zTBU = (float) $antropometri['z_tbu'];
            $zIMTU = (float) $antropometri['z_imtu'];
            $statusTinggi = $antropometri['status_tbu'];
            $statusGizi = $antropometri['status_imtu'];

            $akg = hitungAKG($usia, $jenisKelamin);
            if (!$akg) {
                throw new RuntimeException('Data AKG untuk usia anak tidak ditemukan.');
            }

            $energi = (float) $akg['energi'];
            $protein = (float) $akg['protein'];

            if ($anak) {
                $sql = 'UPDATE data_anak SET
                    nama_anak = ?, usia = ?, tanggal_lahir = ?, tanggal_pengukuran = ?,
                    usia_bulan_eksak = ?, jenis_kelamin = ?, berat_badan = ?, tinggi_badan = ?,
                    aktivitas = ?, alergi = ?, alergi_lainnya = ?, zscore_tb_u = ?, zscore_imt_u = ?,
                    status_tinggi_badan = ?, status_gizi = ?, kebutuhan_energi = ?, kebutuhan_protein = ?
                    WHERE id_user = ?';
                $stmtSimpan = mysqli_prepare($conn, $sql);
                if (!$stmtSimpan) throw new RuntimeException('Query update data anak gagal.');

                mysqli_stmt_bind_param(
                    $stmtSimpan,
                    'sissisddsssddssddi',
                    $namaAnak, $usia, $tanggalLahir, $tanggalPengukuran, $usiaBulan,
                    $jenisKelamin, $beratBadan, $tinggiBadan, $aktivitas, $alergiStr,
                    $alergiLain, $zTBU, $zIMTU, $statusTinggi, $statusGizi,
                    $energi, $protein, $idUser
                );
            } else {
                $sql = 'INSERT INTO data_anak
                    (id_user, nama_anak, usia, tanggal_lahir, tanggal_pengukuran, usia_bulan_eksak,
                     jenis_kelamin, berat_badan, tinggi_badan, aktivitas, alergi, alergi_lainnya,
                     zscore_tb_u, zscore_imt_u, status_tinggi_badan, status_gizi,
                     kebutuhan_energi, kebutuhan_protein)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $stmtSimpan = mysqli_prepare($conn, $sql);
                if (!$stmtSimpan) throw new RuntimeException('Query insert data anak gagal.');

                mysqli_stmt_bind_param(
                    $stmtSimpan,
                    'isissisddsssddssdd',
                    $idUser, $namaAnak, $usia, $tanggalLahir, $tanggalPengukuran, $usiaBulan,
                    $jenisKelamin, $beratBadan, $tinggiBadan, $aktivitas, $alergiStr,
                    $alergiLain, $zTBU, $zIMTU, $statusTinggi, $statusGizi, $energi, $protein
                );
            }

            if (!mysqli_stmt_execute($stmtSimpan)) {
                throw new RuntimeException('Gagal menyimpan data anak: ' . mysqli_stmt_error($stmtSimpan));
            }

            mysqli_stmt_close($stmtSimpan);
            $success = 'Data anak dan hasil perhitungan gizi berhasil disimpan.';

            $anak = ambilDataAnakProfil($conn, $idUser);
            $form = $anak ?? [];
            $savedAlergi = $anak
                ? array_values(array_filter(array_map('trim', explode(',', $anak['alergi'] ?? ''))))
                : [];
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$tanggalPengukuranForm = $form['tanggal_pengukuran'] ?? date('Y-m-d');
$tahunSekarang = (int) date('Y');
$tanggalLahirMin = ($tahunSekarang - 12) . '-01-01';
$tanggalLahirMaxObjek = new DateTime('today');
$tanggalLahirMaxObjek->modify('-6 years');
$tanggalLahirMax = $tanggalLahirMaxObjek->format('Y-m-d');

$hasilSiap = $anak && $anak['zscore_tb_u'] !== null && $anak['zscore_imt_u'] !== null;
if ($hasilSiap) {
    $usiaBulanTampil = (int) $anak['usia_bulan_eksak'];
    $usiaTahunTampil = intdiv($usiaBulanTampil, 12);
    $usiaBulanSisa = $usiaBulanTampil % 12;
    $imtTampil = hitungIMT((float) $anak['berat_badan'], (float) $anak['tinggi_badan']);
}

$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Anak — NutriPlan</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <style>
        .hasil-gizi-card{background:#fff;border:1px solid #e3e8e4;border-radius:16px;margin-bottom:32px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,.04)}
        .hasil-gizi-header{padding:20px 24px;background:#f3f8f4;border-bottom:1px solid #e4ebe5}.hasil-gizi-header h3{margin:0 0 5px;font-size:19px;color:#174d2b}.hasil-gizi-header p{margin:0;color:#68756c;font-size:14px}
        .hasil-gizi-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr))}.hasil-section{padding:22px 24px;border-right:1px solid #ecefec}.hasil-section:last-child{border-right:none}.hasil-section h4{margin:0 0 14px;font-size:15px;color:#4f5d53}.hasil-section-kebutuhan{background:#fafcfb}
        .hasil-row{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:11px 0;border-bottom:1px solid #f0f2f0}.hasil-row:last-child{border-bottom:none}.hasil-label{font-size:14px;line-height:1.5;color:#69746c}.hasil-row strong{text-align:right;color:#163d25}
        .hasil-value-status{display:flex;flex-direction:column;align-items:flex-end;gap:5px;text-align:right}.hasil-value-status small{color:#89918b;font-size:12px}.status-badge{display:inline-block;padding:5px 10px;border-radius:999px;background:#e8f3eb;color:#22643a!important;font-size:13px;line-height:1.4}.hasil-utama{font-size:17px;color:#175d32!important}
        @media(max-width:950px){.hasil-gizi-grid{grid-template-columns:1fr}.hasil-section{border-right:none;border-bottom:1px solid #ecefec}.hasil-section:last-child{border-bottom:none}}
        @media(max-width:520px){.hasil-gizi-header,.hasil-section{padding:18px}.hasil-row{flex-direction:column;gap:5px}.hasil-row strong,.hasil-value-status{text-align:left;align-items:flex-start}}
    </style>
</head>
<body>
<?php include ROOT . '/includes/navbar.php'; ?>
<div class="dashboard-wrap">
    <?php include ROOT . '/includes/sidebar.php'; ?>
    <main class="dashboard-content">
        <h2>Data Anak</h2>

        <?php if ($error): ?><div class="flash flash-error"><?= $e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="flash flash-success"><?= $e($success) ?></div><?php endif; ?>

        <?php if ($hasilSiap): ?>
            <div class="hasil-gizi-card">
                <div class="hasil-gizi-header">
                    <h3>📊 Hasil Penilaian Gizi</h3>
                    <p>Ringkasan hasil pengukuran <strong><?= $e($anak['nama_anak']) ?></strong></p>
                </div>
                <div class="hasil-gizi-grid">
                    <div class="hasil-section">
                        <h4>Data Pengukuran</h4>
                        <div class="hasil-row"><span class="hasil-label">Usia saat pengukuran</span><strong><?= $usiaTahunTampil ?> tahun <?= $usiaBulanSisa ?> bulan</strong></div>
                        <div class="hasil-row"><span class="hasil-label">Indeks Massa Tubuh (IMT)</span><strong><?= number_format($imtTampil, 2, ',', '.') ?> kg/m²</strong></div>
                    </div>

                    <div class="hasil-section">
                        <h4>Status Pertumbuhan</h4>
                        <?php foreach ([
                            ['Tinggi Badan', 'status_tinggi_badan', 'zscore_tb_u'],
                            ['Status Gizi', 'status_gizi', 'zscore_imt_u']
                        ] as [$label, $statusKey, $zKey]): ?>
                            <div class="hasil-row">
                                <span class="hasil-label"><?= $label ?></span>
                                <div class="hasil-value-status">
                                    <strong class="status-badge"><?= $e($anak[$statusKey]) ?></strong>
                                    <small>Z-score: <?= number_format((float) $anak[$zKey], 2, ',', '.') ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="hasil-section hasil-section-kebutuhan">
                        <h4>Kebutuhan Gizi Harian</h4>
                        <div class="hasil-row"><span class="hasil-label">Energi</span><strong class="hasil-utama"><?= number_format((float) $anak['kebutuhan_energi'], 0, ',', '.') ?> kkal/hari</strong></div>
                        <div class="hasil-row"><span class="hasil-label">Protein</span><strong class="hasil-utama"><?= number_format((float) $anak['kebutuhan_protein'], 0, ',', '.') ?> g/hari</strong></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="dash-form">
            <div class="dash-form-group">
                <label>Nama Anak</label>
                <input type="text" name="nama_anak" value="<?= $e($form['nama_anak'] ?? '') ?>" placeholder="Nama lengkap anak" required>
            </div>

            <div class="dash-form-row">
                <div class="dash-form-group">
                    <label>Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" value="<?= $e($form['tanggal_lahir'] ?? '') ?>" min="<?= $e($tanggalLahirMin) ?>" max="<?= $e($tanggalLahirMax) ?>" required>
                    <small style="color:#777">Pilih tanggal lahir anak usia 6–12 tahun. Usia dihitung otomatis.</small>
                </div>
                <div class="dash-form-group">
                    <label>Jenis Kelamin</label>
                    <select name="jenis_kelamin" required>
                        <option value="">— Pilih —</option>
                        <?php foreach (['Laki-laki', 'Perempuan'] as $jk): ?>
                            <option value="<?= $e($jk) ?>" <?= ($form['jenis_kelamin'] ?? '') === $jk ? 'selected' : '' ?>><?= $e($jk) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="dash-form-group">
                <label>Tanggal Pengukuran Berat dan Tinggi Badan</label>
                <input type="date" name="tanggal_pengukuran" value="<?= $e($tanggalPengukuranForm) ?>" max="<?= date('Y-m-d') ?>" required>
                <small style="color:#777">Isi sesuai tanggal saat berat dan tinggi badan diukur.</small>
            </div>

            <div class="dash-form-row">
                <div class="dash-form-group">
                    <label>Berat Badan (kg)</label>
                    <input type="number" name="berat_badan" step="0.1" min="0.1" value="<?= $e($form['berat_badan'] ?? '') ?>" placeholder="Contoh: 25,5" required>
                </div>
                <div class="dash-form-group">
                    <label>Tinggi Badan (cm)</label>
                    <input type="number" name="tinggi_badan" step="0.1" min="0.1" value="<?= $e($form['tinggi_badan'] ?? '') ?>" placeholder="Contoh: 120,5" required>
                </div>
            </div>

            <div class="dash-form-group">
                <label>Tingkat Aktivitas</label>
                <select name="aktivitas" required>
                    <?php foreach (['Ringan', 'Sedang', 'Berat'] as $akt): ?>
                        <option value="<?= $e($akt) ?>" <?= ($form['aktivitas'] ?? 'Sedang') === $akt ? 'selected' : '' ?>><?= $e($akt) ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color:#777">Aktivitas dicatat sebagai informasi profil anak.</small>
            </div>

            <div class="dash-form-group">
                <label>Alergi Makanan</label>
                <div class="alergi-grid">
                    <?php foreach ($alergiOptions as $value => [$label, $icon]): ?>
                        <label class="alergi-item">
                            <input type="checkbox" name="alergi[]" value="<?= $e($value) ?>" <?= in_array($value, $savedAlergi, true) ? 'checked' : '' ?>>
                            <span class="alergi-label"><?= $icon ?> <?= $e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="dash-form-group">
                <label>Alergi Lainnya <span style="font-weight:400;color:#888">(opsional)</span></label>
                <input type="text" name="alergi_lainnya" value="<?= $e($form['alergi_lainnya'] ?? '') ?>" placeholder="Contoh: kiwi, stroberi">
            </div>

            <button type="submit" class="btn-primary">Simpan dan Hitung Kebutuhan Gizi</button>
        </form>
    </main>
</div>
<script src="/assets/js/script.js"></script>
</body>
</html>