<?php

require_once __DIR__ . '/akg.php';
require_once __DIR__ . '/gizi_menu.php';

/* Proporsi kebutuhan harian untuk tiap waktu makan utama */

const PROPORSI_WAKTU_MAKAN = [
    'pagi'  => 0.25,
    'siang' => 0.30,
    'malam' => 0.25
];

/* Jumlah menu teratas yang ditampilkan untuk tiap jenis lauk */

const MENU_PER_JENIS_LAUK = 3;

/* Mengubah teks alergi menjadi token yang konsisten */

function tokenAlergi(string $teks): array
{
    $tokens = array_map(
        static fn($nilai) => strtolower(trim($nilai)),
        explode(',', $teks)
    );

    return array_values(array_unique(array_filter(
        $tokens,
        static fn($nilai) => $nilai !== ''
    )));
}

/* Mengecek keamanan menu berdasarkan alergi anak */

function menuAmanUntukAnak(array $menu, array $anak): bool
{
    $alergiAnak = tokenAlergi(implode(',', [
        $anak['alergi'] ?? '',
        $anak['catatan_alergi'] ?? '',
        $anak['alergi_lainnya'] ?? ''
    ]));

    $alergiMenu = tokenAlergi(
        (string) ($menu['alergi_kandungan'] ?? '')
    );

    return !array_intersect($alergiAnak, $alergiMenu);
}

/**
 * Target energi dan protein satu waktu makan:
 * kebutuhan harian anak × proporsi (pagi 25%, siang 30%, malam 25%).
 *
 * Kebutuhan harian dihitung saat data anak disimpan
 * (lihat helpers/kebutuhan.php).
 */
function targetMenuPlanning(array $anak, string $slot): array
{
    if (!isset(PROPORSI_WAKTU_MAKAN[$slot])) {
        throw new InvalidArgumentException('Waktu makan tidak valid.');
    }

    $energiHarian = (float) ($anak['kebutuhan_energi'] ?? 0);
    $proteinHarian = (float) ($anak['kebutuhan_protein'] ?? 0);

    // Tanpa berat badan ideal berarti kebutuhan belum dihitung
    // dengan metode yang berlaku (data anak versi lama).
    $beratBadanIdeal = (float) ($anak['berat_badan_ideal'] ?? 0);

    if ($energiHarian <= 0 || $proteinHarian <= 0 || $beratBadanIdeal <= 0) {
        throw new InvalidArgumentException(
            'Kebutuhan gizi anak belum dihitung. '
            . 'Simpan ulang data anak terlebih dahulu.'
        );
    }

    $proporsi = PROPORSI_WAKTU_MAKAN[$slot];

    return [
        'target_energi' => round($energiHarian * $proporsi, 2),
        'target_protein' => round($proteinHarian * $proporsi, 2)
    ];
}

/**
 * Rule-Based Filtering:
 * R1 usia, R2 protein, R3 waktu makan, R4 alergi.
 *
 * Keempat rule ini hard rule: menu baru boleh dihitung skornya
 * kalau lolos R1 sampai R4. Rule dicek berurutan, dan begitu satu
 * rule gagal menu langsung ditolak (return false) tanpa mengecek
 * rule berikutnya.
 *
 * $kategoriProtein: sumber protein pilihan orang tua di halaman
 * planning (hewani/nabati).
 * $slot: waktu makan yang sedang diisi (pagi/siang/malam).
 */
