<?php

/** @var mysqli $conn */

$kategori = $_GET['kategori'] ?? 'makan';

if (!in_array($kategori, ['makan', 'camilan'], true)) {
    $kategori = 'makan';
}

$menus = [];
$error = '';


/* =============================================
   DATA
   ============================================= */

if ($kategori === 'camilan') {

    $result = mysqli_query(
        $conn,
        "SELECT
            id_camilan,
            nama_camilan,
            deskripsi,
            gambar,
            energi,
            protein
         FROM camilan_sehat
         ORDER BY nama_camilan ASC"
    );

    $errorMessage = 'Data camilan tidak dapat ditampilkan.';

} else {

    $result = mysqli_query(
        $conn,
        "SELECT
            id_menu,
            nama_menu,
            deskripsi,
            gambar,
            energi,
            protein,
            kategori_protein,
            waktu_makan
         FROM menu
         WHERE FIND_IN_SET('pagi', waktu_makan) > 0
            OR FIND_IN_SET('siang', waktu_makan) > 0
            OR FIND_IN_SET('malam', waktu_makan) > 0
         ORDER BY nama_menu ASC"
    );

    $errorMessage = 'Data menu tidak dapat ditampilkan.';
}


if ($result) {

    $menus = mysqli_fetch_all(
        $result,
        MYSQLI_ASSOC
    );

} else {

    $error = $errorMessage;
}


/* =============================================
   HELPER
   ============================================= */

function katalogLabelWaktu(?string $waktu): array
{
    if (!$waktu) {
        return [];
    }

    $labels = [
        'pagi' => 'Pagi',
        'siang' => 'Siang',
        'malam' => 'Malam'
    ];

    $hasil = [];

    foreach (explode(',', $waktu) as $item) {

        $item = trim($item);

        if (isset($labels[$item])) {
            $hasil[] = $labels[$item];
        }
    }

    return $hasil;
}


