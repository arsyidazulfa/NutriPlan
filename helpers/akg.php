<?php

/**
 * Angka Kecukupan Gizi per kelompok umur.
 * Sumber: Permenkes No. 28 Tahun 2019, Lampiran I, Tabel 1.
 *
 * Tiap baris: batas umur atas (tahun), kelompok, lalu
 * [BB rujukan (kg), energi (kkal), protein (g)] per jenis kelamin.
 * Kelompok 4–6 dan 7–9 tahun tidak dibedakan menurut jenis kelamin.
 */
function akgKelompokUmur(
    int $usiaTahun,
    string $jenisKelamin
): ?array {

    if (!in_array(
        $jenisKelamin,
        ['Laki-laki', 'Perempuan'],
        true
    )) {
        throw new InvalidArgumentException(
            'Jenis kelamin tidak valid.'
        );
    }

    if ($usiaTahun < 4 || $usiaTahun > 18) {
        return null;
    }

    $tabel = [
        [6,  '4-6',   'Laki-laki' => [19, 1400, 25], 'Perempuan' => [19, 1400, 25]],
        [9,  '7-9',   'Laki-laki' => [27, 1650, 40], 'Perempuan' => [27, 1650, 40]],
        [12, '10-12', 'Laki-laki' => [36, 2000, 50], 'Perempuan' => [38, 1900, 55]],
        [15, '13-15', 'Laki-laki' => [50, 2400, 70], 'Perempuan' => [48, 2050, 65]],
        [18, '16-18', 'Laki-laki' => [60, 2650, 75], 'Perempuan' => [52, 2100, 65]],
    ];

    foreach ($tabel as $baris) {
        if ($usiaTahun <= $baris[0]) {
            [$bbRujukan, $energi, $protein] = $baris[$jenisKelamin];

            return [
                'kelompok_akg' => $baris[1],
                'bb_rujukan' => $bbRujukan,
                'energi' => $energi,
                'protein' => $protein
            ];
        }
    }

    return null;
}


/**
 * AKG energi dan protein harian untuk anak usia 6–12 tahun
 * berdasarkan Permenkes No. 28 Tahun 2019.
 */
function hitungAKG(
    int $usia,
    string $jenisKelamin
): ?array {

    if (!in_array(
        $jenisKelamin,
        ['Laki-laki', 'Perempuan'],
        true
    )) {
        throw new InvalidArgumentException(
            'Jenis kelamin tidak valid.'
        );
    }

    // Sistem NutriPlan digunakan untuk anak usia 6–12 tahun.
    $kelompokUsia = kelompokUsia($usia);

    if ($kelompokUsia === null) {
        return null;
    }

    $akg = akgKelompokUmur($usia, $jenisKelamin);

    return [
        'energi' => $akg['energi'],
        'protein' => $akg['protein'],
        'kelompok_akg' => $akg['kelompok_akg'],
        'kelompok_usia' => $kelompokUsia
    ];
}


/**
 * Kelompok usia internal NutriPlan untuk pengelompokan menu.
 */
function kelompokUsia(int $usia): ?string
{
    if ($usia >= 6 && $usia <= 9) {
        return '6-9';
    }

    if ($usia >= 10 && $usia <= 12) {
        return '10-12';
    }

    return null;
}
