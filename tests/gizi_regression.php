<?php

/**
 * Uji perhitungan gizi: kebutuhan anak, gizi menu, takaran nasi, dan ranking.
 * Jalankan: php tests/gizi_regression.php
 *
 * Nilai harapan dihitung manual dari PMK 28/2019 (AKG), WHO Reference 2007,
 * dan TKPI 2017, bukan dari fungsi yang diuji.
 */

foreach (['akg', 'antropometri', 'kebutuhan', 'gizi_menu', 'rekomendasi'] as $helper) {
    $file = __DIR__ . '/../helpers/' . $helper . '.php';
    if (file_exists($file)) require_once $file;
}

$gagal = 0;

function uji(string $nama, callable $fn): void
{
    global $gagal;
    try {
        $fn();
        echo "PASS: $nama\n";
    } catch (Throwable $e) {
        $gagal++;
        echo "FAIL: $nama\n      " . $e->getMessage() . "\n";
    }
}

function cocok($hasil, $harapan): bool
{
    if (is_array($hasil) && is_array($harapan)) {
        if (array_keys($hasil) !== array_keys($harapan)) return false;
        foreach ($harapan as $kunci => $nilai) {
            if (!cocok($hasil[$kunci], $nilai)) return false;
        }
        return true;
    }

    if ((is_float($hasil) || is_float($harapan)) && is_numeric($hasil) && is_numeric($harapan)) {
        return abs((float) $hasil - (float) $harapan) < 0.00001;
    }

    return $hasil === $harapan;
}

function sama($hasil, $harapan, string $label = ''): void
{
    if (!cocok($hasil, $harapan)) {
        throw new RuntimeException(trim(
            $label . ' harapan ' . json_encode($harapan) . ', hasil ' . json_encode($hasil)
        ));
    }
}

function menolak(callable $fn): void
{
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return;
    }
    throw new RuntimeException('seharusnya ditolak');
}

/* ---------- AKG per kelompok umur (PMK 28/2019 Lampiran I Tabel 1) ---------- */

uji('AKG memuat BB rujukan, energi, dan protein tiap kelompok umur', function () {
    $kasus = [
        [5, 'Perempuan', '4-6', 19, 1400, 25],
        [6, 'Laki-laki', '4-6', 19, 1400, 25],
        [7, 'Laki-laki', '7-9', 27, 1650, 40],
        [9, 'Perempuan', '7-9', 27, 1650, 40],
        [10, 'Laki-laki', '10-12', 36, 2000, 50],
        [12, 'Perempuan', '10-12', 38, 1900, 55],
        [13, 'Laki-laki', '13-15', 50, 2400, 70],
        [15, 'Perempuan', '13-15', 48, 2050, 65],
        [16, 'Laki-laki', '16-18', 60, 2650, 75],
        [18, 'Perempuan', '16-18', 52, 2100, 65],
    ];

    foreach ($kasus as [$usia, $jk, $kelompok, $bb, $energi, $protein]) {
        $akg = akgKelompokUmur($usia, $jk);
        sama($akg['kelompok_akg'], $kelompok, "$usia th $jk kelompok");
        sama($akg['bb_rujukan'], $bb, "$usia th $jk BB rujukan");
        sama($akg['energi'], $energi, "$usia th $jk energi");
        sama($akg['protein'], $protein, "$usia th $jk protein");
    }
});

uji('AKG di luar 4-18 tahun tidak tersedia', function () {
    sama(akgKelompokUmur(3, 'Laki-laki'), null);
    sama(akgKelompokUmur(19, 'Perempuan'), null);
});

uji('hitungAKG tetap melayani usia 6-12 tahun saja', function () {
    sama(hitungAKG(6, 'Perempuan')['energi'], 1400);
    sama(hitungAKG(11, 'Perempuan')['protein'], 55);
    sama(hitungAKG(5, 'Laki-laki'), null);
    sama(hitungAKG(13, 'Laki-laki'), null);
});

/* ---------- Berat badan ideal dan kebutuhan (IDAI 2011 bagian 5.2) ---------- */

