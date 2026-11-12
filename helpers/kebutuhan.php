<?php

require_once __DIR__ . '/akg.php';
require_once __DIR__ . '/antropometri.php';

/*
  KEBUTUHAN GIZI HARIAN ANAK

  Rekomendasi IDAI 2011, Asuhan Nutrisi Pediatrik, bagian 5.2:
  kebutuhan kalori = berat badan ideal × RDA menurut usia-tinggi.
  Berlaku untuk gizi baik, gizi kurang, maupun obesitas.
 
  RDA yang dipakai adalah AKG Permenkes No. 28 Tahun 2019,
  diubah menjadi per kg dengan membaginya dengan BB rujukan
  kelompok umur yang sama.

 */

function hitungKebutuhanGizi( /* Kebutuhan energi dan protein harian dari berat badan ideal dan usia-tinggi anak.*/ 
    float $beratBadanIdeal,   /* $beratBadanIdeal	Berat badan ideal anak dalam kilogram */
    int $usiaTinggiBulan,     /* $usiaTinggiBulan	Usia-tinggi anak dalam bulan */
    string $jenisKelamin      /* $jenisKelamin	Jenis kelamin anak */
): array {

    if ($beratBadanIdeal <= 0) {   
        throw new InvalidArgumentException(
            'Berat badan ideal harus lebih dari 0 kg.'
        );
    }

    // Tabel AKG yang dipakai berhenti di kelompok 16–18 tahun.
    $usiaTinggiTahun = min(18, intdiv($usiaTinggiBulan, 12));     /* intdiv(..., 12) mengubah bulan menjadi tahun penuh */
                                                                  /* min(18, ...) membatasi hasil maksimal 18 tahun */
    $akg = akgKelompokUmur($usiaTinggiTahun, $jenisKelamin);
    /* Mengambil nilai energi, protein, dan BB rujukan 
    berdasarkan kelompok usia-tinggi serta jenis kelamin
    */

    if (!$akg) {
        throw new InvalidArgumentException(
            'AKG untuk usia-tinggi anak tidak tersedia.'
        );
    }

    $energiPerKg = $akg['energi'] / $akg['bb_rujukan'];
    $proteinPerKg = $akg['protein'] / $akg['bb_rujukan'];

    return [
        'energi' => (int) round($beratBadanIdeal * $energiPerKg),
        'protein' => round($beratBadanIdeal * $proteinPerKg, 1),
        'kelompok_akg' => $akg['kelompok_akg'],
        'bb_rujukan' => $akg['bb_rujukan'],
        'energi_per_kg' => $energiPerKg,
        'protein_per_kg' => $proteinPerKg
    ];
}

/*
  Kebutuhan gizi harian seorang anak.
 
  Alur:
  tinggi badan
  → usia-tinggi (standar TB/U WHO)
  → median IMT/U pada usia-tinggi
  → berat badan ideal
  → kebutuhan energi dan protein
 */

function hitungKebutuhanAnak(  /* Menentukan usia-tinggi, median IMT/U, BBI, lalu kebutuhan gizi harian */
    mysqli $conn,
    string $jenisKelamin,
    float $tinggiBadan
): array {

    $usiaTinggi = cariUsiaTinggi(
        $conn,
        $jenisKelamin,
        $tinggiBadan
    );

    if ($usiaTinggi === null) {
        throw new RuntimeException(
            'Standar TB/U WHO tidak ditemukan.'
        );
    }

    $medianImt = ambilMedianIMTU(
        $conn,
        $jenisKelamin,
        $usiaTinggi
    );

    if ($medianImt === null) {
        throw new RuntimeException(
            'Median IMT/U WHO untuk usia-tinggi '
            . $usiaTinggi
            . ' bulan tidak ditemukan.'
        );
    }

    $beratBadanIdeal = hitungBeratBadanIdeal(
        $medianImt,
        $tinggiBadan
    );

    return [
        'usia_tinggi_bulan' => $usiaTinggi,
        'median_imt' => $medianImt,
        'berat_badan_ideal' => $beratBadanIdeal
    ] + hitungKebutuhanGizi(
        $beratBadanIdeal,
        $usiaTinggi,
        $jenisKelamin
    );
}
