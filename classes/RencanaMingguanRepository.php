<?php

class RencanaMingguanRepository
{
    private mysqli $conn;

    private array $hariList = [
        'Senin', 'Selasa', 'Rabu', 'Kamis',
        'Jumat', 'Sabtu', 'Minggu'
    ];

    private array $slotList = ['pagi', 'siang', 'malam'];

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function validasiTanggalMulai(string $tanggal): DateTime
    {
        $date = DateTime::createFromFormat('!Y-m-d', $tanggal);

        if (
            !$date ||
            $date->format('Y-m-d') !== $tanggal ||
            $date->format('N') !== '1'
        ) {
            throw new InvalidArgumentException(
                'Tanggal mulai planning harus tanggal valid pada hari Senin.'
            );
        }

        return $date;
    }

    public function cariPlanTerakhir(int $idUser, int $idAnak): ?array
    {
        $stmt = mysqli_prepare(
            $this->conn,
            'SELECT id_plan, kategori_protein, tanggal_mulai
             FROM planning_mingguan
             WHERE id_user = ? AND id_anak = ?
             ORDER BY id_plan DESC LIMIT 1'
        );

        mysqli_stmt_bind_param($stmt, 'ii', $idUser, $idAnak);
        mysqli_stmt_execute($stmt);

        $plan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $plan ?: null;
    }

    public function cariPlan(
        int $idUser,
        int $idAnak,
        string $tanggalMulai,
        string $kategoriProtein
    ): int {
        $stmt = mysqli_prepare(
            $this->conn,
            'SELECT id_plan
             FROM planning_mingguan
             WHERE id_user = ?
               AND id_anak = ?
               AND tanggal_mulai = ?
               AND LOWER(TRIM(kategori_protein)) = LOWER(?)
             ORDER BY id_plan DESC LIMIT 1'
        );

        if (!$stmt) return 0;

        mysqli_stmt_bind_param(
            $stmt,
            'iiss',
            $idUser,
            $idAnak,
            $tanggalMulai,
            $kategoriProtein
        );

        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return (int) ($row['id_plan'] ?? 0);
    }

    public function buatPlan(
        int $idUser,
        int $idAnak,
        string $tanggalMulai,
        string $kategoriProtein
    ): int {
        $awal = $this->validasiTanggalMulai($tanggalMulai);
        $akhir = clone $awal;
        $akhir->modify('+6 days');

        $tglAwal = $awal->format('Y-m-d');
        $tglAkhir = $akhir->format('Y-m-d');

        $stmt = mysqli_prepare(
            $this->conn,
            'INSERT INTO planning_mingguan
             (id_user, id_anak, tanggal_mulai, tanggal_selesai, kategori_protein)
             VALUES (?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new RuntimeException('Planning mingguan gagal disiapkan.');
        }

        mysqli_stmt_bind_param(
            $stmt,
            'iisss',
            $idUser,
            $idAnak,
            $tglAwal,
            $tglAkhir,
            $kategoriProtein
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Planning mingguan gagal dibuat.');
        }

        $idPlan = (int) mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        return $idPlan;
    }

    public function ambilPilihan(int $idPlan): array
    {
        if ($idPlan <= 0) return [];

        $stmt = mysqli_prepare(
            $this->conn,
            "SELECT hari_ke, waktu_makan, id_menu
             FROM planning_detail
             WHERE id_plan = ?
               AND hari_ke BETWEEN 1 AND 7
               AND waktu_makan IN ('pagi','siang','malam')
             ORDER BY hari_ke"
        );

        if (!$stmt) return [];

        mysqli_stmt_bind_param($stmt, 'i', $idPlan);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $pilihan = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $pilihan[(int) $row['hari_ke']][$row['waktu_makan']] =
                (int) $row['id_menu'];
        }

        mysqli_stmt_close($stmt);

        return $pilihan;
    }