uji('BB ideal = median IMT/U pada usia-tinggi x TB kuadrat', function () {
    // WHO 2007: median IMT laki-laki 91 bulan 15,624; perempuan 113 bulan 16,300
    sama(hitungBeratBadanIdeal(15.624, 125.0), 24.41);
    sama(hitungBeratBadanIdeal(16.3, 135.0), 29.71);
});

uji('BB ideal menolak tinggi atau median yang tidak valid', function () {
    menolak(fn() => hitungBeratBadanIdeal(15.6, 0));
    menolak(fn() => hitungBeratBadanIdeal(0, 125));
});

uji('Kebutuhan = BB ideal x AKG per kg pada kelompok umur usia-tinggi', function () {
    $kasus = [
        // BB ideal, usia-tinggi (bulan), jenis kelamin, kelompok, energi, protein
        [24.41, 91, 'Laki-laki', '7-9', 1492, 36.2],
        [29.71, 113, 'Perempuan', '7-9', 1816, 44.0],
        [25.86, 98, 'Laki-laki', '7-9', 1580, 38.3],
        [19.12, 66, 'Perempuan', '4-6', 1409, 25.2],
        [47.82, 163, 'Laki-laki', '13-15', 2295, 66.9],
        [20.56, 74, 'Perempuan', '4-6', 1515, 27.1],
    ];

    foreach ($kasus as [$bb, $usiaTinggi, $jk, $kelompok, $energi, $protein]) {
        $hasil = hitungKebutuhanGizi($bb, $usiaTinggi, $jk);
        sama($hasil['kelompok_akg'], $kelompok, "BB $bb kelompok");
        sama($hasil['energi'], $energi, "BB $bb energi");
        sama($hasil['protein'], $protein, "BB $bb protein");
    }
});

uji('Usia-tinggi di atas 18 tahun memakai kelompok 16-18', function () {
    $hasil = hitungKebutuhanGizi(70.0, 228, 'Laki-laki');
    sama($hasil['kelompok_akg'], '16-18');
    sama($hasil['energi'], 3092);
    sama($hasil['protein'], 87.5);
});

/* ---------- Gizi menu dari bahan (TKPI 2017, per 100 g) ---------- */

$ayam = ['energi' => 298, 'protein' => 18.2, 'lemak' => 25.0, 'karbohidrat' => 0, 'serat' => 0];
$minyak = ['energi' => 884, 'protein' => 0, 'lemak' => 100, 'karbohidrat' => 0, 'serat' => 0];
$wortel = ['energi' => 36, 'protein' => 1.0, 'lemak' => 0.6, 'karbohidrat' => 7.9, 'serat' => 1.0];
$nasi = ['energi' => 180, 'protein' => 3.0, 'lemak' => 0.3, 'karbohidrat' => 39.8, 'serat' => 0.2];

uji('Gizi menu = jumlah (gram / 100 x nilai TKPI) tiap bahan', function () use ($ayam, $minyak, $wortel) {
    $gizi = hitungGiziMenu([
        $ayam + ['berat_gram' => 40],
        $minyak + ['berat_gram' => 5],
        $wortel + ['berat_gram' => 100],
    ]);

    sama($gizi['energi'], 199.4);
    sama($gizi['protein'], 8.3);
    sama($gizi['lemak'], 15.6);
    sama($gizi['karbohidrat'], 7.9);
    sama($gizi['serat'], 1.0);
});

uji('Nilai tepat di tengah dibulatkan ke atas, sama seperti hitungan manual', function () use ($wortel) {
    // protein = udang 35 g x 21,0 + wortel 50 g x 1,0 + buncis 50 g x 2,4 = 7,35 + 0,5 + 1,2 = 9,05 -> 9,1
    $gizi = hitungGiziMenu([
        ['energi' => 91, 'protein' => 21.0, 'lemak' => 0.2, 'karbohidrat' => 0.1, 'serat' => 0, 'berat_gram' => 35],
        $wortel + ['berat_gram' => 50],
        ['energi' => 34, 'protein' => 2.4, 'lemak' => 0.3, 'karbohidrat' => 7.2, 'serat' => 1.9, 'berat_gram' => 50],
    ]);

    sama($gizi['protein'], 9.1);
});

