<?php

$idUser = (int) ($_SESSION['id_user'] ?? 0);

if ($idUser <= 0) {
    header('Location: /index.php?page=login');
    exit();
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA ANAK
|--------------------------------------------------------------------------
*/
$stmtAnak = mysqli_prepare(
    $conn,
    "SELECT *
     FROM data_anak
     WHERE id_user = ?
     ORDER BY id_anak DESC
     LIMIT 1"
);

if (!$stmtAnak) {
    die('Query data anak gagal: ' . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmtAnak, 'i', $idUser);
mysqli_stmt_execute($stmtAnak);

$anak = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmtAnak)
);

/*
|--------------------------------------------------------------------------
| TANGGAL SENIN BERIKUTNYA
|--------------------------------------------------------------------------
*/
function spTanggalSeninDepan(): string
{
    $tanggal = new DateTime();

    if ((int) $tanggal->format('N') !== 1) {
        $tanggal->modify('next monday');
    }

    return $tanggal->format('Y-m-d');
}

/*
|--------------------------------------------------------------------------
| PILIHAN SUMBER PROTEIN
|--------------------------------------------------------------------------
| Nilai "nabati" dan "hewani" tetap dipakai untuk database/filter.
| Label dibuat lebih mudah dipahami orang tua.
|--------------------------------------------------------------------------
*/
$pilihanProtein = [
    'nabati' => [
        'label' => 'Protein dari Tumbuhan',
        'icon' => '🌱',
        'asal' => 'Berasal dari tumbuhan',
        'contoh' => 'Tempe, tahu, dan kacang-kacangan.'
    ],

    'hewani' => [
        'label' => 'Protein dari Hewan',
        'icon' => '🐟🥚',
        'asal' => 'Berasal dari hewan',
        'contoh' => 'Ayam, ikan, telur, daging, dan susu.'
    ]
];

$error = '';

/*
|--------------------------------------------------------------------------
| AMBIL PLANNING TERAKHIR DARI DATABASE
|--------------------------------------------------------------------------
*/
$planTerakhir = null;

if ($anak) {
    $idAnak = (int) $anak['id_anak'];
    $stmtPlan = mysqli_prepare(
        $conn,
        "SELECT kategori_protein, tanggal_mulai
         FROM planning_mingguan
         WHERE id_user = ? AND id_anak = ?
         ORDER BY id_plan DESC
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmtPlan, 'ii', $idUser, $idAnak);
    mysqli_stmt_execute($stmtPlan);
    $planTerakhir = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPlan));
}

$kategoriDipilih = (string) (
    $_POST['kategori_protein']
    ?? $_SESSION['kategori_protein_planning']
    ?? $planTerakhir['kategori_protein']
    ?? ''
);

$tanggalMulai = (string) (
    $_POST['tanggal_mulai']
    ?? $_SESSION['tanggal_mulai_planning']
    ?? $planTerakhir['tanggal_mulai']
    ?? spTanggalSeninDepan()
);

