<footer class="footer">

    <div class="footer-inner">

        <div class="footer-brand">

            <img
                src="/assets/images/nutriplan-logo.png"
                alt="NutriPlan"
                style="height:40px; width:auto;"
            >

            <p>
                Membantu orang tua merencanakan menu sehat
                untuk anak usia 6–12 tahun dengan lebih mudah.
            </p>

        </div>


        <div class="footer-links">

            <div class="footer-col">

                <h4>Navigasi</h4>

                <a href="/index.php?page=beranda">
                    Beranda
                </a>

                <a href="/index.php?page=menu">
                    Menu Sehat
                </a>

                <a href="/index.php?page=edukasi">
                    Edukasi Gizi
                </a>

            </div>


            <div class="footer-col">

                <h4>Akun</h4>

                <?php if (isLoggedIn()): ?>

                    <a href="/index.php?page=profil_ibu">
                        Profil
                    </a>

                    <a href="/logout.php">
                        Keluar
                    </a>

                <?php else: ?>

                    <a href="/index.php?page=login">
                        Masuk
                    </a>

                    <a href="/index.php?page=registrasi">
                        Daftar
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <div class="footer-bottom">
        Copyright &copy; <?= date('Y'); ?> NutriPlan.
        All Rights Reserved.
    </div>

</footer>