uji('Menu tanpa bahan bergizi nol', function () {
    sama(hitungGiziMenu([])['energi'], 0.0);
});

/* ---------- Takaran nasi ---------- */

uji('Nasi dipilih kelipatan 50 g yang totalnya paling dekat ke target', function () {
    sama(takaranNasi(452.3, 163.4, 180), 150);
    sama(takaranNasi(373.0, 199.4, 180), 100);
});

uji('Nasi tidak kurang dari 50 g dan tidak lebih dari 250 g', function () {
    sama(takaranNasi(300.0, 290.0, 180), 50);
    sama(takaranNasi(689.0, 120.0, 180), 250);
});

uji('Bila dua takaran sama dekat, dipilih yang lebih kecil', function () {
    // 50 g -> 190 kkal, 100 g -> 280 kkal, target 235 berada tepat di tengah
    sama(takaranNasi(235.0, 100.0, 180), 50);
});

/* ---------- Porsi dan skor satu menu ---------- */

uji('Porsi menu = lauk + nasi, dengan selisih dan skor terhadap target', function () use ($nasi) {
    $menu = ['energi' => 163.4, 'protein' => 7.3, 'lemak' => 15.0, 'karbohidrat' => 0, 'serat' => 0];
    $target = ['target_energi' => 447.6, 'target_protein' => 10.86];

    $porsi = susunPorsiMenu($menu, $target, $nasi);

    sama($porsi['nasi_gram'], 150);
    sama($porsi['total_energi'], 433.4);
    sama($porsi['total_protein'], 11.8);
    sama($porsi['total_lemak'], 15.45);
    sama($porsi['total_karbohidrat'], 59.7);
    sama($porsi['total_serat'], 0.3);
    sama($porsi['selisih_energi'], -14.2);
    sama($porsi['selisih_protein'], 0.94);
    // 14,2 / 447,6 + 0,94 / 10,86
    sama($porsi['skor'], 0.1183);
});

/* ---------- Target per waktu makan ---------- */

$anak = [
    'usia' => 8,
    'jenis_kelamin' => 'Laki-laki',
    'berat_badan_ideal' => '24.41',
    'kebutuhan_energi' => '1492.00',
    'kebutuhan_protein' => '36.20',
];

uji('Target waktu makan diambil dari kebutuhan anak: 25% / 30% / 25%', function () use ($anak) {
    sama(targetMenuPlanning($anak, 'pagi')['target_energi'], 373.0);
    sama(targetMenuPlanning($anak, 'siang')['target_energi'], 447.6);
    sama(targetMenuPlanning($anak, 'malam')['target_energi'], 373.0);
    sama(targetMenuPlanning($anak, 'pagi')['target_protein'], 9.05);
    sama(targetMenuPlanning($anak, 'siang')['target_protein'], 10.86);
});

uji('Target ditolak bila kebutuhan anak belum dihitung', function () {
    menolak(fn() => targetMenuPlanning(
        ['usia' => 8, 'jenis_kelamin' => 'Laki-laki', 'kebutuhan_energi' => null, 'kebutuhan_protein' => null],
        'pagi'
    ));
});

uji('Target ditolak bila kebutuhan masih hasil metode lama (tanpa BB ideal)', function () {
    menolak(fn() => targetMenuPlanning(
        [
            'usia' => 12, 'jenis_kelamin' => 'Perempuan', 'berat_badan_ideal' => null,
            'kebutuhan_energi' => '1900.00', 'kebutuhan_protein' => '55.00',
        ],
        'pagi'
    ));
});

uji('Camilan bukan waktu makan utama', function () use ($anak) {
    menolak(fn() => targetMenuPlanning($anak, 'camilan'));
});

/* ---------- Ranking kandidat ---------- */

