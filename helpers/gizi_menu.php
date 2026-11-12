    <?php

    /*
     
      GIZI MENU DAN TAKARAN NASI
     
      - Berat tiap bahan mengikuti satuan penukar pada Daftar Bahan
        Makanan Penukar (Lampiran Permenkes No. 41 Tahun 2014).
      - Kandungan gizi tiap bahan diambil dari Tabel Komposisi
        Pangan Indonesia (TKPI) 2017, per 100 gram.
      - Menu menyimpan 1 porsi lauk. Nasi ditakar terpisah agar
        total energi mendekati target waktu makan anak.
     
     */

    const ZAT_GIZI_MENU = ['energi', 'protein', 'lemak', 'karbohidrat', 'serat'];

    const NASI_MINIMAL_GRAM = 50;    /* CONST : Mendefinisikan nilai tetap */
    const NASI_MAKSIMAL_GRAM = 250;
    const NASI_KELIPATAN_GRAM = 50;

    /*
      Pembulatan setengah ke atas seperti hitungan manual.
     
      Hasil perkalian pecahan pada komputer bisa meleset sangat kecil
      (9,05 tersimpan sebagai 9,0499999…), sehingga nilai dirapikan
      dulu sebelum dibulatkan ke jumlah desimal yang diminta.
     */ 
    function bulatkan(float $nilai, int $desimal): float      /* ($nilai): Angka yang akan dibulatkan, ($desimal): Jumlah angka di belakang koma */
    {
        return round(round($nilai, $desimal + 6), $desimal); /* RETURN Mengembalikan nilai dari fungsi */
    }


    /*
      Gizi 1 porsi lauk = jumlah (berat ÷ 100 × nilai TKPI) tiap bahan.
     */
    function hitungGiziMenu(array $komposisi): array     /* (hitungGiziMenu) Menghitung total gizi satu porsi lauk */
    {
        $total = array_fill_keys(ZAT_GIZI_MENU, 0.0);

        foreach ($komposisi as $bahan) {   /* ($komposisi) Daftar bahan penyusun menu */
            $berat = (float) ($bahan['berat_gram'] ?? 0);  /* FLOAT Tipe data angka desimal */

            foreach (ZAT_GIZI_MENU as $zat) {
                $total[$zat] += $berat / 100 * (float) ($bahan[$zat] ?? 0);   /* ($total) Akumulasi kandungan gizi 
                                                                                 ($berat) Satu bahan yang sedang dihitung
                                                                                 ($zat) Jenis zat gizi yang dihitung */
            }
        }

        return array_map(
            static fn($nilai) => bulatkan($nilai, 1),
            $total
        );
    }
     /* array Tipe data kumpulan nilai */

    /*
    Takaran nasi: kelipatan 50 g di antara 50–250 g yang membuat
    energi lauk + nasi paling dekat dengan target waktu makan.
    Bila dua takaran sama dekat, dipilih yang lebih kecil.
    */

    function takaranNasi(   /* Memilih berat nasi yang energinya paling mendekati target */
        float $targetEnergi,          /* ($targetEnergi) Target energi waktu makan anak */
        float $energiLauk,            /* ($energiLauk) Energi dari satu porsi lauk */
        float $energiNasiPer100Gram   /* ($energiNasiPer100Gram) Energi nasi setiap 100 gram */
    ): int {

        $terbaik = NASI_MINIMAL_GRAM;
        $selisihTerbaik = null;

        for (
            $gram = NASI_MINIMAL_GRAM;      /* ($gram) Takaran nasi yang sedang dicoba */
            $gram <= NASI_MAKSIMAL_GRAM;
            $gram += NASI_KELIPATAN_GRAM
        ) {
            $total = $energiLauk + $gram / 100 * $energiNasiPer100Gram;  /* ($total) Energi lauk + nasi */
            $selisih = round(abs($total - $targetEnergi), 4);  /* ($selisih) Selisih absolut terhadap target energi */

            if ($selisihTerbaik === null || $selisih < $selisihTerbaik) {
                $terbaik = $gram;   /* ($terbaik) Takaran nasi yang paling mendekati target */
                $selisihTerbaik = $selisih;  /* ($selisihTerbaik) Selisih terkecil yang ditemukan */
            }
        }

        return $terbaik;
    }

    /*
    Skor kesesuaian = selisih energi ÷ target energi
                      + selisih protein ÷ target protein.
    Makin kecil skor, makin sesuai menu dengan kebutuhan anak.
    */

    function skorMenu(        /* (skorMenu) Menghitung skor kesesuaian energi dan protein */
        float $totalEnergi,     /* Energi total lauk dan nasi */
        float $totalProtein,    /* Protein total lauk dan nasi */
        float $targetEnergi,    /* Target energi anak */
        float $targetProtein    /* Target protein anak */
    ): float {

        if ($targetEnergi <= 0 || $targetProtein <= 0) {
            throw new InvalidArgumentException(
                'Target energi dan protein harus lebih dari 0.'
            );
        }

        return bulatkan(
            abs($totalEnergi - $targetEnergi) / $targetEnergi   /* ABS Mengambil nilai absolut */
            + abs($totalProtein - $targetProtein) / $targetProtein, 
            4
        );
    }

    /*
    Menyusun 1 porsi makan anak: lauk (tetap) + nasi (ditakar),
    lalu membandingkannya dengan target waktu makan.
    */

    function susunPorsiMenu(    /* (susunPorsiMenu) Menggabungkan gizi lauk dan nasi, lalu menghitung skor */
        array $menu,
        array $target,
        array $nasi
    ): array {

        $targetEnergi = (float) $target['target_energi'];
        $targetProtein = (float) $target['target_protein'];

        $nasiGram = takaranNasi(
            $targetEnergi,
            (float) ($menu['energi'] ?? 0),
            (float) $nasi['energi']
        );

        $porsi = ['nasi_gram' => $nasiGram];

        foreach (ZAT_GIZI_MENU as $zat) {
            $dariNasi = $nasiGram / 100 * (float) ($nasi[$zat] ?? 0);

            $porsi['nasi_' . $zat] = bulatkan($dariNasi, 2);
            $porsi['total_' . $zat] = bulatkan(
                (float) ($menu[$zat] ?? 0) + $dariNasi,
                2
            );
        }

        $porsi['selisih_energi'] = bulatkan(
            $porsi['total_energi'] - $targetEnergi,
            2
        );

        $porsi['selisih_protein'] = bulatkan(
            $porsi['total_protein'] - $targetProtein,
            2
        );

        $porsi['skor'] = skorMenu(
            $porsi['total_energi'],
            $porsi['total_protein'],
            $targetEnergi,
            $targetProtein
        );

        return $porsi;
    }


    /*
    Kandungan gizi nasi per 100 gram (TKPI).
    */

    function ambilBahanNasi(mysqli $conn): array  /* (ambilBahanNasi) Mengambil nilai gizi nasi dari database */
    {
        $hasil = mysqli_query(
            $conn,
            "SELECT energi, protein, lemak, karbohidrat, serat
            FROM bahan
            WHERE kode_tkpi = 'AP001'
            LIMIT 1"    /* membatasi hasil query menjadi maksimal satu baris */
        );

        $nasi = $hasil ? mysqli_fetch_assoc($hasil) : null;

        if (!$nasi) {
            throw new RuntimeException(
                'Data nasi (TKPI AP001) belum ada di tabel bahan.'
            );
        }

        return array_map('floatval', $nasi);
    }

    /*
    Bahan penyusun 1 porsi lauk sebuah menu beserta nilai TKPI-nya.
    */

    function ambilKomposisiMenu(mysqli $conn, int $idMenu): array
    /* Mengambil bahan penyusun satu menu dari database */
    {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                b.kode_tkpi,
                b.nama_bahan,
                mb.berat_gram,
                mb.urt,
                mb.peran,
                b.energi,
                b.protein,
                b.lemak,
                b.karbohidrat,
                b.serat
            FROM menu_bahan mb
            INNER JOIN bahan b ON b.id_bahan = mb.id_bahan
            WHERE mb.id_menu = ? /* mengambil komposisi bahan dari menu tertentu. Tanda tanya merupakan parameter yang nilainya diisi melalui prepared statement */
            ORDER BY FIELD(mb.peran, 'lauk', 'sayur', 'santan', 'minyak'),
                    b.nama_bahan"
        );

        if (!$stmt) {
            throw new RuntimeException(
                'Komposisi menu gagal disiapkan: ' . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param($stmt, 'i', $idMenu);  /* mengikat nilai ID menu pada parameter query yang sudah disiapkan */
        mysqli_stmt_execute($stmt);

        $komposisi = mysqli_fetch_all(
            mysqli_stmt_get_result($stmt),
            MYSQLI_ASSOC
        );

        mysqli_stmt_close($stmt);

        return $komposisi;
    }
