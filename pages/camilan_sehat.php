<?php

/** @var mysqli $conn */

$idUser = (int) ($_SESSION['id_user'] ?? 0);

if ($idUser <= 0) {
    header('Location: /index.php?page=login');
    exit();
}

require_once ROOT . '/helpers/rekomendasi.php';


/* =============================================
   DATA ANAK
   ============================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM data_anak
     WHERE id_user = ?
     ORDER BY id_anak DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, 'i', $idUser);
mysqli_stmt_execute($stmt);

$anak = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
) ?: null;

mysqli_stmt_close($stmt);


/* =============================================
   DATA CAMILAN
   ============================================= */

$result = mysqli_query(
    $conn,
    "SELECT *
     FROM camilan_sehat
     ORDER BY nama_camilan ASC"
);

$camilans = [];

while ($result && $camilan = mysqli_fetch_assoc($result)) {

    if (
        $anak
        && !menuAmanUntukAnak($camilan, $anak)
    ) {
        continue;
    }

    $camilans[] = $camilan;
}


/* =============================================
   HELPER
   ============================================= */

function camilanEsc(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function gambarCamilan(array $camilan): string
{
    $gambar = trim(
        (string) ($camilan['gambar'] ?? '')
    );

    if (
        $gambar === ''
        || !file_exists(ROOT . '/assets/images/' . $gambar)
    ) {
        return '';
    }

    return '/assets/images/' . ltrim($gambar, '/');
}


function nilaiCamilan(
    mixed $nilai,
    string $satuan
): string {

    if ($nilai === null || $nilai === '') {
        return '-';
    }

    $angka = (float) $nilai;

    $hasil = floor($angka) == $angka
        ? number_format($angka, 0, ',', '.')
        : number_format($angka, 1, ',', '.');

    return $hasil . ' ' . $satuan;
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

    <title>Camilan Sehat — NutriPlan</title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="/assets/css/dashboard.css"
    >


    <style>

        .cs-page {
            width: 100%;
        }


        .cs-header {
            margin-bottom: 30px;
        }

        .cs-header h1 {
            margin: 0 0 8px;
            color: #17233c;
            font-size: 32px;
        }

        .cs-header p {
            margin: 0;
            color: #64748b;
            font-size: 15px;
        }


        .cs-grid {
            display: grid;
            grid-template-columns: repeat(3, 360px);
            gap: 24px;
            align-items: start;
        }


        .cs-card {
            display: block;
            overflow: hidden;
            border: 1px solid #dce5df;
            border-radius: 16px;
            background: #fff;
            color: inherit;
            text-decoration: none;
            transition: 0.2s ease;
        }

        .cs-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.06);
        }


        .cs-image {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 180px;
            background: #edf9ef;
        }

        .cs-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cs-placeholder {
            font-size: 52px;
        }


        .cs-body {
            padding: 20px;
        }

        .cs-body h3 {
            margin: 0 0 10px;
            color: #17233c;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.4;
        }


        .cs-description {
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


        .cs-gizi {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            overflow: hidden;
            border-radius: 10px;
            background: #f8fafc;
        }

        .cs-gizi > div {
            padding: 12px;
            text-align: center;
        }

        .cs-gizi > div:first-child {
            border-right: 1px solid #e2e8f0;
        }

        .cs-gizi strong {
            display: block;
            color: #17233c;
            font-size: 13px;
            font-weight: 700;
        }

        .cs-gizi span {
            display: block;
            margin-top: 3px;
            color: #94a3b8;
            font-size: 10px;
        }


        .cs-empty {
            padding: 30px;
            border-radius: 12px;
            background: #f8fafc;
            color: #64748b;
            text-align: center;
        }


        @media (max-width: 1200px) {

            .cs-grid {
                grid-template-columns: repeat(2, 360px);
            }

        }


        @media (max-width: 800px) {

            .cs-grid {
                grid-template-columns: 1fr;
            }

            .cs-card {
                max-width: 360px;
            }

        }

    </style>

</head>

<body>

<?php include ROOT . '/includes/navbar.php'; ?>


<div class="dashboard-wrap">

    <?php include ROOT . '/includes/sidebar.php'; ?>


    <main class="dashboard-content">

        <div class="cs-page">


            <div class="cs-header">

                <h1>
                    Camilan Sehat
                </h1>

                <p>
                    Pilihan camilan sehat sebagai makanan
                    selingan untuk anak.
                </p>

            </div>


            <?php if ($camilans): ?>

                <div class="cs-grid">


                    <?php foreach ($camilans as $camilan): ?>

                        <?php

                        $idCamilan = (int) $camilan['id_camilan'];
                        $gambar = gambarCamilan($camilan);

                        ?>


                        <a
                            href="/index.php?page=camilan_detail&id=<?= $idCamilan; ?>"
                            class="cs-card"
                        >


                            <div class="cs-image">

                                <?php if ($gambar): ?>

                                    <img
                                        src="<?= camilanEsc($gambar); ?>"
                                        alt="<?= camilanEsc(
                                            $camilan['nama_camilan']
                                        ); ?>"
                                        loading="lazy"
                                    >

                                <?php else: ?>

                                    <div class="cs-placeholder">
                                        🍎
                                    </div>

                                <?php endif; ?>

                            </div>


                            <div class="cs-body">

                                <h3>
                                    <?= camilanEsc(
                                        $camilan['nama_camilan']
                                    ); ?>
                                </h3>


                                <p class="cs-description">
                                    <?= camilanEsc(
                                        $camilan['deskripsi'] ?? ''
                                    ); ?>
                                </p>


                                <div class="cs-gizi">

                                    <div>

                                        <strong>
                                            <?= camilanEsc(
                                                nilaiCamilan(
                                                    $camilan['energi'],
                                                    'kkal'
                                                )
                                            ); ?>
                                        </strong>

                                        <span>
                                            Energi
                                        </span>

                                    </div>


                                    <div>

                                        <strong>
                                            <?= camilanEsc(
                                                nilaiCamilan(
                                                    $camilan['protein'],
                                                    'g'
                                                )
                                            ); ?>
                                        </strong>

                                        <span>
                                            Protein
                                        </span>

                                    </div>

                                </div>

                            </div>

                        </a>

                    <?php endforeach; ?>


                </div>


            <?php else: ?>

                <div class="cs-empty">
                    Belum ada camilan yang tersedia.
                </div>

            <?php endif; ?>


        </div>

    </main>

</div>


<script src="/assets/js/script.js"></script>

</body>
</html>