/*
|--------------------------------------------------------------------------
| PROSES SIMPAN
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['aksi'] ?? '') === 'lanjut'
) {
    $kategoriDipilih = trim(
        (string) ($_POST['kategori_protein'] ?? '')
    );

    $tanggalMulai = trim(
        (string) ($_POST['tanggal_mulai'] ?? '')
    );

    if (!$anak) {
        $error = 'Data anak belum diisi.';
    } elseif (!array_key_exists($kategoriDipilih, $pilihanProtein)) {
        $error = 'Pilih salah satu sumber protein terlebih dahulu.';
    } elseif ($tanggalMulai === '') {
        $error = 'Tanggal mulai planning wajib dipilih.';
    } else {
        try {
            $tanggalObjek = new DateTime($tanggalMulai);

            if ((int) $tanggalObjek->format('N') !== 1) {
                throw new Exception(
                    'Tanggal mulai planning harus hari Senin.'
                );
            }

            /* SIMPAN PILIHAN KE DATABASE AGAR TETAP ADA SETELAH LOGOUT */
            $idAnak = (int) $anak['id_anak'];
            $tanggalAkhir = clone $tanggalObjek;
            $tanggalSelesai = $tanggalAkhir->modify('+6 days')->format('Y-m-d');

            $stmtCari = mysqli_prepare(
                $conn,
                "SELECT id_plan, kategori_protein
                 FROM planning_mingguan
                 WHERE id_user = ? AND id_anak = ? AND tanggal_mulai = ?
                 ORDER BY id_plan DESC LIMIT 1"
            );
            mysqli_stmt_bind_param($stmtCari, 'iis', $idUser, $idAnak, $tanggalMulai);
            mysqli_stmt_execute($stmtCari);
            $planAda = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCari));

            if ($planAda) {
                $idPlan = (int) $planAda['id_plan'];

                if (($planAda['kategori_protein'] ?? '') !== $kategoriDipilih) {
                    $stmtHapus = mysqli_prepare(
                        $conn,
                        "DELETE FROM planning_detail WHERE id_plan = ?"
                    );
                    mysqli_stmt_bind_param($stmtHapus, 'i', $idPlan);
                    mysqli_stmt_execute($stmtHapus);
                }

                $stmtSimpan = mysqli_prepare(
                    $conn,
                    "UPDATE planning_mingguan
                     SET tanggal_selesai = ?, kategori_protein = ?
                     WHERE id_plan = ?"
                );
                mysqli_stmt_bind_param(
                    $stmtSimpan,
                    'ssi',
                    $tanggalSelesai,
                    $kategoriDipilih,
                    $idPlan
                );
            } else {
                $stmtSimpan = mysqli_prepare(
                    $conn,
                    "INSERT INTO planning_mingguan
                     (id_user, id_anak, tanggal_mulai, tanggal_selesai, kategori_protein)
                     VALUES (?, ?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param(
                    $stmtSimpan,
                    'iisss',
                    $idUser,
                    $idAnak,
                    $tanggalMulai,
                    $tanggalSelesai,
                    $kategoriDipilih
                );
            }

            mysqli_stmt_execute($stmtSimpan);

            $_SESSION['kategori_protein_planning'] = $kategoriDipilih;
            $_SESSION['tanggal_mulai_planning'] = $tanggalMulai;

            header('Location: /index.php?page=planning_mingguan');
            exit();

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Sumber Protein — NutriPlan</title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="/assets/css/dashboard.css"
    >

</head>

<body>

<?php include ROOT . '/includes/navbar.php'; ?>

<div class="dashboard-wrap">

    <?php include ROOT . '/includes/sidebar.php'; ?>

    <main class="dashboard-content">

        <div class="sp-page">

            <!-- HEADER -->
            <div class="sp-header">

                <span class="sp-step">
                    Langkah 1
                </span>

                <h1>
                    Pilih Sumber Protein
                </h1>

                <p>
                    Pilih jenis sumber protein utama yang ingin digunakan
                    pada rekomendasi menu anak selama satu minggu.
                </p>

            </div>

            <!-- ERROR -->
            <?php if ($error): ?>

                <div class="sp-alert">
                    <?= htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>

            <!-- DATA ANAK BELUM ADA -->
            <?php if (!$anak): ?>

                <div class="sp-empty">

                    <h3>
                        Data anak belum diisi
                    </h3>

                    <p>
                        Isi data anak terlebih dahulu sebelum membuat
                        planning menu.
                    </p>

                    <a href="/index.php?page=data_anak">
                        Isi Data Anak
                    </a>

                </div>

            <?php else: ?>

                <form
                    method="POST"
                    class="sp-form-card"
                >

                    <input
                        type="hidden"
                        name="aksi"
                        value="lanjut"
                    >

                    <!-- JUDUL PILIHAN -->
                    <div class="sp-form-heading">

                        <h2>
                            Pilih salah satu
                        </h2>

                        <p>
                            Menu yang ditampilkan pada planning mingguan
                            akan disesuaikan dengan pilihan sumber protein.
                        </p>

                    </div>

                    <!-- PILIHAN PROTEIN -->
                    <div class="sp-options">

                        <?php foreach ($pilihanProtein as $key => $pilihan): ?>

                            <label class="sp-option">

                                <input
                                    type="radio"
                                    name="kategori_protein"
                                    value="<?= htmlspecialchars($key); ?>"
                                    <?= $kategoriDipilih === $key
                                        ? 'checked'
                                        : ''; ?>
                                    required
                                >

                                <span class="sp-option-card">

                                    <span class="sp-option-radio"></span>

                                    <span class="sp-option-icon">
                                        <?= htmlspecialchars(
                                            $pilihan['icon']
                                        ); ?>
                                    </span>

                                    <span class="sp-option-content">

                                        <strong class="sp-option-title">
                                            <?= htmlspecialchars(
                                                $pilihan['label']
                                            ); ?>
                                        </strong>

                                        <span class="sp-option-example">
                                            <?= htmlspecialchars(
                                                $pilihan['asal']
                                            ); ?>
                                        </span>

                                        <span class="sp-option-description">
                                            Contoh:
                                            <?= htmlspecialchars(
                                                $pilihan['contoh']
                                            ); ?>
                                        </span>

                                    </span>

                                </span>

                            </label>

                        <?php endforeach; ?>

                    </div>

                    <!-- CATATAN -->
                    <div class="sp-note">

                        <strong>Catatan:</strong>

                        Protein dari hewan dan tumbuhan sama-sama dapat
                        menjadi bagian dari menu anak. Pilihan ini hanya
                        digunakan untuk menyesuaikan jenis menu yang
                        ditampilkan pada planning mingguan.

                    </div>

                    <!-- TANGGAL -->
                    <div class="sp-field">

                        <label for="tanggal_mulai">
                            Mulai planning
                        </label>

                        <input
                            type="date"
                            id="tanggal_mulai"
                            name="tanggal_mulai"
                            value="<?= htmlspecialchars(
                                $tanggalMulai
                            ); ?>"
                            required
                        >

                        <div class="sp-help">
                            Pilih hari Senin sebagai tanggal mulai planning
                            untuk Senin sampai Minggu.
                        </div>

                    </div>

                    <button
                        type="submit"
                        class="sp-submit"
                    >
                        Lanjut ke Planning Mingguan
                    </button>

                </form>

            <?php endif; ?>

        </div>

    </main>

</div>

<script src="/assets/js/script.js"></script>

</body>

</html>