<?php

/**
 * ============================================================
 * HELPER ANTROPOMETRI ANAK
 * ============================================================
 * Digunakan untuk anak usia 6–12 tahun.
 *
 * Referensi:
 * - WHO Growth Reference 2007
 *   - BMI-for-age
 *   - Height-for-age
 * - Permenkes No. 2 Tahun 2020
 *   - Kategori status gizi IMT/U usia 5–18 tahun
 *
 * Standar WHO disimpan pada tabel standar_antropometri
 * berdasarkan jenis kelamin dan umur dalam bulan.
 * ============================================================
 */

/**
 * Hitung umur dalam bulan penuh.
 * Contoh: 8 tahun 7 bulan = 103 bulan.
 */
function hitungUmurBulan(
    string $tanggalLahir,
    string $tanggalPengukuran
): int {

    if (empty($tanggalLahir) || empty($tanggalPengukuran)) {
        throw new InvalidArgumentException(
            'Tanggal lahir dan tanggal pengukuran wajib diisi.'
        );
    }

    try {
        $lahir = new DateTime($tanggalLahir);
        $pengukuran = new DateTime($tanggalPengukuran);
    } catch (Exception $e) {
        throw new InvalidArgumentException(
            'Format tanggal tidak valid.'
        );
    }

    if ($pengukuran < $lahir) {
        throw new InvalidArgumentException(
            'Tanggal pengukuran tidak boleh sebelum tanggal lahir.'
        );
    }

    $selisih = $lahir->diff($pengukuran);

    return ($selisih->y * 12) + $selisih->m;
}


/**
 * Ubah umur bulan menjadi tahun penuh.
 * Digunakan untuk lookup AKG.
 */
function hitungUmurTahun(int $umurBulan): int
{
    if ($umurBulan < 0) {
        return 0;
    }

    return intdiv($umurBulan, 12);
}

/**
 * IMT = BB (kg) / TB² (m)
 */
function hitungIMT(
    float $beratBadan,
    float $tinggiBadan
): float {

    if ($beratBadan <= 0) {
        throw new InvalidArgumentException(
            'Berat badan harus lebih dari 0 kg.'
        );
    }

    if ($tinggiBadan <= 0) {
        throw new InvalidArgumentException(
            'Tinggi badan harus lebih dari 0 cm.'
        );
    }

    $tinggiMeter = $tinggiBadan / 100;

    return $beratBadan / ($tinggiMeter * $tinggiMeter);
}


/**
 * Ambil standar antropometri WHO berdasarkan:
 * - indikator: imt_u / tb_u
 * - jenis kelamin
 * - umur bulan
 */
function ambilStandarAntropometri(
    mysqli $conn,
    string $indikator,
    string $jenisKelamin,
    int $umurBulan
): ?array {

    if (!in_array($indikator, ['imt_u', 'tb_u'], true)) {
        throw new InvalidArgumentException(
            'Indikator antropometri tidak valid.'
        );
    }

    if (!in_array(
        $jenisKelamin,
        ['Laki-laki', 'Perempuan'],
        true
    )) {
        throw new InvalidArgumentException(
            'Jenis kelamin tidak valid.'
        );
    }

    if ($umurBulan < 72 || $umurBulan > 155) {
        return null;
    }

    $sql = "
        SELECT
            L,
            M,
            S,
            stdev,
            sd5neg,
            sd4neg,
            sd3neg,
            sd2neg,
            sd1neg,
            median,
            sd1pos,
            sd2pos,
            sd3pos,
            sd4pos
        FROM standar_antropometri
        WHERE indikator = ?
          AND jenis_kelamin = ?
          AND umur_bulan = ?
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Gagal menyiapkan query standar antropometri: '
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        'ssi',
        $indikator,
        $jenisKelamin,
        $umurBulan
    );

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        throw new RuntimeException(
            'Gagal mengambil standar antropometri: ' . $error
        );
    }

    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $data ?: null;
}

/**
 * Hitung z-score IMT/U menggunakan metode LMS WHO.
 *
 * z = ((y/M)^L - 1) / (S × L)
 *
 * Untuk nilai di luar ±3 SD digunakan koreksi WHO.
 */
function hitungZScoreIMTU(
    float $imt,
    array $standar
): float {

    if ($imt <= 0) {
        throw new InvalidArgumentException(
            'Nilai IMT harus lebih dari 0.'
        );
    }

    $L = (float) ($standar['L'] ?? 0);
    $M = (float) ($standar['M'] ?? 0);
    $S = (float) ($standar['S'] ?? 0);

    if ($L == 0 || $M == 0 || $S == 0) {
        throw new RuntimeException(
            'Nilai L, M, atau S IMT/U tidak valid.'
        );
    }

    $z = (pow($imt / $M, $L) - 1) / ($S * $L);

    /*
     * Koreksi WHO jika z-score > +3 SD.
     */
    if ($z > 3) {
        $sd3 = (float) ($standar['sd3pos'] ?? 0);
        $sd2 = (float) ($standar['sd2pos'] ?? 0);
        $jarak = $sd3 - $sd2;

        if ($jarak > 0) {
            return 3 + (($imt - $sd3) / $jarak);
        }
    }

    /*
     * Koreksi WHO jika z-score < -3 SD.
     */
    if ($z < -3) {
        $sd3 = (float) ($standar['sd3neg'] ?? 0);
        $sd2 = (float) ($standar['sd2neg'] ?? 0);
        $jarak = $sd2 - $sd3;

        if ($jarak > 0) {
            return -3 + (($imt - $sd3) / $jarak);
        }
    }

    return $z;
}

