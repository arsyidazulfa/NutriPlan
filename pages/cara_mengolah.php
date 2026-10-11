<?php

/** @var mysqli $conn */

$idUser = (int) ($_SESSION['id_user'] ?? 0);

if ($idUser <= 0) {
    header('Location: /index.php?page=login');
    exit();
}

$idPlan = (int) ($_GET['id'] ?? 0);


/* =========================================================
   AMBIL PLANNING
   ========================================================= */

if ($idPlan > 0) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT pm.*, da.*
         FROM planning_mingguan pm
         INNER JOIN data_anak da ON da.id_anak = pm.id_anak
         WHERE pm.id_plan = ?
           AND pm.id_user = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, 'ii', $idPlan, $idUser);

} else {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT pm.*, da.*
         FROM planning_mingguan pm
         INNER JOIN data_anak da ON da.id_anak = pm.id_anak
         WHERE pm.id_user = ?
         ORDER BY pm.id_plan DESC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, 'i', $idUser);
}

mysqli_stmt_execute($stmt);

$plan = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
) ?: null;

mysqli_stmt_close($stmt);


/* =========================================================
   AMBIL MENU PLANNING
   ========================================================= */

$jadwal = [];

if ($plan) {
    $idPlan = (int) $plan['id_plan'];

    $_SESSION['kategori_protein_planning'] =
        $plan['kategori_protein'] ?? '';

    $_SESSION['tanggal_mulai_planning'] =
        $plan['tanggal_mulai'] ?? '';

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            pd.hari_ke,
            pd.nama_hari,
            pd.tanggal,
            pd.waktu_makan AS slot_planning,
            m.*
         FROM planning_detail pd
         INNER JOIN menu m ON m.id_menu = pd.id_menu
         WHERE pd.id_plan = ?
           AND pd.hari_ke BETWEEN 1 AND 7
           AND pd.waktu_makan IN ('pagi', 'siang', 'malam')
         ORDER BY
            pd.hari_ke,
            FIELD(pd.waktu_makan, 'pagi', 'siang', 'malam')"
    );

    mysqli_stmt_bind_param($stmt, 'i', $idPlan);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $hari = (int) $row['hari_ke'];
        $slot = $row['slot_planning'];

        $jadwal[$hari] ??= [
            'tanggal' => $row['tanggal'],
            'pagi' => null,
            'siang' => null,
            'malam' => null
        ];

        $jadwal[$hari][$slot] = $row;
    }

    mysqli_stmt_close($stmt);
}


/* =========================================================
   DATA TAMPILAN
   ========================================================= */

$hariList = [
    1 => 'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu',
    'Minggu'
];

$slotList = [
    'pagi' => [
        'label' => 'Sarapan',
        'icon' => '☀'
    ],
    'siang' => [
        'label' => 'Makan Siang / Bekal Sekolah',
        'icon' => '▣'
    ],
    'malam' => [
        'label' => 'Makan Malam',
        'icon' => '☾'
    ]
];

$nutrisi = [
    'energi' => ['Energi', 'kkal'],
    'protein' => ['Protein', 'g'],
    'karbohidrat' => ['Karbohidrat', 'g'],
    'lemak' => ['Lemak', 'g'],
    'serat' => ['Serat', 'g']
];


/* =========================================================
   HELPER
   ========================================================= */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value);
}


function caraAmbilTeks(
    array $menu,
    array $keys,
    string $default
): string {
    foreach ($keys as $key) {
        $value = trim((string) ($menu[$key] ?? ''));

        if ($value !== '') {
            return $value;
        }
    }

    return $default;
}


function caraGambarMenu(array $menu): string
{
    $gambar = caraAmbilTeks(
        $menu,
        ['gambar', 'foto', 'image'],
        ''
    );

    if ($gambar === '') {
        return '';
    }

    if (preg_match('~^(https?://|/)~i', $gambar)) {
        return $gambar;
    }

    return '/uploads/menu/' . ltrim($gambar, '/');
}


function caraFormatTanggal(?string $tanggal): string
{
    if (!$tanggal) {
        return '-';
    }

    $bulan = [
        1 => 'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agu',
        'Sep',
        'Okt',
        'Nov',
        'Des'
    ];

    $date = new DateTime($tanggal);

    return $date->format('d')
        . ' '
        . $bulan[(int) $date->format('n')]
        . ' '
        . $date->format('Y');
}


function caraNilaiMenu(
    array $menu,
    string $key,
    string $satuan = ''
): string {
    $nilai = $menu[$key] ?? null;

    if ($nilai === null || $nilai === '') {
        return '-';
    }

    $angka = (float) $nilai;

    $hasil = floor($angka) == $angka
        ? number_format($angka, 0, ',', '.')
        : number_format($angka, 1, ',', '.');

    return trim($hasil . ' ' . $satuan);
}


/*
 * Mengubah teks cara memasak
 * menjadi langkah 1, 2, 3, dan seterusnya.
 */
