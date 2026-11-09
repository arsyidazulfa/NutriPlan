<?php

/** @var mysqli $conn */

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: /index.php?page=menu');
    exit();
}


/* =============================================
   DETAIL MENU
   ============================================= */

$stmt = mysqli_prepare(
    $conn,
    'SELECT * FROM menu WHERE id_menu = ?'
);

mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);

$menu = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
);

mysqli_stmt_close($stmt);

if (!$menu) {
    header('Location: /index.php?page=menu');
    exit();
}


/* =============================================
   RINCIAN BAHAN DAN PERHITUNGAN GIZI
   ============================================= */

require_once ROOT . '/helpers/gizi_menu.php';
require_once ROOT . '/helpers/ikon_menu.php';

try {
    $komposisi = ambilKomposisiMenu($conn, $id);
} catch (RuntimeException $e) {
    $komposisi = [];
}

$bahanResep = array_values(array_filter(array_map(
    'trim',
    preg_split('/\r\n|\r|\n/', (string) ($menu['bahan_makanan'] ?? ''))
)));


/* =============================================
   MENU LAINNYA
   ============================================= */

$stmt = mysqli_prepare(
    $conn,
    'SELECT
        id_menu,
        nama_menu,
        gambar,
        jenis_lauk,
        energi,
        protein
     FROM menu
     WHERE id_menu != ?
     ORDER BY RAND()
     LIMIT 3'
);

mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);

$related = mysqli_fetch_all(
    mysqli_stmt_get_result($stmt),
    MYSQLI_ASSOC
);

mysqli_stmt_close($stmt);


/* =============================================
   DATA TAMPILAN
   ============================================= */

$alergiList = array_filter(
    array_map(
        'trim',
        explode(',', $menu['alergi_kandungan'] ?? '')
    )
);

$waktuLabels = [
    'pagi' => '🌅 Sarapan',
    'siang' => '☀️ Makan Siang',
    'malam' => '🌙 Makan Malam'
];

$waktuList = array_filter(
    array_map(
        'trim',
        explode(',', $menu['waktu_makan'] ?? '')
    )
);

$kategoriProtein = match ($menu['kategori_protein'] ?? '') {
    'hewani' => 'Protein Hewani',
    'nabati' => 'Protein Nabati',
    default => ''
};

$caraMasak = trim(
    (string) ($menu['cara_masak'] ?? '')
);

$langkah = [];

