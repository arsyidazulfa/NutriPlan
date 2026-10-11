    <?php

    require_once __DIR__ . '/akg.php';

    /**
     * Mengubah teks alergi menjadi token yang konsisten.
     */
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

    /**
     * Mengecek keamanan menu berdasarkan alergi anak.
     */
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
     * Target energi makanan utama:
     * pagi 25%, siang 30%, malam 25%.
     */
    function targetMenuPlanning(array $anak, string $slot): array
    {
        $proporsi = [
            'pagi'  => 0.25,
            'siang' => 0.30,
            'malam' => 0.25
        ];

        if (!isset($proporsi[$slot])) {
            throw new InvalidArgumentException('Waktu makan tidak valid.');
        }

        $akg = hitungAKG(
            (int) ($anak['usia'] ?? 0),
            (string) ($anak['jenis_kelamin'] ?? '')
        );

        if (!$akg) {
            throw new InvalidArgumentException(
                'Perencanaan hanya tersedia untuk anak usia 6–12 tahun.'
            );
        }

        return [
            'target_energi' => $akg['energi'] * $proporsi[$slot],
            'target_protein' => null,
            'status_target_protein' => 'PERLU KEPUTUSAN',
            'status_ranking' => 'PERLU KEPUTUSAN'
        ];
    }

    /**
     * Rule-Based Filtering:
     * R1 usia, R2 protein, R3 waktu makan, R4 alergi.
     */
    function menuLolosHardRule(
        array $menu,
        array $anak,
        string $kategoriProtein,
        string $slot
    ): bool {
        if (
            !in_array($kategoriProtein, ['hewani', 'nabati'], true) ||
            !in_array($slot, ['pagi', 'siang', 'malam'], true)
        ) {
            return false;
        }

        // R1 — Kelompok usia
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
        $proteinMenu = strtolower(trim(
            (string) ($menu['kategori_protein'] ?? '')
        ));

        if ($proteinMenu !== $kategoriProtein) {
            return false;
        }

        // R3 — Waktu makan
        $waktuMakan = array_map(
            static fn($nilai) => strtolower(trim($nilai)),
            explode(',', (string) ($menu['waktu_makan'] ?? ''))
        );

        if (!in_array($slot, $waktuMakan, true)) {
            return false;
        }

        // R4 — Alergi
        return menuAmanUntukAnak($menu, $anak);
    }

    /**
     * Mengumpulkan menu yang lolos seluruh hard rule.
     */
    function kandidatMenuPlanning(
        array $menus,
        array $anak,
        string $kategoriProtein,
        string $slot
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

            unset($menu['skor_rekomendasi']);

            $menu = array_merge($menu, $target);
            $menu['selisih_energi'] = abs(
                (float) ($menu['energi'] ?? 0) -
                (float) $target['target_energi']
            );

            // Ranking protein belum ditentukan.
            $menu['selisih_protein'] = null;

            $kandidat[] = $menu;
        }

        return $kandidat;
    }

    /**
     * Mengambil menu dari database lalu menjalankan Rule-Based Filtering.
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
            $slot
        );
    }