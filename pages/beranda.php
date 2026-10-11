<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        NutriPlan — Rencanakan Menu Sehat Anak
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >
</head>

<body>

<?php include ROOT . '/includes/navbar.php'; ?>


<!-- HERO -->
<section class="hero">

    <div class="hero-text">

        <h1>
            Rencanakan Menu<br>
            Sehat untuk Si Kecil
        </h1>

        <p>
            NutriPlan membantu Anda menyusun menu makan harian
            anak usia 6–12 tahun sesuai kebutuhan gizi,
            alergi, dan pilihan sumber protein.
        </p>

        <?php if (!isLoggedIn()): ?>

            <a
                href="/index.php?page=registrasi"
                class="btn-primary"
                style="align-self:flex-start; font-size:18px; padding:16px 40px;"
            >
                Mulai Sekarang
            </a>

        <?php else: ?>

            <a
                href="/index.php?page=menu"
                class="btn-primary"
                style="align-self:flex-start; font-size:18px; padding:16px 40px;"
            >
                Lihat Menu
            </a>

        <?php endif; ?>

    </div>


    <div class="hero-image">

        <img
            src="/assets/images/nutriplan-hero-image.jpg"
            alt="Menu sehat untuk anak"
        >

    </div>

</section>



<!-- ABOUT / FITUR -->
<section class="about-section">

    <svg
        class="about-wave"
        viewBox="0 0 1440 100"
        xmlns="http://www.w3.org/2000/svg"
        preserveAspectRatio="none"
    >
        <path
            d="M0,50 Q180,10 360,50 T720,50 T1080,50 T1440,50 L1440,100 L0,100 Z"
            fill="#0d4715"
        />
    </svg>


    <div class="about-content">

        <div class="about-text">

            <p>
                NutriPlan adalah platform perencanaan menu makan
                sehat anak berbasis angka kecukupan gizi (AKG)
                Kemenkes 2023.
            </p>

            <p>
                NutriPlan membantu orang tua menyajikan makanan
                bergizi dengan lebih mudah dan sesuai kebutuhan anak.
            </p>

        </div>


        <div class="feature-list">

            <div class="feature-item">
                <div class="feature-check">✓</div>
                <p>
                    Rekomendasi menu berdasarkan kebutuhan gizi anak
                </p>
            </div>

            <div class="feature-item">
                <div class="feature-check">✓</div>
                <p>
                    Filter alergi makanan otomatis
                </p>
            </div>

            <div class="feature-item">
                <div class="feature-check">✓</div>
                <p>
                    Pilihan menu berdasarkan sumber protein
                </p>
            </div>

            <div class="feature-item">
                <div class="feature-check">✓</div>
                <p>
                    Artikel dan informasi edukasi gizi
                </p>
            </div>

        </div>

    </div>


    <svg
        class="about-wave"
        viewBox="0 0 1440 100"
        xmlns="http://www.w3.org/2000/svg"
        preserveAspectRatio="none"
    >
        <path
            d="M0,50 Q180,90 360,50 T720,50 T1080,50 T1440,50 L1440,0 L0,0 Z"
            fill="#0d4715"
        />
    </svg>

</section>



<?php

$menus = [];

$result = mysqli_query(
    $conn,
    "SELECT
        id_menu,
        nama_menu,
        gambar
     FROM menu
     ORDER BY id_menu ASC
     LIMIT 6"
);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $menus[] = $row;
    }
}

?>


<!-- MENU SECTION -->
<section class="section">

    <h2 class="section-title">
        Pilihan Menu Bergizi
    </h2>

    <p class="section-subtitle">
        Temukan berbagai pilihan menu sehat untuk si kecil
    </p>


    <?php if ($menus): ?>

        <div class="menu-grid">

            <?php foreach ($menus as $menu): ?>

                <a
                    href="/index.php?page=menu_detail&id=<?= (int) $menu['id_menu']; ?>"
                    class="menu-card"
                    style="text-decoration:none; color:inherit;"
                >

                    <?php if (
                        !empty($menu['gambar'])
                        && file_exists(
                            ROOT . '/assets/images/' . $menu['gambar']
                        )
                    ): ?>

                        <img
                            src="/assets/images/<?= htmlspecialchars($menu['gambar']); ?>"
                            alt="<?= htmlspecialchars($menu['nama_menu']); ?>"
                        >

                    <?php else: ?>

                        <div class="menu-card-no-img">
                            🍱
                        </div>

                    <?php endif; ?>


                    <div class="menu-card-label">
                        <?= htmlspecialchars($menu['nama_menu']); ?>
                    </div>

                </a>

            <?php endforeach; ?>

        </div>


    <?php else: ?>

        <p
            style="text-align:center; color:#888; padding:40px 0;"
        >
            Belum ada menu tersedia.
        </p>

    <?php endif; ?>


    <div
        style="text-align:center; margin-top:40px;"
    >

        <a
            href="/index.php?page=menu"
            class="btn-primary"
            style="font-size:18px; padding:14px 40px;"
        >
            Lihat Semua Menu
        </a>

    </div>

</section>



<?php

$edukasi = [];

$result = mysqli_query(
    $conn,
    "SELECT
        id_edukasi,
        judul,
        isi,
        gambar
     FROM edukasi
     ORDER BY created_at DESC
     LIMIT 4"
);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $edukasi[] = $row;
    }
}

?>


<!-- EDUKASI -->
<section
    class="section"
    style="background:#f9f9f9;"
>

    <h2 class="section-title">
        Artikel & Tips Gizi
    </h2>

    <p class="section-subtitle">
        Informasi gizi untuk membantu orang tua
        mendukung tumbuh kembang anak
    </p>


    <?php if ($edukasi): ?>

        <div class="news-grid">

            <?php foreach ($edukasi as $item): ?>

                <a
                    href="/index.php?page=edukasi_detail&id=<?= (int) $item['id_edukasi']; ?>"
                    class="news-card"
                    style="text-decoration:none; color:inherit;"
                >

                    <?php if (
                        !empty($item['gambar'])
                        && file_exists(
                            ROOT . '/assets/images/' . $item['gambar']
                        )
                    ): ?>

                        <img
                            class="news-card-img"
                            src="/assets/images/<?= htmlspecialchars($item['gambar']); ?>"
                            alt="<?= htmlspecialchars($item['judul']); ?>"
                        >

                    <?php else: ?>

                        <div class="news-card-img-placeholder">
                            📰
                        </div>

                    <?php endif; ?>


                    <div class="news-card-body">

                        <div class="news-card-title">
                            <?= htmlspecialchars($item['judul']); ?>
                        </div>

                        <div class="news-card-excerpt">

                            <?= htmlspecialchars(
                                strip_tags(
                                    substr(
                                        $item['isi'],
                                        0,
                                        200
                                    )
                                )
                            ); ?>

                        </div>

                    </div>

                </a>

            <?php endforeach; ?>

        </div>


    <?php else: ?>

        <p
            style="text-align:center; color:#888; padding:40px 0;"
        >
            Belum ada artikel tersedia.
        </p>

    <?php endif; ?>


    <div
        style="text-align:center; margin-top:40px;"
    >

        <a
            href="/index.php?page=edukasi"
            class="btn-primary"
            style="font-size:18px; padding:14px 40px;"
        >
            Lihat Semua Artikel
        </a>

    </div>

</section>


<?php include ROOT . '/includes/footer.php'; ?>


<script src="/assets/js/script.js"></script>

</body>
</html>