function menuLolosHardRule(
    array $menu,
    array $anak,
    string $kategoriProtein,
    string $slot
): bool {
    // Pilihan protein atau waktu makan di luar daftar dianggap
    // tidak valid, jadi tidak ada menu yang lolos.
    if (
        !in_array($kategoriProtein, ['hewani', 'nabati'], true) ||
        !in_array($slot, ['pagi', 'siang', 'malam'], true)
    ) {
        return false;
    }

    // R1 — Kelompok usia
    // IF kelompok usia menu sama dengan kelompok usia anak
    //    ATAU menu berlaku untuk 'semua'
    // THEN lanjut ke R2, ELSE menu ditolak.
    // Kelompok anak: 6-9 atau 10-12 tahun (kelompokUsia() di
    // helpers/akg.php). Anak di luar 6-12 tahun tidak punya
    // kelompok, sehingga semua menu ditolak.
    $kelompokAnak = kelompokUsia((int) ($anak['usia'] ?? 0));
    $kelompokMenu = strtolower(trim(
        (string) ($menu['kelompok_usia'] ?? '')
    ));

    if (
        !$kelompokAnak ||
        !in_array($kelompokMenu, [$kelompokAnak, 'semua'], true)
    ) {
        return false;
    }

    // R2 — Sumber protein
    // IF kategori protein menu (hewani/nabati) sama dengan
    //    pilihan orang tua di halaman planning
    // THEN lanjut ke R3, ELSE menu ditolak.
    $proteinMenu = strtolower(trim(
        (string) ($menu['kategori_protein'] ?? '')
    ));

    if ($proteinMenu !== $kategoriProtein) {
        return false;
    }

    // R3 — Waktu makan
    // IF waktu makan yang sedang diisi ada di kolom waktu_makan
    //    menu (contoh isi kolom: "pagi,siang,malam")
    // THEN lanjut ke R4, ELSE menu ditolak.
    $waktuMakan = array_map(
        static fn($nilai) => strtolower(trim($nilai)),
        explode(',', (string) ($menu['waktu_makan'] ?? ''))
    );

    if (!in_array($slot, $waktuMakan, true)) {
        return false;
    }

    // R4 — Alergi
    // IF tidak ada alergi anak yang sama dengan kandungan alergen
    //    menu (kolom alergi_kandungan)
    // THEN menu lolos semua hard rule, ELSE menu ditolak.
    // Alergi dicocokkan per kata yang dipisah koma, lihat
    // menuAmanUntukAnak().
    return menuAmanUntukAnak($menu, $anak);
}

/**
 * Urutan ranking: skor terkecil lebih dulu,
 * skor sama diurutkan menurut nama lalu id menu.
 */
function bandingkanSkorMenu(array $a, array $b): int
{
    return [$a['skor'], $a['nama_menu'] ?? '', $a['id_menu'] ?? 0]
        <=> [$b['skor'], $b['nama_menu'] ?? '', $b['id_menu'] ?? 0];
}

/**
 * Menyaring menu dengan hard rule, menakar nasi untuk tiap menu,
 * lalu mengurutkannya dari yang paling sesuai kebutuhan anak.
 * Tiap jenis lauk hanya menampilkan beberapa menu teratas.
 *
 * $nasi: kandungan gizi nasi per 100 gram (TKPI).
 */
function kandidatMenuPlanning(
    array $menus,
    array $anak,
    string $kategoriProtein,
    string $slot,
    array $nasi
): array {
    if (!in_array($kategoriProtein, ['hewani', 'nabati'], true)) {
        throw new InvalidArgumentException('Sumber protein tidak valid.');
    }

    $target = targetMenuPlanning($anak, $slot);
    $kandidat = [];

    foreach ($menus as $menu) {
        if (!menuLolosHardRule(
            $menu,
            $anak,
            $kategoriProtein,
            $slot
        )) {
            continue;
        }

        $kandidat[] = array_merge(
            $menu,
            $target,
            susunPorsiMenu($menu, $target, $nasi)
        );
    }

    usort($kandidat, 'bandingkanSkorMenu');

    $jumlahPerJenis = [];
    $teratas = [];

    foreach ($kandidat as $menu) {
        $jenis = (string) ($menu['jenis_lauk'] ?? '');
        $jumlahPerJenis[$jenis] = ($jumlahPerJenis[$jenis] ?? 0) + 1;

        if ($jumlahPerJenis[$jenis] <= MENU_PER_JENIS_LAUK) {
            $teratas[] = $menu;
        }
    }

    return $teratas;
}

/**
 * Mengambil menu dari database lalu menjalankan penyaringan dan ranking.
 */
function ambilMenuPlanning(
    mysqli $conn,
    string $kategoriProtein,
    array $anak,
    string $slot
): array {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT * FROM menu ORDER BY nama_menu ASC, id_menu ASC'
    );

    if (!$stmt) {
        throw new RuntimeException('Data menu gagal disiapkan.');
    }

    mysqli_stmt_execute($stmt);

    $menus = mysqli_fetch_all(
        mysqli_stmt_get_result($stmt),
        MYSQLI_ASSOC
    );

    mysqli_stmt_close($stmt);

    return kandidatMenuPlanning(
        $menus,
        $anak,
        $kategoriProtein,
        $slot,
        ambilBahanNasi($conn)
    );
}