function caraLangkahMasak(array $menu): array
{
    $teks = caraAmbilTeks(
        $menu,
        [
            'cara_masak',
            'cara_mengolah',
            'cara_pengolahan',
            'langkah_pengolahan',
            'resep'
        ],
        ''
    );

    if ($teks === '') {
        return [];
    }

    /*
     * Jika data disimpan per baris,
     * setiap baris dianggap satu langkah.
     */
    $langkah = preg_split(
        '/\r\n|\r|\n/',
        $teks
    );

    /*
     * Data lama masih berupa satu paragraf.
     * Pisahkan berdasarkan akhir kalimat.
     */
    if (count($langkah) === 1) {
        $langkah = preg_split(
            '/(?<=[.!?])\s+(?=[A-Z0-9])/u',
            $teks
        );
    }

    return array_values(
        array_filter(
            array_map('trim', $langkah)
        )
    );
}


/* =========================================================
   DATA ANAK
   ========================================================= */

$dataAnak = $plan ? [
    'Nama Anak' =>
        $plan['nama_anak'] ?? '-',

    'Usia' =>
        (int) ($plan['usia'] ?? 0) . ' tahun',

    'Jenis Kelamin' =>
        $plan['jenis_kelamin'] ?? '-',

    'Berat Badan' =>
        ($plan['berat_badan'] ?? '-') . ' kg',

    'Tinggi Badan' =>
        ($plan['tinggi_badan'] ?? '-') . ' cm'
] : [];

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Cara Mengolah Menu</title>

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

        <div class="cara-page">

            <?php if (!$plan): ?>

                <div class="planning-empty">

                    <h3>Belum ada planning menu</h3>

                    <p>
                        Pilih menu mingguan terlebih dahulu
                        agar cara mengolah dapat ditampilkan.
                    </p>

                    <a href="/index.php?page=planning_mingguan">
                        Buat Planning Menu
                    </a>

                </div>

            <?php else: ?>

                <!-- HEADER -->
                <div class="cara-header">

                    <div>
                        <h1>Cara Mengolah Menu</h1>

                        <p>
                            Lihat kandungan gizi, bahan,
                            dan langkah pengolahan dari menu
                            yang sudah dipilih.
                        </p>
                    </div>

                    <div class="cara-header-actions">

                        <a
                            href="/index.php?page=planning_mingguan"
                            class="cara-btn cara-btn-secondary"
                        >
                            Kembali
                        </a>

                        <a
                            href="/index.php?page=download_jadwal_pdf&id=<?= $idPlan; ?>"
                            class="cara-btn cara-btn-primary"
                        >
                            Download PDF
                        </a>

                    </div>

                </div>


                <!-- DATA ANAK -->
                <section class="cara-child-card">

                    <div class="cara-child-head">

                        <h2>Data Anak</h2>

                        <span>
                            Sumber Protein:
                            <strong>
                                <?= e(
                                    ucfirst(
                                        $plan['kategori_protein']
                                        ?? '-'
                                    )
                                ); ?>
                            </strong>
                        </span>

                    </div>

                    <div class="cara-child-grid">

                        <?php foreach ($dataAnak as $label => $nilai): ?>

                            <div>
                                <span>
                                    <?= e($label); ?>
                                </span>

                                <strong>
                                    <?= e($nilai); ?>
                                </strong>
                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>


                <!-- PERIODE -->
                <div class="cara-period">

                    <strong>
                        Planning 7 hari:
                    </strong>

                    <?= e(
                        caraFormatTanggal(
                            $plan['tanggal_mulai'] ?? null
                        )
                    ); ?>

                    –

                    <?= e(
                        caraFormatTanggal(
                            $plan['tanggal_selesai'] ?? null
                        )
                    ); ?>

                </div>


                <!-- DAFTAR HARI -->
                <div class="cara-days">

                    <?php foreach ($hariList as $hariKe => $hari): ?>

                        <?php
                        $dataHari = $jadwal[$hariKe] ?? [
                            'tanggal' => null,
                            'pagi' => null,
                            'siang' => null,
                            'malam' => null
                        ];
                        ?>

                        <section
                            class="cara-day"
                            id="hari-<?= $hariKe; ?>"
                        >

                            <!-- HEADER HARI -->
                            <div class="cara-day-head">

                                <div>
                                    <span>Hari</span>

                                    <h2>
                                        <?= e($hari); ?>
                                    </h2>
                                </div>

                                <time>
                                    <?= e(
                                        caraFormatTanggal(
                                            $dataHari['tanggal']
                                        )
                                    ); ?>
                                </time>

                            </div>


                            <!-- MENU -->
                            <div class="cara-menu-list">

                                <?php foreach ($slotList as $slot => $meta): ?>

                                    <?php
                                    $menu = $dataHari[$slot] ?? null;
                                    $idAccordion =
                                        "menu-$hariKe-$slot";
                                    ?>

                                    <?php if (!$menu): ?>

                                        <div class="cara-empty-slot">

                                            <?= e($meta['label']); ?>
                                            belum memiliki menu
                                            yang dipilih.

                                        </div>

                                        <?php continue; ?>

                                    <?php endif; ?>


                                    <?php

                                    $namaMenu =
                                        $menu['nama_menu']
                                        ?? 'Menu';

                                    $gambar =
                                        caraGambarMenu($menu);

                                    $bahan =
                                        caraAmbilTeks(
                                            $menu,
                                            [
                                                'bahan_belanjaan',
                                                'bahan',
                                                'bahan_makanan'
                                            ],
                                            'Bahan untuk menu ini belum tersedia.'
                                        );

                                    $langkahMasak =
                                        caraLangkahMasak($menu);

                                    ?>


                                    <article
                                        class="cara-menu"
                                        id="<?= e($idAccordion); ?>"
                                    >

                                        <!-- MENU RINGKAS -->
                                        <button
                                            type="button"
                                            class="cara-menu-trigger"
                                            aria-expanded="false"
                                        >

                                            <span class="cara-slot-icon">
                                                <?= $meta['icon']; ?>
                                            </span>

                                            <span class="cara-trigger-main">

                                                <small>
                                                    <?= e($meta['label']); ?>
                                                </small>

                                                <strong>
                                                    <?= e($namaMenu); ?>
                                                </strong>

                                                <span>
                                                    <?= e(
                                                        caraNilaiMenu(
                                                            $menu,
                                                            'energi',
                                                            'kkal'
                                                        )
                                                    ); ?>

                                                    · Protein

                                                    <?= e(
                                                        caraNilaiMenu(
                                                            $menu,
                                                            'protein',
                                                            'g'
                                                        )
                                                    ); ?>
                                                </span>

                                            </span>

                                            <span class="cara-trigger-action">

                                                Lihat detail

                                                <span>
                                                    ⌄
                                                </span>

                                            </span>

                                        </button>


                                        <!-- DETAIL MENU -->
                                        <div
                                            class="cara-menu-detail"
                                            hidden
                                        >

                                            <div class="cara-detail-grid">

                                                <!-- GAMBAR -->
                                                <div class="cara-menu-image">

                                                    <?php if ($gambar): ?>

                                                        <img
                                                            src="<?= e($gambar); ?>"
                                                            alt="<?= e($namaMenu); ?>"
                                                            loading="lazy"
                                                        >

                                                    <?php else: ?>

                                                        <div class="cara-image-placeholder">
                                                            🍽
                                                        </div>

                                                    <?php endif; ?>

                                                </div>


                                                <!-- INFORMASI MENU -->
                                                <div class="cara-detail-content">

                                                    <!-- JUDUL -->
                                                    <div class="cara-detail-title">
                                                        <div>
                                                            <span class="cara-badge">
                                                                <?= e(
                                                                    $meta['label']
                                                                ); ?>
                                                            </span>

                                                            <h3>
                                                                <?= e($namaMenu); ?>
                                                            </h3>
                                                        </div>
                                                    </div>


                                                    <!-- KANDUNGAN GIZI -->
                                                    <section class="cara-detail-section">
                                                        <h4>
                                                            Kandungan Gizi
                                                        </h4>
                                                        <div class="cara-nutrition-grid">

                                                            <?php foreach (
                                                                $nutrisi
                                                                as $key => [$label, $satuan]
                                                            ): ?>
                                                                <div>
                                                                    <span>
                                                                        <?= e($label); ?>
                                                                    </span>

                                                                    <strong>
                                                                        <?= e(
                                                                            caraNilaiMenu(
                                                                                $menu,
                                                                                $key,
                                                                                $satuan
                                                                            )
                                                                        ); ?>
                                                                    </strong>

                                                                </div>

                                                            <?php endforeach; ?>

                                                        </div>

                                                    </section>

                                                    <!-- BAHAN -->
                                                    <section class="cara-detail-section">

                                                        <h4>
                                                            Bahan
                                                        </h4>

                                                        <div class="cara-text-block">
                                                            <?= nl2br(
                                                                e($bahan)
                                                            ); ?>
                                                        </div>

                                                    </section>


                                                    <!-- CARA MENGOLAH -->
                                                    <section class="cara-detail-section">

                                                        <h4>
                                                            Cara Mengolah
                                                        </h4>

                                                        <div class="cara-text-block">

                                                            <?php if ($langkahMasak): ?>

                                                                <?php foreach (
                                                                    $langkahMasak
                                                                    as $i => $langkah
                                                                ): ?>

                                                                    <div>
                                                                        <strong>
                                                                            <?= $i + 1; ?>.
                                                                        </strong>

                                                                        <?= e($langkah); ?>
                                                                    </div>

                                                                <?php endforeach; ?>

                                                            <?php else: ?>

                                                                Cara mengolah untuk menu ini
                                                                belum tersedia.

                                                            <?php endif; ?>

                                                        </div>

                                                    </section>

                                                </div>

                                            </div>

                                        </div>

                                    </article>

                                <?php endforeach; ?>

                            </div>

                        </section>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>


<script src="/assets/js/script.js"></script>
<script src="/assets/js/cara_mengolah.js"></script>

</body>

</html>