if ($caraMasak !== '') {

    $langkah = preg_split(
        '/\r\n|\r|\n/',
        $caraMasak
    );

    if (count($langkah) === 1) {
        $langkah = preg_split(
            '/(?<=[.!?])\s+(?=[A-Z0-9])/u',
            $caraMasak
        );
    }

    $langkah = array_values(
        array_filter(
            array_map('trim', $langkah)
        )
    );
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

    <title>
        <?= htmlspecialchars($menu['nama_menu']); ?> — NutriPlan
    </title>

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

    <main class="dashboard-content">


        <a
            href="/index.php?page=menu"
            class="edukasi-back"
        >
            ← Kembali
        </a>


        <div class="menu-detail-layout">


            <!-- GAMBAR -->
            <div>

                <?php if (
                    !empty($menu['gambar'])
                    && file_exists(
                        ROOT . '/assets/images/' . $menu['gambar']
                    )
                ): ?>

                    <img
                        class="menu-detail-img"
                        src="/assets/images/<?= htmlspecialchars($menu['gambar']); ?>"
                        alt="<?= htmlspecialchars($menu['nama_menu']); ?>"
                    >

                <?php else: ?>

                    <div class="menu-detail-no-img">
                        <?= ikonMenuHtml($menu); ?>
                    </div>

                <?php endif; ?>

            </div>


            <!-- INFORMASI -->
            <div>

                <h1 class="menu-detail-title">
                    <?= htmlspecialchars($menu['nama_menu']); ?>
                </h1>


                <!-- BADGE -->
                <div class="menu-detail-badges">

                    <?php if ($kategoriProtein): ?>

                        <span class="menu-detail-badge">
                            <?= htmlspecialchars($kategoriProtein); ?>
                        </span>

                    <?php endif; ?>


                    <?php foreach ($waktuList as $waktu): ?>

                        <?php if (isset($waktuLabels[$waktu])): ?>

                            <span class="menu-detail-badge">
                                <?= $waktuLabels[$waktu]; ?>
                            </span>

                        <?php endif; ?>

                    <?php endforeach; ?>

                </div>


                <!-- DESKRIPSI -->
                <?php if (!empty($menu['deskripsi'])): ?>

                    <p class="menu-detail-desc">
                        <?= nl2br(
                            htmlspecialchars($menu['deskripsi'])
                        ); ?>
                    </p>

                <?php endif; ?>




                <!-- RINCIAN PERHITUNGAN GIZI -->
                <?php if ($komposisi): ?>

                    <div class="menu-detail-steps">

                        <h4>
                            Rincian Perhitungan Gizi
                        </h4>

                        <table class="rincian-gizi">

                            <thead>
                                <tr>
                                    <th>Bahan (TKPI)</th>
                                    <th>Takaran</th>
                                    <th>Berat</th>
                                    <th>Energi</th>
                                    <th>Protein</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($komposisi as $bahan): ?>

                                    <?php $giziBahan = hitungGiziMenu([$bahan]); ?>

                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($bahan['nama_bahan']); ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($bahan['urt'] ?? '-'); ?>
                                        </td>
                                        <td>
                                            <?= number_format((float) $bahan['berat_gram'], 0, ',', '.'); ?> g
                                        </td>
                                        <td>
                                            <?= number_format($giziBahan['energi'], 1, ',', '.'); ?> kkal
                                        </td>
                                        <td>
                                            <?= number_format($giziBahan['protein'], 1, ',', '.'); ?> g
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                            <tfoot>
                                <tr>
                                    <td colspan="3">Jumlah 1 porsi lauk</td>
                                    <td>
                                        <?= number_format((float) ($menu['energi'] ?? 0), 1, ',', '.'); ?> kkal
                                    </td>
                                    <td>
                                        <?= number_format((float) ($menu['protein'] ?? 0), 1, ',', '.'); ?> g
                                    </td>
                                </tr>
                            </tfoot>

                        </table>

                        <p class="rincian-gizi-catatan">
                            Berat bahan mengikuti satuan penukar pada Daftar Bahan Makanan Penukar
                            (Permenkes No. 41 Tahun 2014). Nilai gizi dihitung dari Tabel Komposisi
                            Pangan Indonesia 2017. Bumbu tidak dihitung, dan nasi ditakar terpisah
                            sesuai kebutuhan anak pada halaman planning.
                        </p>

                    </div>

                <?php endif; ?>


                <!-- BAHAN RESEP -->
                <?php if ($bahanResep): ?>

                    <div class="menu-detail-steps">

                        <h4>
                            Bahan
                        </h4>

                        <ul class="bahan-resep">

                            <?php foreach ($bahanResep as $item): ?>

                                <li>
                                    <?= htmlspecialchars($item); ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                <?php endif; ?>


                <!-- CARA MENGOLAH -->
                <?php if ($langkah): ?>

                    <div class="menu-detail-steps">

                        <h4>
                            Cara Mengolah
                        </h4>

                        <ol>

                            <?php foreach ($langkah as $item): ?>

                                <li>
                                    <?= htmlspecialchars($item); ?>
                                </li>

                            <?php endforeach; ?>

                        </ol>

                    </div>

                <?php endif; ?>


                <!-- ALERGI -->
                <?php if ($alergiList): ?>

                    <p class="menu-detail-alergi">

                        ⚠️ Mengandung alergen:

                        <strong>
                            <?= htmlspecialchars(
                                implode(', ', $alergiList)
                            ); ?>
                        </strong>

                    </p>

                <?php endif; ?>


            </div>

        </div>


        <!-- MENU LAINNYA -->
        <?php if ($related): ?>

            <div class="menu-related">

                <div class="menu-section-title">
                    Menu Lainnya
                </div>

                <div class="menu-grid">

                    <?php foreach ($related as $item): ?>

                        <a
                            href="/index.php?page=menu_detail&id=<?= (int) $item['id_menu']; ?>"
                            class="menu-card"
                        >

                            <?php if (
                                !empty($item['gambar'])
                                && file_exists(
                                    ROOT . '/assets/images/' . $item['gambar']
                                )
                            ): ?>

                                <img
                                    src="/assets/images/<?= htmlspecialchars($item['gambar']); ?>"
                                    alt="<?= htmlspecialchars($item['nama_menu']); ?>"
                                >

                            <?php else: ?>

                                <div class="menu-card-no-img">
                                    <?= ikonMenuHtml($item); ?>
                                </div>

                            <?php endif; ?>


                            <div class="menu-card-label">

                                <?= htmlspecialchars($item['nama_menu']); ?>

                                <div class="menu-related-gizi">

                                    <?= number_format(
                                        (float) $item['energi'],
                                        0,
                                        ',',
                                        '.'
                                    ); ?>
                                    kkal

                                    &nbsp;·&nbsp;

                                    <?= number_format(
                                        (float) $item['protein'],
                                        1,
                                        ',',
                                        '.'
                                    ); ?>
                                    g protein

                                </div>

                            </div>

                        </a>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php endif; ?>

    </main>
</div>
<script src="/assets/js/script.js"></script>
</body>
</html>