<?php

/**
 * Menghitung kebutuhan energi dan protein harian anak
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

    // AKG 2019: usia 6 tahun mengikuti kelompok 4–6 tahun.
    if ($usia === 6) {
        return [
            'energi' => 1400,
            'protein' => 25,
            'kelompok_akg' => '4-6',
            'kelompok_usia' => $kelompokUsia
        ];
    }

    // AKG 2019: kelompok usia 7–9 tahun.
    if ($usia <= 9) {
        return [
            'energi' => 1650,
            'protein' => 40,
            'kelompok_akg' => '7-9',
            'kelompok_usia' => $kelompokUsia
        ];
    }

    // AKG 2019: laki-laki usia 10–12 tahun.
    if ($jenisKelamin === 'Laki-laki') {
        return [
            'energi' => 2000,
            'protein' => 50,
            'kelompok_akg' => '10-12',
            'kelompok_usia' => $kelompokUsia
        ];
    }

    // AKG 2019: perempuan usia 10–12 tahun.
    return [
        'energi' => 1900,
        'protein' => 55,
        'kelompok_akg' => '10-12',
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