    public function simpanPilihanAnak(
        int $idUser,
        int $idAnak,
        string $tanggalMulai,
        string $kategoriProtein,
        int $hariKe,
        string $slot,
        int $idMenu
    ): int {
        $this->validasiTanggalMulai($tanggalMulai);
        mysqli_begin_transaction($this->conn);

        try {
            $stmt = mysqli_prepare(
                $this->conn,
                'SELECT id_anak
                 FROM data_anak
                 WHERE id_anak = ? AND id_user = ?
                 FOR UPDATE'
            );

            mysqli_stmt_bind_param($stmt, 'ii', $idAnak, $idUser);
            mysqli_stmt_execute($stmt);

            $anak = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$anak) {
                throw new RuntimeException('Data anak tidak ditemukan.');
            }

            $idPlan = $this->cariPlan(
                $idUser,
                $idAnak,
                $tanggalMulai,
                $kategoriProtein
            );

            if (!$idPlan) {
                $idPlan = $this->buatPlan(
                    $idUser,
                    $idAnak,
                    $tanggalMulai,
                    $kategoriProtein
                );
            }

            $this->simpanPilihan(
                $idPlan,
                $hariKe,
                $slot,
                $idMenu,
                $tanggalMulai,
                false
            );

            mysqli_commit($this->conn);

            return $idPlan;

        } catch (Throwable $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    public function simpanPilihan(
        int $idPlan,
        int $hariKe,
        string $slot,
        int $idMenu,
        string $tanggalMulai,
        bool $kelolaTransaksi = true
    ): void {
        if ($hariKe < 1 || $hariKe > 7) {
            throw new InvalidArgumentException('Hari planning tidak valid.');
        }

        if (!in_array($slot, $this->slotList, true)) {
            throw new InvalidArgumentException('Waktu makan tidak valid.');
        }

        if ($idMenu <= 0) {
            throw new InvalidArgumentException('Menu tidak valid.');
        }

        $awal = $this->validasiTanggalMulai($tanggalMulai);
        $tanggalHari = clone $awal;
        $tanggalHari->modify('+' . ($hariKe - 1) . ' days');

        $namaHari = $this->hariList[$hariKe - 1];
        $tanggal = $tanggalHari->format('Y-m-d');

        if ($kelolaTransaksi) {
            mysqli_begin_transaction($this->conn);
        }

        try {
            $stmt = mysqli_prepare(
                $this->conn,
                'SELECT tanggal_mulai
                 FROM planning_mingguan
                 WHERE id_plan = ?
                 FOR UPDATE'
            );

            mysqli_stmt_bind_param($stmt, 'i', $idPlan);
            mysqli_stmt_execute($stmt);

            $plan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$plan || $plan['tanggal_mulai'] !== $tanggalMulai) {
                throw new InvalidArgumentException(
                    'Planning atau tanggal mulai tidak sesuai.'
                );
            }

            $stmt = mysqli_prepare(
                $this->conn,
                'DELETE FROM planning_detail
                 WHERE id_plan = ? AND hari_ke = ? AND waktu_makan = ?'
            );

            mysqli_stmt_bind_param($stmt, 'iis', $idPlan, $hariKe, $slot);

            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('Pilihan lama gagal dihapus.');
            }

            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare(
                $this->conn,
                'INSERT INTO planning_detail
                 (id_plan, hari_ke, nama_hari, tanggal, waktu_makan, id_menu)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            mysqli_stmt_bind_param(
                $stmt,
                'iisssi',
                $idPlan,
                $hariKe,
                $namaHari,
                $tanggal,
                $slot,
                $idMenu
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('Pilihan menu gagal disimpan.');
            }

            mysqli_stmt_close($stmt);

            if ($kelolaTransaksi) {
                mysqli_commit($this->conn);
            }

        } catch (Throwable $e) {
            if ($kelolaTransaksi) {
                mysqli_rollback($this->conn);
            }

            throw $e;
        }
    }

    public function getHariList(): array
    {
        return $this->hariList;
    }

    public function getSlotList(): array
    {
        return $this->slotList;
    }
}