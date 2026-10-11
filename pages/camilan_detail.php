<?php

$idCamilan = (int) ($_GET['id'] ?? 0);

if ($idCamilan <= 0) {
    header('Location: /index.php?page=camilan_sehat');
    exit();
}


/* =============================================
   DATA CAMILAN
   ============================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM camilan_sehat
     WHERE id_camilan = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, 'i', $idCamilan);
mysqli_stmt_execute($stmt);

$camilan = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
);

mysqli_stmt_close($stmt);


if (!$camilan) {
    http_response_code(404);
    include ROOT . '/pages/404.php';
    exit();
}


/* =============================================
   HELPER
   ============================================= */

function detailNilaiGizi(
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


function detailCaraMasak(string $teks): array
{
    $teks = trim($teks);

    if ($teks === '') {
        return [];
    }

    $baris = preg_split(
        '/\r\n|\r|\n/',
        $teks
    );

    if (count($baris) === 1) {
        $baris = preg_split(
            '/(?<=[.!?])\s+(?=[A-Z0-9])/u',
            $teks
        );
    }

    return array_values(
        array_filter(
            array_map(
                function ($item) {
                    return trim(
                        preg_replace(
                            '/^\d+[\.\)]\s*/',
                            '',
                            $item
                        )
                    );
                },
                $baris
            )
        )
    );
}


function detailGambarCamilan(array $camilan): string
{
    $gambar = trim(
        (string) ($camilan['gambar'] ?? '')
    );

    if ($gambar === '') {
        return '';
    }

    if (str_starts_with($gambar, '/')) {
        return $gambar;
    }

    if (
        file_exists(
            ROOT . '/assets/images/' . $gambar
        )
    ) {
        return '/assets/images/' . $gambar;
    }

    return '';
}


$caraMasak = detailCaraMasak(
    $camilan['cara_masak'] ?? ''
);

$gambar = detailGambarCamilan($camilan);

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
        <?= htmlspecialchars($camilan['nama_camilan']); ?> — NutriPlan
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="/assets/css/dashboard.css"
    >


    <style>

        .camilan-detail {
            width: 100%;
            max-width: 1050px;
        }


        .camilan-detail-back {
            display: inline-block;
            margin-bottom: 20px;
            color: #334155;
            font-size: 14px;
            text-decoration: none;
        }

        .camilan-detail-back:hover {
            color: #0d5c1b;
        }


        .camilan-detail-header {
            margin-bottom: 24px;
        }

        .camilan-detail-header h1 {
            margin: 0 0 8px;
            color: #17233c;
            font-size: 32px;
            line-height: 1.3;
        }

        .camilan-detail-header p {
            margin: 0;
            color: #64748b;
            line-height: 1.7;
        }


        .camilan-detail-box {
            overflow: hidden;
            border: 1px solid #dce5df;
            border-radius: 16px;
            background: #fff;
        }


        .camilan-detail-image {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 230px;
            background: #effaf1;
        }

        .camilan-detail-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .camilan-detail-placeholder {
            font-size: 60px;
        }


        .camilan-detail-content {
            padding: 28px;
        }


        .camilan-detail-content h2 {
            margin: 0 0 16px;
            color: #17233c;
            font-size: 20px;
        }


        .camilan-detail-gizi {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
        }

        .camilan-detail-gizi div {
            padding: 14px 10px;
            border-radius: 10px;
            background: #f8fafc;
            text-align: center;
        }

        .camilan-detail-gizi span {
            display: block;
            margin-bottom: 5px;
            color: #94a3b8;
            font-size: 11px;
        }

        .camilan-detail-gizi strong {
            display: block;
            color: #17233c;
            font-size: 13px;
        }


        .camilan-detail-cara {
            margin-top: 30px;
        }

        .camilan-detail-cara ol {
            margin: 0;
            padding-left: 22px;
            color: #334155;
            line-height: 2;
        }

        .camilan-detail-cara li {
            padding-left: 3px;
        }


        @media (max-width: 900px) {

            .camilan-detail-gizi {
                grid-template-columns: repeat(2, 1fr);
            }

        }


        @media (max-width: 600px) {

            .camilan-detail-header h1 {
                font-size: 26px;
            }

            .camilan-detail-content {
                padding: 20px;
            }

            .camilan-detail-gizi {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>


<?php include ROOT . '/includes/navbar.php'; ?>


<div class="dashboard-wrap">

    <?php include ROOT . '/includes/sidebar.php'; ?>


    <main class="dashboard-content">

        <div class="camilan-detail">


            <a
                href="/index.php?page=camilan_sehat"
                class="camilan-detail-back"
            >
                ← Kembali ke Camilan Sehat
            </a>


            <div class="camilan-detail-header">

                <h1>
                    <?= htmlspecialchars(
                        $camilan['nama_camilan']
                    ); ?>
                </h1>

                <?php if (!empty($camilan['deskripsi'])): ?>

                    <p>
                        <?= htmlspecialchars(
                            $camilan['deskripsi']
                        ); ?>
                    </p>

                <?php endif; ?>

            </div>


            <div class="camilan-detail-box">


                <div class="camilan-detail-image">

                    <?php if ($gambar): ?>

                        <img
                            src="<?= htmlspecialchars($gambar); ?>"
                            alt="<?= htmlspecialchars(
                                $camilan['nama_camilan']
                            ); ?>"
                        >

                    <?php else: ?>

                        <div class="camilan-detail-placeholder">
                            🍎
                        </div>

                    <?php endif; ?>

                </div>


                <div class="camilan-detail-content">


                    <section>

                        <h2>
                            Kandungan Gizi
                        </h2>


                        <div class="camilan-detail-gizi">

                            <div>
                                <span>Energi</span>

                                <strong>
                                    <?= htmlspecialchars(
                                        detailNilaiGizi(
                                            $camilan['energi'],
                                            'kkal'
                                        )
                                    ); ?>
                                </strong>
                            </div>


                            <div>
                                <span>Protein</span>

                                <strong>
                                    <?= htmlspecialchars(
                                        detailNilaiGizi(
                                            $camilan['protein'],
                                            'g'
                                        )
                                    ); ?>
                                </strong>
                            </div>


                            <div>
                                <span>Karbohidrat</span>

                                <strong>
                                    <?= htmlspecialchars(
                                        detailNilaiGizi(
                                            $camilan['karbohidrat'],
                                            'g'
                                        )
                                    ); ?>
                                </strong>
                            </div>


                            <div>
                                <span>Lemak</span>

                                <strong>
                                    <?= htmlspecialchars(
                                        detailNilaiGizi(
                                            $camilan['lemak'],
                                            'g'
                                        )
                                    ); ?>
                                </strong>
                            </div>


                            <div>
                                <span>Serat</span>

                                <strong>
                                    <?= htmlspecialchars(
                                        detailNilaiGizi(
                                            $camilan['serat'],
                                            'g'
                                        )
                                    ); ?>
                                </strong>
                            </div>

                        </div>

                    </section>


                    <?php if ($caraMasak): ?>

                        <section class="camilan-detail-cara">

                            <h2>
                                Cara Mengolah
                            </h2>

                            <ol>

                                <?php foreach ($caraMasak as $langkah): ?>

                                    <li>
                                        <?= htmlspecialchars($langkah); ?>
                                    </li>

                                <?php endforeach; ?>

                            </ol>

                        </section>

                    <?php endif; ?>


                </div>

            </div>

        </div>

    </main>

</div>


<script src="/assets/js/script.js"></script>

</body>

</html>