$dasar = [
    'kelompok_usia' => 'semua',
    'kategori_protein' => 'hewani',
    'waktu_makan' => 'pagi,siang,malam',
    'alergi_kandungan' => null,
    'lemak' => 0, 'karbohidrat' => 0, 'serat' => 0,
];

$menus = [
    ['id_menu' => 3, 'nama_menu' => 'Ayam C', 'jenis_lauk' => 'ayam', 'energi' => 120.0, 'protein' => 7.0] + $dasar,
    ['id_menu' => 5, 'nama_menu' => 'Telur E', 'jenis_lauk' => 'telur', 'energi' => 129.0, 'protein' => 6.8] + $dasar,
    ['id_menu' => 2, 'nama_menu' => 'Ayam B', 'jenis_lauk' => 'ayam', 'energi' => 199.4, 'protein' => 8.3] + $dasar,
    ['id_menu' => 4, 'nama_menu' => 'Ayam D', 'jenis_lauk' => 'ayam', 'energi' => 76.0, 'protein' => 7.4] + $dasar,
    ['id_menu' => 1, 'nama_menu' => 'Ayam A', 'jenis_lauk' => 'ayam', 'energi' => 163.4, 'protein' => 7.3] + $dasar,
];

uji('Kandidat diurutkan dari skor terkecil', function () use ($menus, $anak, $nasi) {
    $hasil = kandidatMenuPlanning($menus, $anak, 'hewani', 'siang', $nasi);

    // skor manual: A 0,1183 | B 0,2273 | D 0,2598 | C 0,2694 | E 0,2711
    sama(array_column($hasil, 'skor'), [0.1183, 0.2273, 0.2598, 0.2711]);
    sama(array_column($hasil, 'id_menu'), [1, 2, 4, 5]);
});

uji('Tiap jenis lauk hanya menampilkan 3 menu teratas', function () use ($menus, $anak, $nasi) {
    $hasil = kandidatMenuPlanning($menus, $anak, 'hewani', 'siang', $nasi);
    $ayam = array_filter($hasil, fn($m) => $m['jenis_lauk'] === 'ayam');

    sama(count($ayam), 3);
    sama(in_array(3, array_column($hasil, 'id_menu'), true), false, 'Ayam C (peringkat 4 ayam)');
});

uji('Takaran nasi tiap kandidat mengikuti energi lauknya', function () use ($menus, $anak, $nasi) {
    $hasil = kandidatMenuPlanning($menus, $anak, 'hewani', 'siang', $nasi);
    $nasiPerMenu = array_column($hasil, 'nasi_gram', 'id_menu');

    sama($nasiPerMenu[1], 150);
    sama($nasiPerMenu[2], 150);
    sama($nasiPerMenu[4], 200);
    sama($nasiPerMenu[5], 200);
});

uji('Skor sama diurutkan menurut nama menu', function () use ($dasar, $anak, $nasi) {
    $kembar = [
        ['id_menu' => 9, 'nama_menu' => 'Tumis B', 'jenis_lauk' => 'ayam', 'energi' => 163.4, 'protein' => 7.3] + $dasar,
        ['id_menu' => 8, 'nama_menu' => 'Tumis A', 'jenis_lauk' => 'ayam', 'energi' => 163.4, 'protein' => 7.3] + $dasar,
    ];

    sama(array_column(kandidatMenuPlanning($kembar, $anak, 'hewani', 'siang', $nasi), 'id_menu'), [8, 9]);
});

uji('Menu yang tidak lolos saringan tidak ikut diranking', function () use ($menus, $anak, $nasi) {
    $alergiTelur = $anak + ['alergi' => 'telur'];
    $menus[1]['alergi_kandungan'] = 'telur';

    $hasil = kandidatMenuPlanning($menus, $alergiTelur, 'hewani', 'siang', $nasi);

    sama(in_array(5, array_column($hasil, 'id_menu'), true), false);
});

echo $gagal === 0 ? "\nSemua uji lulus.\n" : "\n$gagal uji gagal.\n";
exit($gagal === 0 ? 0 : 1);
