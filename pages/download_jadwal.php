<?php
/*
|--------------------------------------------------------------------------
| DOWNLOAD JADWAL MENU MINGGUAN DALAM PDF
|--------------------------------------------------------------------------
| Install Dompdf terlebih dahulu dari folder root project:
|
| composer require dompdf/dompdf
|
| Halaman ini disarankan dipanggil melalui:
| /index.php?page=download_jadwal_pdf&id=ID_PLAN
*/

$id_user = (int) ($_SESSION['id_user'] ?? 0);

if ($id_user <= 0) {
    header('Location: /index.php?page=login');
    exit();
}

$id_plan = (int) ($_GET['id'] ?? 0);

if ($id_plan <= 0) {
    die('ID planning tidak valid.');
}

/*
|--------------------------------------------------------------------------
| PASTIKAN DOMPDF TERPASANG
|--------------------------------------------------------------------------
*/
$autoload = ROOT . '/vendor/autoload.php';

if (!file_exists($autoload)) {
    die(
        'Dompdf belum terpasang. Jalankan: composer require dompdf/dompdf'
    );
}

require_once $autoload;

/*
|--------------------------------------------------------------------------
| AMBIL DATA PLANNING
|--------------------------------------------------------------------------
*/
$stmtPlan = mysqli_prepare(
    $conn,
    "SELECT
        pm.*,
        da.nama_anak,
        da.usia,
        da.jenis_kelamin
     FROM planning_mingguan pm
     INNER JOIN data_anak da
        ON da.id_anak = pm.id_anak
     WHERE pm.id_plan = ?
       AND pm.id_user = ?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmtPlan,
    'ii',
    $id_plan,
    $id_user
);

mysqli_stmt_execute($stmtPlan);

$plan = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmtPlan)
);

if (!$plan) {
    die('Planning tidak ditemukan.');
}

/*
|--------------------------------------------------------------------------
| AMBIL DETAIL MENU
|--------------------------------------------------------------------------
*/
$stmtDetail = mysqli_prepare(
    $conn,
    "SELECT
        pd.hari_ke,
        pd.nama_hari,
        pd.tanggal,
        pd.waktu_makan,
        m.nama_menu,
        m.energi,
        m.protein
     FROM planning_detail pd
     INNER JOIN menu m
        ON m.id_menu = pd.id_menu
     WHERE pd.id_plan = ? AND pd.hari_ke BETWEEN 1 AND 7
           AND pd.waktu_makan IN ('pagi', 'siang', 'malam')
     ORDER BY
        pd.hari_ke ASC,
        FIELD(pd.waktu_makan, 'pagi', 'siang', 'malam') ASC"
);

mysqli_stmt_bind_param(
    $stmtDetail,
    'i',
    $id_plan
);

mysqli_stmt_execute($stmtDetail);

$hasilDetail = mysqli_stmt_get_result($stmtDetail);

$jadwal = [];

while ($row = mysqli_fetch_assoc($hasilDetail)) {
    $hari_ke = (int) $row['hari_ke'];
    $slot = $row['waktu_makan'];

    if (!isset($jadwal[$hari_ke])) {
        $jadwal[$hari_ke] = [
            'nama_hari' => $row['nama_hari'],
            'tanggal'   => $row['tanggal'],
            'pagi'      => null,
            'siang'     => null,
            'malam'     => null
        ];
    }

    $jadwal[$hari_ke][$slot] = $row;
}

$hari_list = [
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
    7 => 'Minggu'
];

function formatTanggalPdf(?string $tanggal): string
{
    if (!$tanggal) {
        return '-';
    }

    return date('d-m-Y', strtotime($tanggal));
}