/**
 * Hitung z-score TB/U.
 *
 * WHO Height-for-Age menggunakan L = 1:
 * z = (y - M) / (M × S)
 */
function hitungZScoreTBU(
    float $tinggiBadan,
    array $standar
): float {

    if ($tinggiBadan <= 0) {
        throw new InvalidArgumentException(
            'Tinggi badan harus lebih dari 0 cm.'
        );
    }

    $L = (float) ($standar['L'] ?? 0);
    $M = (float) ($standar['M'] ?? 0);
    $S = (float) ($standar['S'] ?? 0);

    if ($L != 1) {
        throw new RuntimeException(
            'Nilai L untuk TB/U harus 1.'
        );
    }

    if ($M == 0 || $S == 0) {
        throw new RuntimeException(
            'Nilai M atau S TB/U tidak valid.'
        );
    }

    return ($tinggiBadan - $M) / ($M * $S);
}


/**
 * RULE IF–THEN:
 * Menentukan status gizi berdasarkan z-score IMT/U.
 */
function statusIMTU(float $z): string
{
    if ($z < -3) {
        return 'Gizi buruk';
    }

    if ($z < -2) {
        return 'Gizi kurang';
    }

    if ($z <= 1) {
        return 'Gizi baik';
    }

    if ($z <= 2) {
        return 'Gizi lebih';
    }

    return 'Obesitas';
}


/**
 * RULE IF–THEN:
 * Menentukan status tinggi badan berdasarkan z-score TB/U.
 */
function statusTBU(float $z): string
{
    if ($z < -3) {
        return 'Sangat pendek';
    }

    if ($z < -2) {
        return 'Pendek';
    }

    if ($z <= 3) {
        return 'Normal';
    }

    return 'Tinggi';
}


/**
 * Menghitung seluruh hasil antropometri anak.
 *
 * Alur:
 * umur bulan
 * → IMT
 * → standar WHO
 * → z-score IMT/U dan TB/U
 * → klasifikasi status
 */
function hitungAntropometriAnak(
    mysqli $conn,
    string $tanggalLahir,
    string $tanggalPengukuran,
    string $jenisKelamin,
    float $beratBadan,
    float $tinggiBadan
): ?array {

    $umurBulan = hitungUmurBulan(
        $tanggalLahir,
        $tanggalPengukuran
    );

    /*
     * Standar antropometri tersedia
     * untuk umur 72–155 bulan.
     */
    if ($umurBulan < 72 || $umurBulan > 155) {
        return null;
    }

    $umurTahun = hitungUmurTahun($umurBulan);

    $imt = hitungIMT(
        $beratBadan,
        $tinggiBadan
    );

    /*
     * Ambil standar WHO sesuai umur dan jenis kelamin.
     */
    $standarIMTU = ambilStandarAntropometri(
        $conn,
        'imt_u',
        $jenisKelamin,
        $umurBulan
    );

    if (!$standarIMTU) {
        throw new RuntimeException(
            'Standar IMT/U WHO untuk usia '
            . $umurBulan
            . ' bulan tidak ditemukan.'
        );
    }

    $standarTBU = ambilStandarAntropometri(
        $conn,
        'tb_u',
        $jenisKelamin,
        $umurBulan
    );

    if (!$standarTBU) {
        throw new RuntimeException(
            'Standar TB/U WHO untuk usia '
            . $umurBulan
            . ' bulan tidak ditemukan.'
        );
    }

    /*
     * Hitung z-score.
     */
    $zIMTU = hitungZScoreIMTU(
        $imt,
        $standarIMTU
    );

    $zTBU = hitungZScoreTBU(
        $tinggiBadan,
        $standarTBU
    );

    /*
     * RULE IF–THEN untuk klasifikasi hasil.
     */
    $statusIMTU = statusIMTU($zIMTU);
    $statusTBU = statusTBU($zTBU);

    return [
        'umur_bulan' => $umurBulan,
        'umur_tahun' => $umurTahun,

        'imt' => $imt,
        'imt_tampil' => round($imt, 2),

        'z_imtu' => $zIMTU,
        'z_imtu_tampil' => round($zIMTU, 2),
        'status_imtu' => $statusIMTU,

        'z_tbu' => $zTBU,
        'z_tbu_tampil' => round($zTBU, 2),
        'status_tbu' => $statusTBU
    ];
}