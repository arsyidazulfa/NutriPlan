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
   MENU LAINNYA
   ============================================= */

$stmt = mysqli_prepare(
    $conn,
    'SELECT
        id_menu,
        nama_menu,
        gambar,
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
                        🍱
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


                <!-- GIZI -->
                <div class="menu-detail-nutrition">

                    <h4>
                        Kandungan Gizi
                    </h4>

                    <div class="nutrition-grid">

                        <div class="nutrition-item">
                            <span class="val">
                                <?= number_format(
                                    (float) ($menu['energi'] ?? 0),
                                    0,
                                    ',',
                                    '.'
                                ); ?>
                            </span>
                            <span class="lbl">kkal Energi</span>
                        </div>


                        <div class="nutrition-item">
                            <span class="val">
                                <?= number_format(
                                    (float) ($menu['protein'] ?? 0),
                                    1,
                                    ',',
                                    '.'
                                ); ?> g
                            </span>
                            <span class="lbl">Protein</span>
                        </div>


                        <div class="nutrition-item">
                            <span class="val">
                                <?= number_format(
                                    (float) ($menu['lemak'] ?? 0),
                                    1,
                                    ',',
                                    '.'
                                ); ?> g
                            </span>
                            <span class="lbl">Lemak</span>
                        </div>


                        <div class="nutrition-item">
                            <span class="val">
                                <?= number_format(
                                    (float) ($menu['karbohidrat'] ?? 0),
                                    1,
                                    ',',
                                    '.'
                                ); ?> g
                            </span>
                            <span class="lbl">Karbohidrat</span>
                        </div>


                        <div class="nutrition-item">
                            <span class="val">
                                <?= number_format(
                                    (float) ($menu['serat'] ?? 0),
                                    1,
                                    ',',
                                    '.'
                                ); ?> g
                            </span>
                            <span class="lbl">Serat</span>
                        </div>

                    </div>

                </div>


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
                                    🍱
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