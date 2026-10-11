<?php

$apage = $_GET['page'] ?? 'dashboard';

?>

<aside class="admin-sidebar">

    <div class="admin-sidebar-logo">
        <img
            src="/assets/images/nutriplan-logo.png"
            alt="NutriPlan"
            style="
                height:44px;
                width:auto;
                display:block;
                margin-bottom:6px;
            "
        >

        <small>Admin Panel</small>
    </div>


    <nav class="admin-nav">

        <a
            href="/admin/index.php?page=dashboard"
            class="<?= $apage === 'dashboard' ? 'active' : ''; ?>"
        >
            📊 Dashboard
        </a>


        <div class="admin-nav-divider"></div>


        <a
            href="/admin/index.php?page=edukasi_list"
            class="<?= in_array(
                $apage,
                ['edukasi_list', 'edukasi_tambah', 'edukasi_edit'],
                true
            ) ? 'active' : ''; ?>"
        >
            📰 Kelola Edukasi
        </a>


        <div class="admin-nav-divider"></div>


        <a
            href="/admin/index.php?page=profil"
            class="<?= $apage === 'profil' ? 'active' : ''; ?>"
        >
            👤 Profil Admin
        </a>

    </nav>


    <div class="admin-sidebar-bottom">
        <a href="/logout.php">
            ⎋ Keluar
        </a>
    </div>

</aside>