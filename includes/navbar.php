<?php

$current = $_GET['page'] ?? 'beranda';

?>

<nav class="navbar">

    <a href="/index.php?page=beranda" class="navbar-logo">
        <img
            src="/assets/images/nutriplan-logo.png"
            alt="NutriPlan"
        >
    </a>


    <div class="navbar-menu">

        <a
            href="/index.php?page=beranda"
            class="<?= $current === 'beranda' ? 'active' : ''; ?>"
        >
            Beranda
        </a>

        <a
            href="/index.php?page=menu"
            class="<?= in_array(
                $current,
                ['menu', 'menu_detail'],
                true
            ) ? 'active' : ''; ?>"
        >
            Menu Sehat
        </a>

        <a
            href="/index.php?page=edukasi"
            class="<?= in_array(
                $current,
                ['edukasi', 'edukasi_detail'],
                true
            ) ? 'active' : ''; ?>"
        >
            Edukasi Gizi
        </a>

    </div>


    <div class="navbar-right">

        <?php if (isLoggedIn()): ?>

            <?php
            $profilLink = isAdmin()
                ? '/admin/'
                : '/index.php?page=profil_ibu';
            ?>

            <a
                href="<?= $profilLink; ?>"
                class="navbar-user-btn"
            >
                <?= htmlspecialchars(
                    $_SESSION['nama'] ?? 'Pengguna'
                ); ?>
            </a>

        <?php else: ?>

            <a
                href="/index.php?page=login"
                class="btn-navbar"
            >
                Masuk
            </a>

        <?php endif; ?>

    </div>

</nav>