function menuPdf(?array $menu): string
{
    if (!$menu) {
        return '-';
    }

    $hasil = htmlspecialchars($menu['nama_menu']);

    $info = [];

    if ($menu['energi'] !== null && $menu['energi'] !== '') {
        $info[] = number_format(
            (float) $menu['energi'],
            0,
            ',',
            '.'
        ) . ' kkal';
    }

    if ($menu['protein'] !== null && $menu['protein'] !== '') {
        $info[] = 'Protein '
            . number_format(
                (float) $menu['protein'],
                1,
                ',',
                '.'
            )
            . ' g';
    }

    if ($info) {
        $hasil .= '<br><span class="gizi">'
            . htmlspecialchars(implode(' | ', $info))
            . '</span>';
    }

    return $hasil;
}

ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <style>
        @page {
            margin: 28px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 11px;
        }

        h1 {
            margin: 0 0 5px;
            color: #0d4715;
            font-size: 21px;
        }

        .subtitle {
            margin: 0 0 18px;
            color: #64748b;
            font-size: 10px;
        }

        .info {
            width: 100%;
            margin-bottom: 18px;
            border-collapse: collapse;
        }

        .info td {
            width: 25%;
            padding: 8px;
            border: 1px solid #dbe3dd;
        }

        .info-label {
            display: block;
            margin-bottom: 3px;
            color: #64748b;
            font-size: 8px;
            text-transform: uppercase;
        }

        .info-value {
            color: #111827;
            font-weight: bold;
        }

        .jadwal {
            width: 100%;
            border-collapse: collapse;
        }

        .jadwal th {
            padding: 9px 7px;
            border: 1px solid #cad5cc;
            background: #0d4715;
            color: #ffffff;
            font-size: 9px;
            text-align: left;
        }

        .jadwal td {
            padding: 9px 7px;
            border: 1px solid #dbe3dd;
            vertical-align: top;
            line-height: 1.45;
        }

        .hari {
            width: 13%;
            background: #f3f7f3;
            font-weight: bold;
        }

        .tanggal {
            display: block;
            margin-top: 4px;
            color: #64748b;
            font-size: 8px;
            font-weight: normal;
        }

        .gizi {
            color: #64748b;
            font-size: 8px;
        }

        .footer {
            margin-top: 16px;
            color: #64748b;
            font-size: 8px;
            text-align: center;
        }
    </style>
</head>

<body>

    <h1>Jadwal Menu Mingguan</h1>

    <p class="subtitle">
        Sarapan, makan siang/bekal sekolah, dan makan malam
        dari Senin sampai Minggu.
    </p>

    <table class="info">
        <tr>
            <td>
                <span class="info-label">Nama Anak</span>
                <span class="info-value">
                    <?= htmlspecialchars(
                        $plan['nama_anak'] ?? '-'
                    ); ?>
                </span>
            </td>

            <td>
                <span class="info-label">Usia</span>
                <span class="info-value">
                    <?= (int) ($plan['usia'] ?? 0); ?> tahun
                </span>
            </td>

            <td>
                <span class="info-label">Periode</span>
                <span class="info-value">
                    <?= htmlspecialchars(
                        formatTanggalPdf(
                            $plan['tanggal_mulai'] ?? null
                        )
                    ); ?>
                    -
                    <?= htmlspecialchars(
                        formatTanggalPdf(
                            $plan['tanggal_selesai'] ?? null
                        )
                    ); ?>
                </span>
            </td>

            <td>
                <span class="info-label">Sumber Protein</span>
                <span class="info-value">
                    <?= htmlspecialchars(
                        ucfirst(
                            $plan['kategori_protein'] ?? '-'
                        )
                    ); ?>
                </span>
            </td>
        </tr>
    </table>

    <table class="jadwal">
        <thead>
            <tr>
                <th>Hari</th>
                <th>Sarapan</th>
                <th>Makan Siang / Bekal</th>
                <th>Makan Malam</th>
            </tr>
        </thead>

        <tbody>

            <?php foreach ($hari_list as $hari_ke => $nama_hari): ?>

                <?php
                $data_hari = $jadwal[$hari_ke] ?? [
                    'tanggal' => null,
                    'pagi'    => null,
                    'siang'   => null,
                    'malam'   => null
                ];
                ?>

                <tr>
                    <td class="hari">
                        <?= htmlspecialchars($nama_hari); ?>

                        <span class="tanggal">
                            <?= htmlspecialchars(
                                formatTanggalPdf(
                                    $data_hari['tanggal']
                                )
                            ); ?>
                        </span>
                    </td>

                    <td>
                        <?= menuPdf($data_hari['pagi']); ?>
                    </td>

                    <td>
                        <?= menuPdf($data_hari['siang']); ?>
                    </td>

                    <td>
                        <?= menuPdf($data_hari['malam']); ?>
                    </td>
                </tr>

            <?php endforeach; ?>

        </tbody>
    </table>

    <div class="footer">
        Jadwal menu ini dapat disimpan dan dibuka kembali secara offline.
    </div>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new \Dompdf\Options();
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new \Dompdf\Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

/*
|--------------------------------------------------------------------------
| BERSIHKAN OUTPUT BUFFER SEBELUM MENGIRIM PDF
|--------------------------------------------------------------------------
*/
while (ob_get_level() > 0) {
    ob_end_clean();
}

$nama_file = 'jadwal-menu-'
    . preg_replace(
        '/[^a-z0-9]+/i',
        '-',
        strtolower(
            $plan['nama_anak'] ?? 'anak'
        )
    )
    . '.pdf';

$dompdf->stream(
    $nama_file,
    [
        'Attachment' => true
    ]
);

exit();