function katalogLabelProtein(?string $protein): string
{
    if ($protein === 'hewani') {
        return 'Protein Hewani';
    }

    if ($protein === 'nabati') {
        return 'Protein Nabati';
    }

    return '';
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

    <title>Menu Sehat — NutriPlan</title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >


    <style>

        /* CARD CAMILAN */

        .menu-camilan-card {
            display: block;
            overflow: hidden;
            border: 1px solid #dce5df;
            border-radius: 16px;
            background: #fff;
            color: inherit;
            text-decoration: none;
        }

        .menu-camilan-image {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 180px;
            background: #edf9ef;
        }

        .menu-camilan-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .menu-camilan-placeholder {
            font-size: 52px;
        }

        .menu-camilan-body {
            padding: 20px;
        }

        .menu-camilan-body h3 {
            margin: 0 0 10px;
            color: #17233c;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.4;
        }

        .menu-camilan-description {
            display: -webkit-box;
            overflow: hidden;
            min-height: 42px;
            margin: 0 0 18px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .menu-camilan-gizi {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            overflow: hidden;
            border-radius: 10px;
            background: #f8fafc;
        }

        .menu-camilan-gizi > div {
            padding: 12px;
            text-align: center;
        }

        .menu-camilan-gizi > div:first-child {
            border-right: 1px solid #e2e8f0;
        }

        .menu-camilan-gizi strong {
            display: block;
            color: #17233c;
            font-size: 13px;
            font-weight: 700;
        }

        .menu-camilan-gizi span {
            display: block;
            margin-top: 3px;
            color: #94a3b8;
            font-size: 10px;
        }

    </style>

</head>

<body>


<?php include ROOT . '/includes/navbar.php'; ?>


<main class="katalog-page">


    <!-- HEADER -->

    <div class="katalog-header">

        <h1>
            Menu Sehat untuk Si Kecil
        </h1>

        <p>
            Temukan berbagai pilihan menu makan dan camilan sehat
            yang tersedia di NutriPlan.
        </p>

    </div>


    <!-- TAB -->

    <div class="katalog-tabs">

        <a
            href="/index.php?page=menu&kategori=makan"
            class="katalog-tab <?= $kategori === 'makan' ? 'active' : ''; ?>"
        >
            🍽️ Menu Makan
        </a>

        <a
            href="/index.php?page=menu&kategori=camilan"
            class="katalog-tab <?= $kategori === 'camilan' ? 'active' : ''; ?>"
        >
            🍎 Camilan Sehat
        </a>

    </div>


    <!-- ERROR -->

    <?php if ($error): ?>

        <div class="katalog-empty">

            <div class="katalog-empty-icon">
                ⚠️
            </div>

            <h3>
                Data tidak dapat ditampilkan
            </h3>

            <p>
                <?= htmlspecialchars($error); ?>
            </p>

        </div>


    <!-- ADA DATA -->

    <?php elseif ($menus): ?>

        <div class="katalog-grid">


            <?php foreach ($menus as $menu): ?>


                <?php

                $isCamilan = $kategori === 'camilan';

                $id = $isCamilan
                    ? (int) $menu['id_camilan']
                    : (int) $menu['id_menu'];

                $nama = $isCamilan
                    ? $menu['nama_camilan']
                    : $menu['nama_menu'];

                $gambarAda =
                    !empty($menu['gambar'])
                    && file_exists(
                        ROOT . '/assets/images/' . $menu['gambar']
                    );

                ?>


                <?php if ($isCamilan): ?>


                    <!-- =========================
                         CARD CAMILAN
                         ========================= -->

                    <a
                        href="/index.php?page=camilan_detail&id=<?= $id; ?>"
                        class="menu-camilan-card"
                    >


                        <div class="menu-camilan-image">

                            <?php if ($gambarAda): ?>

                                <img
                                    src="/assets/images/<?= htmlspecialchars($menu['gambar']); ?>"
                                    alt="<?= htmlspecialchars($nama); ?>"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <div class="menu-camilan-placeholder">
                                    🍎
                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="menu-camilan-body">

                            <h3>
                                <?= htmlspecialchars($nama); ?>
                            </h3>


                            <p class="menu-camilan-description">
                                <?= htmlspecialchars(
                                    $menu['deskripsi'] ?? ''
                                ); ?>
                            </p>


                            <div class="menu-camilan-gizi">

                                <div>

                                    <strong>
                                        <?= number_format(
                                            (float) ($menu['energi'] ?? 0),
                                            0,
                                            ',',
                                            '.'
                                        ); ?> kkal
                                    </strong>

                                    <span>
                                        Energi
                                    </span>

                                </div>


                                <div>

                                    <strong>
                                        <?= number_format(
                                            (float) ($menu['protein'] ?? 0),
                                            1,
                                            ',',
                                            '.'
                                        ); ?> g
                                    </strong>

                                    <span>
                                        Protein
                                    </span>

                                </div>

                            </div>

                        </div>

                    </a>


                <?php else: ?>


                    <!-- =========================
                         CARD MENU MAKAN
                         ========================= -->

                    <div class="katalog-card">


                        <!-- GAMBAR -->

                        <div class="katalog-image-wrap">

                            <?php if ($gambarAda): ?>

                                <img
                                    src="/assets/images/<?= htmlspecialchars($menu['gambar']); ?>"
                                    alt="<?= htmlspecialchars($nama); ?>"
                                    class="katalog-image"
                                >

                            <?php else: ?>

                                <div class="katalog-image-placeholder">
                                    🍱
                                </div>

                            <?php endif; ?>


                            <?php if (!empty($menu['kategori_protein'])): ?>

                                <span class="katalog-protein-badge">

                                    <?= htmlspecialchars(
                                        katalogLabelProtein(
                                            $menu['kategori_protein']
                                        )
                                    ); ?>

                                </span>

                            <?php endif; ?>

                        </div>


                        <!-- ISI CARD -->

                        <div class="katalog-card-body">


                            <!-- WAKTU MAKAN -->

                            <?php

                            $waktuList = katalogLabelWaktu(
                                $menu['waktu_makan'] ?? ''
                            );

                            ?>


                            <?php if ($waktuList): ?>

                                <div class="katalog-waktu">

                                    <?php foreach ($waktuList as $waktu): ?>

                                        <span>
                                            <?= htmlspecialchars($waktu); ?>
                                        </span>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>


                            <!-- NAMA -->

                            <h3 class="katalog-card-title">
                                <?= htmlspecialchars($nama); ?>
                            </h3>


                            <!-- DESKRIPSI -->

                            <?php if (!empty($menu['deskripsi'])): ?>

                                <p class="katalog-description">
                                    <?= htmlspecialchars($menu['deskripsi']); ?>
                                </p>

                            <?php endif; ?>


                            <!-- GIZI -->

                            <div class="katalog-gizi">

                                <div>

                                    <strong>
                                        <?= number_format(
                                            (float) ($menu['energi'] ?? 0),
                                            0,
                                            ',',
                                            '.'
                                        ); ?> kkal
                                    </strong>

                                    <span>
                                        Energi
                                    </span>

                                </div>


                                <div>

                                    <strong>
                                        <?= number_format(
                                            (float) ($menu['protein'] ?? 0),
                                            1,
                                            ',',
                                            '.'
                                        ); ?> g
                                    </strong>

                                    <span>
                                        Protein
                                    </span>

                                </div>

                            </div>


                            <!-- DETAIL -->

                            <a
                                href="/index.php?page=menu_detail&id=<?= $id; ?>"
                                class="katalog-detail-btn"
                            >
                                <span>
                                    Lihat Detail Menu
                                </span>

                                <span>
                                    →
                                </span>
                            </a>

                        </div>

                    </div>


                <?php endif; ?>


            <?php endforeach; ?>


        </div>


    <!-- KOSONG -->

    <?php else: ?>

        <div class="katalog-empty">

            <div class="katalog-empty-icon">
                <?= $kategori === 'camilan' ? '🍎' : '🍱'; ?>
            </div>

            <h3>
                Belum ada data tersedia
            </h3>

        </div>

    <?php endif; ?>


</main>


<?php include ROOT . '/includes/footer.php'; ?>


<script src="/assets/js/script.js"></script>

</body>

</html>