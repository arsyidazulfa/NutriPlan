<?php

/**
 * Uji perhitungan yang membaca database (hanya SELECT, tidak mengubah data).
 * Jalankan setelah sql/migrasi_menu_tkpi.sql diterapkan:
 *
 *   php tests/gizi_database.php
 *
 * Nilai harapan diambil dari file WHO Reference 2007, TKPI 2017, dan
 * PMK 28/2019, bukan dari fungsi yang diuji.
 */

require_once __DIR__ . '/../config/koneksi.php';

foreach (['akg', 'antropometri', 'kebutuhan', 'gizi_menu', 'rekomendasi'] as $helper) {
    require_once __DIR__ . '/../helpers/' . $helper . '.php';
}

/** @var mysqli $conn */

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

function satuNilai(mysqli $conn, string $sql)
{
    $baris = mysqli_fetch_row(mysqli_query($conn, $sql));
    return $baris[0] ?? null;
}

/* ---------- Standar WHO ---------- */

uji('Standar WHO tersedia 61-228 bulan untuk tiap indikator dan jenis kelamin', function () use ($conn) {
    $hasil = mysqli_query(
        $conn,
        "SELECT indikator, jenis_kelamin, COUNT(*) n, MIN(umur_bulan) awal, MAX(umur_bulan) akhir
         FROM standar_antropometri GROUP BY indikator, jenis_kelamin"
    );

    $kelompok = 0;
    while ($baris = mysqli_fetch_assoc($hasil)) {
        $kelompok++;
        $label = $baris['indikator'] . ' ' . $baris['jenis_kelamin'];
        sama((int) $baris['n'], 168, "$label jumlah baris");
        sama((int) $baris['awal'], 61, "$label umur awal");
        sama((int) $baris['akhir'], 228, "$label umur akhir");
    }
    sama($kelompok, 4, 'jumlah kelompok');
});

/* ---------- Usia-tinggi ---------- */

uji('Usia-tinggi = umur dengan median TB/U paling dekat ke tinggi anak', function () use ($conn) {
    // WHO 2007: laki-laki 91 bln M=124,9964; perempuan 113 bln M=135,0299
    sama(cariUsiaTinggi($conn, 'Laki-laki', 125.0), 91);
    sama(cariUsiaTinggi($conn, 'Perempuan', 135.0), 113);
    sama(cariUsiaTinggi($conn, 'Laki-laki', 128.0), 98);
    sama(cariUsiaTinggi($conn, 'Perempuan', 112.0), 66);
    sama(cariUsiaTinggi($conn, 'Laki-laki', 160.0), 163);
});

uji('Usia-tinggi di luar tabel memakai batas tabel', function () use ($conn) {
    sama(cariUsiaTinggi($conn, 'Laki-laki', 100.0), 61);
    sama(cariUsiaTinggi($conn, 'Laki-laki', 185.0), 228);
});

uji('Median IMT/U dibaca dari standar WHO', function () use ($conn) {
    sama(ambilMedianIMTU($conn, 'Laki-laki', 91), 15.624);
    sama(ambilMedianIMTU($conn, 'Perempuan', 113), 16.3);
    sama(ambilMedianIMTU($conn, 'Laki-laki', 60), null);
});

/* ---------- Kebutuhan anak ---------- */

uji('Kebutuhan anak dihitung dari jenis kelamin dan tinggi badan', function () use ($conn) {
    $kasus = [
        // jenis kelamin, TB, usia-tinggi, BB ideal, kelompok AKG, energi, protein
        ['Laki-laki', 125.0, 91, 24.41, '7-9', 1492, 36.2],
        ['Perempuan', 135.0, 113, 29.71, '7-9', 1816, 44.0],
        ['Laki-laki', 128.0, 98, 25.86, '7-9', 1580, 38.3],
        ['Perempuan', 112.0, 66, 19.12, '4-6', 1409, 25.2],
        ['Laki-laki', 160.0, 163, 47.82, '13-15', 2295, 66.9],
    ];

    foreach ($kasus as [$jk, $tb, $usiaTinggi, $bbIdeal, $kelompok, $energi, $protein]) {
        $hasil = hitungKebutuhanAnak($conn, $jk, $tb);
        sama($hasil['usia_tinggi_bulan'], $usiaTinggi, "$jk $tb usia-tinggi");
        sama($hasil['berat_badan_ideal'], $bbIdeal, "$jk $tb BB ideal");
        sama($hasil['kelompok_akg'], $kelompok, "$jk $tb kelompok AKG");
        sama($hasil['energi'], $energi, "$jk $tb energi");
        sama($hasil['protein'], $protein, "$jk $tb protein");
    }
});

/* ---------- Bahan dan menu ---------- */

uji('Nasi dibaca dari tabel bahan dengan nilai TKPI AP001', function () use ($conn) {
    $nasi = ambilBahanNasi($conn);
    sama($nasi['energi'], 180.0);
    sama($nasi['protein'], 3.0);
    sama($nasi['lemak'], 0.3);
    sama($nasi['karbohidrat'], 39.8);
    sama($nasi['serat'], 0.2);
});

