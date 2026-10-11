            <?php

            /** @var mysqli $conn */

            require_once ROOT . '/classes/RencanaMingguanRepository.php';
            require_once ROOT . '/helpers/rekomendasi.php';

            $repository = new RencanaMingguanRepository($conn);
            $id_user = (int) ($_SESSION['id_user'] ?? 0);

            if ($id_user <= 0) {
                header('Location: /index.php?page=login');
                exit();
            }

            // Ambil data anak terakhir milik user.
            $stmtA = mysqli_prepare(
                $conn,
                "SELECT * FROM data_anak
                WHERE id_user = ?
                ORDER BY id_anak DESC
                LIMIT 1"
            );

            mysqli_stmt_bind_param($stmtA, 'i', $id_user);
            mysqli_stmt_execute($stmtA);
            $anak = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtA));
            mysqli_stmt_close($stmtA);

            $hari_list = $repository->getHariList();

            $slotMeta = [
                'pagi' => [
                    'label' => 'Sarapan',
                    'icon' => '☀',
                    'subtitle' => 'Menu sarapan sebelum beraktivitas',
                ],
                'siang' => [
                    'label' => 'Makan Siang',
                    'icon' => '▣',
                    'subtitle' => 'Menu makan siang, dapat dijadikan bekal pada hari sekolah',
                ],
                'malam' => [
                    'label' => 'Makan Malam',
                    'icon' => '☾',
                    'subtitle' => 'Menu makan malam di rumah',
                ],
            ];

            $slot_list = [];
            foreach ($repository->getSlotList() as $slot) {
                $slot_list[$slot] = $slotMeta[$slot]['label'] ?? ucfirst($slot);
            }

            $error = '';

            function tanggalSeninDepan(): string
            {
                $tanggal = new DateTime();

                if ((int) $tanggal->format('N') !== 1) {
                    $tanggal->modify('next monday');
                }

                return $tanggal->format('Y-m-d');
            }

            function nilaiMenu(array $menu, string $key, string $satuan = ''): string
            {
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

            function gambarMenu(array $menu): string
            {
                $gambar = trim((string) (
                    $menu['gambar']
                    ?? $menu['foto']
                    ?? $menu['image']
                    ?? ''
                ));

                if ($gambar === '') {
                    return '';
                }

                if (preg_match('~^(https?://|/)~i', $gambar)) {
                    return $gambar;
                }

                return '/uploads/menu/' . ltrim($gambar, '/');
            }

            function dataAnakTampil(array $anak, array $keys, string $default = '-'): string
            {
                foreach ($keys as $key) {
                    if (isset($anak[$key]) && $anak[$key] !== '') {
                        return (string) $anak[$key];
                    }
                }

                return $default;
            }

            function esc($value): string
            {
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            }

            // Pulihkan pilihan planning terakhir bila session hilang.
            $plan_terakhir = $anak
                ? $repository->cariPlanTerakhir($id_user, (int) $anak['id_anak'])
                : null;

            $kategori_protein = strtolower(trim((string) (
                $_POST['kategori_protein']
                ?? $_SESSION['kategori_protein_planning']
                ?? $plan_terakhir['kategori_protein']
                ?? ''
            )));

            $tanggal_mulai = $_POST['tanggal_mulai']
                ?? $_SESSION['tanggal_mulai_planning']
                ?? $plan_terakhir['tanggal_mulai']
                ?? tanggalSeninDepan();

            if (!in_array($kategori_protein, ['nabati', 'hewani'], true)) {
                $kategori_protein = '';
            }

            if ($kategori_protein) {
                $_SESSION['kategori_protein_planning'] = $kategori_protein;
                $_SESSION['tanggal_mulai_planning'] = $tanggal_mulai;
            }

            // Ambil planning dan pilihan yang sudah tersimpan.
            $id_plan_aktif = 0;
            $pilihan_tersimpan = [];

            if ($anak && $kategori_protein) {
                $id_plan_aktif = $repository->cariPlan(
                    $id_user,
                    (int) $anak['id_anak'],
                    $tanggal_mulai,
                    $kategori_protein
                );

                if ($id_plan_aktif > 0) {
                    $pilihan_tersimpan = $repository->ambilPilihan($id_plan_aktif);
                }
            }

            // Endpoint autosave pilihan menu.
            if (
                $_SERVER['REQUEST_METHOD'] === 'POST'
                && ($_POST['aksi'] ?? '') === 'autosave'
            ) {
                header('Content-Type: application/json; charset=utf-8');

                try {
                    if (!$anak) {
                        throw new Exception('Data anak belum diisi.');
                    }

                    if (!$kategori_protein) {
                        throw new Exception('Sumber protein belum dipilih.');
                    }

                    $repository->validasiTanggalMulai($tanggal_mulai);

                    $hari_ke = (int) ($_POST['hari_ke'] ?? 0);
                    $slot = (string) ($_POST['slot'] ?? '');
                    $id_menu = (int) ($_POST['id_menu'] ?? 0);

                    if ($hari_ke < 1 || $hari_ke > 7) {
                        throw new Exception('Hari planning tidak valid.');
                    }

                    if (!isset($slot_list[$slot])) {
                        throw new Exception('Waktu makan tidak valid.');
                    }

                    if ($id_menu <= 0) {
                        throw new Exception('Menu tidak valid.');
                    }

                    // Menu hanya boleh disimpan jika masih lolos rule rekomendasi.
                    $kandidat = ambilMenuPlanning($conn, $kategori_protein, $anak, $slot);
                    $idKandidat = array_map('intval', array_column($kandidat, 'id_menu'));

                    if (!in_array($id_menu, $idKandidat, true)) {
                        throw new Exception('Menu tidak sesuai dengan filter anak.');
                    }

                    $id_plan = $repository->simpanPilihanAnak(
                        $id_user,
                        (int) $anak['id_anak'],
                        $tanggal_mulai,
                        $kategori_protein,
                        $hari_ke,
                        $slot,
                        $id_menu
                    );

                    echo json_encode([
                        'ok' => true,
                        'message' => 'Pilihan tersimpan otomatis.',
                        'id_plan' => $id_plan,
                    ]);
                    exit();
                } catch (Throwable $e) {
                    http_response_code(422);
                    echo json_encode([
                        'ok' => false,
                        'message' => $e->getMessage(),
                    ]);
                    exit();
                }
            }

            // Ambil kandidat menu untuk tiap waktu makan.
            $menus = array_fill_keys(array_keys($slot_list), []);

            if ($anak && $kategori_protein) {
                try {
                    foreach (array_keys($slot_list) as $slot) {
                        $menus[$slot] = ambilMenuPlanning(
                            $conn,
                            $kategori_protein,
                            $anak,
                            $slot
                        );

                        // Pilihan lama yang sudah tidak lolos rule harus dipilih ulang.
                        $idValid = array_map('intval', array_column($menus[$slot], 'id_menu'));

                        foreach ($pilihan_tersimpan as &$pilihanHari) {
                            if (
                                isset($pilihanHari[$slot])
                                && !in_array($pilihanHari[$slot], $idValid, true)
                            ) {
                                unset($pilihanHari[$slot]);
                            }
                        }
                        unset($pilihanHari);
                    }
                } catch (InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }

            $dataAnak = $anak ? [
                ['Nama Anak', dataAnakTampil($anak, ['nama_anak', 'nama'])],
                ['Usia', (int) ($anak['usia'] ?? 0) . ' tahun'],
                ['Jenis Kelamin', dataAnakTampil($anak, ['jenis_kelamin', 'jk'])],
                ['Berat Badan', dataAnakTampil($anak, ['berat_badan', 'bb']) . ' kg'],
                ['Tinggi Badan', dataAnakTampil($anak, ['tinggi_badan', 'tb']) . ' cm'],
            ] : [];

            $nutrisiTampil = [
                'energi' => ['Kalori', 'kkal'],
                'protein' => ['Protein', 'g'],
                'karbohidrat' => ['Karbohidrat', 'g'],
                'lemak' => ['Lemak', 'g'],
                'serat' => ['Serat', 'g'],
            ];
            ?>

            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Planning Menu 1 Minggu</title>
                <link rel="stylesheet" href="/assets/css/style.css">
                <link rel="stylesheet" href="/assets/css/dashboard.css">
            </head>
            <body>

<?php include ROOT . '/includes/navbar.php'; ?>

<div class="dashboard-wrap">
    <?php include ROOT . '/includes/sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="planning-page">
            <div class="planning-header">
                <div>
                    <h1 class="planning-title">Pilih Menu Mingguan</h1>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="planning-alert planning-alert-error">
                    <strong>Planning belum berhasil disimpan.</strong><br>
                    <?= esc($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$anak): ?>
                <div class="planning-empty">
                    <h3>Data anak belum diisi</h3>
                    <p>Isi data anak terlebih dahulu sebelum membuat planning menu mingguan.</p>
                    <a href="/index.php?page=data_anak">Isi Data Anak</a>
                </div>

            <?php elseif (!$kategori_protein): ?>
                <div class="planning-empty">
                    <h3>Sumber protein belum dipilih</h3>
                    <p>Pilih sumber protein terlebih dahulu agar sistem dapat menampilkan menu yang sesuai.</p>
                    <a href="/index.php?page=sumber_protein">Pilih Sumber Protein</a>
                </div>

            <?php else: ?>
                <form
                    method="POST"
                    id="planningForm"
                    data-plan-id="<?= (int) $id_plan_aktif; ?>"
                >
                    <input type="hidden" name="kategori_protein" value="<?= esc($kategori_protein); ?>">
                    <input type="hidden" name="tanggal_mulai" value="<?= esc($tanggal_mulai); ?>">

                    <section class="child-card">
                        <div class="child-card-header">
                            <h2 class="child-card-title">
                                <span class="child-card-icon">♙</span>
                                Data Anak
                            </h2>

                            <span class="child-budget">
                                Sumber Protein:
                                <strong>
                                    <?= $kategori_protein === 'nabati' ? 'Protein Nabati' : 'Protein Hewani'; ?>
                                </strong>
                            </span>
                        </div>

                        <div class="child-data-grid">
                            <?php foreach ($dataAnak as [$label, $value]): ?>
                                <div class="child-data-item">
                                    <span class="child-data-label"><?= esc($label); ?></span>
                                    <span class="child-data-value"><?= esc($value); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <div class="planning-settings">
                        <div class="planning-settings-text">
                            <strong>Planning 7 hari:</strong>
                            Senin sampai Minggu dengan 3 kali makan per hari:
                            sarapan, makan siang, dan makan malam.
                        </div>
                    </div>

                    <div class="planning-day-tabs" role="tablist" aria-label="Pilih hari">
                        <?php foreach ($hari_list as $index => $hari): ?>
                            <button
                                type="button"
                                class="planning-day-tab <?= $index === 0 ? 'active' : ''; ?>"
                                data-day="<?= $index + 1; ?>"
                                role="tab"
                                aria-selected="<?= $index === 0 ? 'true' : 'false'; ?>"
                            >
                                <?= esc($hari); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ($hari_list as $index => $hari): ?>
                        <?php $hari_ke = $index + 1; ?>

                        <section
                            class="planning-day-content <?= $index === 0 ? 'active' : ''; ?>"
                            data-day-content="<?= $hari_ke; ?>"
                        >
                            <div class="planning-content-card">
                                <div class="planning-day-title-row">
                                    <h3 class="planning-day-title">Menu Hari <?= esc($hari); ?></h3>
                                    <span class="planning-day-note">Pilih 1 menu pada setiap waktu makan</span>
                                </div>

                                <?php foreach ($slot_list as $slot => $label): ?>
                                    <?php
                                    $nilai_dipilih = $_POST['menu'][$hari_ke][$slot]
                                        ?? $pilihan_tersimpan[$hari_ke][$slot]
                                        ?? '';
                                    $meta = $slotMeta[$slot] ?? [
                                        'icon' => '•',
                                        'subtitle' => '',
                                    ];
                                    ?>

                                    <div class="meal-section">
                                        <div class="meal-section-header">
                                            <span class="meal-section-icon"><?= esc($meta['icon']); ?></span>
                                            <h4 class="meal-section-title"><?= esc($label); ?></h4>
                                            <span class="meal-section-subtitle"><?= esc($meta['subtitle']); ?></span>
                                        </div>

                                        <div class="menu-grid">
                                            <?php if (empty($menus[$slot] ?? [])): ?>
                                                <div class="menu-empty">
                                                    Belum ada menu <?= esc(strtolower($label)); ?> untuk sumber protein ini.
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($menus[$slot] as $menu): ?>
                                                    <?php
                                                    $id_menu = (int) $menu['id_menu'];
                                                    $is_checked = (string) $nilai_dipilih === (string) $id_menu;
                                                    $nama_menu = $menu['nama_menu'] ?? 'Menu';
                                                    $gambar = gambarMenu($menu);
                                                    $harga = $menu['harga'] ?? null;
                                                    ?>

                                                    <label class="menu-card <?= $is_checked ? 'selected' : ''; ?>">
                                                        <input
                                                            type="radio"
                                                            name="menu[<?= $hari_ke; ?>][<?= esc($slot); ?>]"
                                                            value="<?= $id_menu; ?>"
                                                            data-day="<?= $hari_ke; ?>"
                                                            data-slot="<?= esc($slot); ?>"
                                                            <?= $is_checked ? 'checked' : ''; ?>
                                                        >

                                                        <div class="menu-image-wrap">
                                                            <?php if ($gambar): ?>
                                                                <img
                                                                    src="<?= esc($gambar); ?>"
                                                                    alt="<?= esc($nama_menu); ?>"
                                                                    class="menu-image"
                                                                    loading="lazy"
                                                                >
                                                            <?php else: ?>
                                                                <div class="menu-image-placeholder">🍽</div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <div class="menu-card-body">
                                                            <h5 class="menu-name"><?= esc($nama_menu); ?></h5>

                                                            <div class="menu-nutrition">
                                                                <?php foreach ($nutrisiTampil as $key => [$labelNutrisi, $satuan]): ?>
                                                                    <div class="menu-nutrition-row">
                                                                        <span><?= esc($labelNutrisi); ?></span>
                                                                        <strong><?= esc(nilaiMenu($menu, $key, $satuan)); ?></strong>
                                                                    </div>
                                                                <?php endforeach; ?>

                                                                <?php if ($harga !== null && $harga !== ''): ?>
                                                                    <div class="menu-price">
                                                                        Perkiraan harga:
                                                                        <strong>
                                                                            Rp <?= number_format((float) $harga, 0, ',', '.'); ?>
                                                                        </strong>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>

                                                            <span class="menu-select-button">
                                                                <?= $is_checked ? '✓ Dipilih' : 'Pilih Menu'; ?>
                                                            </span>
                                                        </div>
                                                    </label>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>

                    <div class="planning-form-footer">
                        <div class="planning-form-footer-text">
                            Setiap menu yang dipilih akan langsung tersimpan otomatis.
                            Pilihan tetap tersimpan meskipun halaman ditutup atau dibuka kembali.
                            Setelah semua menu Senin sampai Minggu dipilih, lanjutkan ke halaman Cara Mengolah.
                        </div>

                        <span
                            id="autosaveStatus"
                            style="margin-left:auto; margin-right:12px; font-size:14px; color:#667085;"
                        >
                            <?= $id_plan_aktif > 0 ? '✓ Planning tersimpan' : ''; ?>
                        </span>

                        <button
                            type="button"
                            id="lihatCaraMengolah"
                            class="planning-submit"
                            <?= $id_plan_aktif <= 0 ? 'disabled' : ''; ?>
                        >
                            Lihat Cara Mengolah
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="/assets/js/script.js"></script>
<script src="/assets/js/planning_mingguan.js"></script>

</body>
</html>