uji('Tersedia minimal 40 menu, minimal 5 untuk tiap jenis lauk', function () use ($conn) {
    // Batas bawah, bukan jumlah pasti, supaya menu boleh ditambah setelah migrasi.
    $total = (int) satuNilai($conn, 'SELECT COUNT(*) FROM menu');

    if ($total < 40) {
        throw new RuntimeException("hanya $total menu");
    }

    $hasil = mysqli_query($conn, "SELECT COALESCE(jenis_lauk, '') jenis_lauk, COUNT(*) n FROM menu GROUP BY 1");
    $jumlah = array_column(mysqli_fetch_all($hasil, MYSQLI_ASSOC), 'n', 'jenis_lauk');

    foreach (['ayam', 'sapi', 'kambing', 'ikan', 'udang', 'telur', 'tahu', 'tempe'] as $jenis) {
        if ((int) ($jumlah[$jenis] ?? 0) < 5) {
            throw new RuntimeException("jenis $jenis hanya " . (int) ($jumlah[$jenis] ?? 0) . ' menu');
        }
    }
});

uji('Tiap jenis lauk punya minimal 3 menu di tiap waktu makan', function () use ($conn) {
    foreach (['pagi', 'siang', 'malam'] as $slot) {
        $hasil = mysqli_query(
            $conn,
            "SELECT jenis_lauk, COUNT(*) n FROM menu
             WHERE FIND_IN_SET('$slot', waktu_makan) > 0 GROUP BY jenis_lauk"
        );
        $baris = mysqli_fetch_all($hasil, MYSQLI_ASSOC);

        sama(count($baris), 8, "$slot jumlah jenis lauk");
        foreach ($baris as $b) {
            if ((int) $b['n'] < 3) {
                throw new RuntimeException("$slot {$b['jenis_lauk']} hanya {$b['n']} menu");
            }
        }
    }
});

uji('Komposisi menu dibaca berurutan: lauk, sayur, lalu minyak', function () use ($conn) {
    $id = (int) satuNilai($conn, "SELECT id_menu FROM menu WHERE nama_menu = 'Sop Ayam'");
    $komposisi = ambilKomposisiMenu($conn, $id);

    sama(array_column($komposisi, 'peran'), ['lauk', 'sayur', 'sayur', 'sayur', 'sayur', 'minyak']);
    sama($komposisi[0]['kode_tkpi'], 'FR005');
    sama((float) $komposisi[0]['berat_gram'], 40.0);
    sama($komposisi[0]['urt'], '1 potong sedang');
    sama($komposisi[5]['kode_tkpi'], 'KR012');
    sama((float) $komposisi[5]['berat_gram'], 5.0);
});

uji('Gizi Sop Ayam sama dengan hitungan manual dari TKPI', function () use ($conn) {
    // ayam 40 g (298 kkal/100 g) + wortel, kol, buncis, kembang kol masing-masing 25 g
    // (36, 29, 34, 25 kkal/100 g) + minyak 5 g (884 kkal/100 g)
    // energi  = 119,2 + 9 + 7,25 + 8,5 + 6,25 + 44,2 = 194,4
    // protein = 7,28 + 0,25 + 0,35 + 0,6 + 0,6 + 0 = 9,08
    $menu = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT energi, protein FROM menu WHERE nama_menu = 'Sop Ayam'"
    ));

    sama((float) $menu['energi'], 194.4);
    sama((float) $menu['protein'], 9.1);
});

uji('Gizi tiap menu yang tersimpan sama dengan hasil hitung dari komposisinya', function () use ($conn) {
    $menus = mysqli_fetch_all(mysqli_query($conn, 'SELECT * FROM menu'), MYSQLI_ASSOC);

    foreach ($menus as $menu) {
        $hitung = hitungGiziMenu(ambilKomposisiMenu($conn, (int) $menu['id_menu']));

        foreach (ZAT_GIZI_MENU as $zat) {
            sama((float) $menu[$zat], $hitung[$zat], "{$menu['nama_menu']} $zat");
        }
    }
});

/* ---------- Rekomendasi dari database ---------- */

$anakUji = [
    'usia' => 8,
    'jenis_kelamin' => 'Laki-laki',
    'berat_badan_ideal' => '24.41',
    'kebutuhan_energi' => '1492.00',
    'kebutuhan_protein' => '36.20',
    'alergi' => '',
];

uji('Rekomendasi terurut menurut skor dan paling banyak 3 menu per jenis lauk', function () use ($conn, $anakUji) {
    $hasil = ambilMenuPlanning($conn, 'hewani', $anakUji, 'siang');

    // 6 jenis lauk hewani x 3 menu teratas
    sama(count($hasil), 18);

    $skor = array_column($hasil, 'skor');
    $terurut = $skor;
    sort($terurut);
    sama($skor, $terurut, 'urutan skor');

    foreach (array_count_values(array_column($hasil, 'jenis_lauk')) as $jenis => $n) {
        sama($n, 3, "jenis $jenis");
    }
});

uji('Sumber protein nabati hanya menampilkan tahu dan tempe', function () use ($conn, $anakUji) {
    $jenis = array_unique(array_column(ambilMenuPlanning($conn, 'nabati', $anakUji, 'pagi'), 'jenis_lauk'));
    sort($jenis);
    sama($jenis, ['tahu', 'tempe']);
});

uji('Menu beralergen tidak direkomendasikan untuk anak yang alergi', function () use ($conn, $anakUji) {
    $hasil = ambilMenuPlanning($conn, 'hewani', ['alergi' => 'telur'] + $anakUji, 'siang');

    sama(in_array('telur', array_column($hasil, 'jenis_lauk'), true), false, 'jenis telur');

    foreach ($hasil as $menu) {
        if (in_array('telur', tokenAlergi((string) $menu['alergi_kandungan']), true)) {
            throw new RuntimeException($menu['nama_menu'] . ' mengandung telur');
        }
    }
});

echo $gagal === 0 ? "\nSemua uji lulus.\n" : "\n$gagal uji gagal.\n";
exit($gagal === 0 ? 0 : 1);
