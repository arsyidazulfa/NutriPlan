<?php

/** @var mysqli $conn */

$current = $_GET['page'] ?? '';
$idUser = (int) ($_SESSION['id_user'] ?? 0);

$foto = '';

if ($idUser > 0) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT foto FROM users WHERE id_user = ?'
    );

    mysqli_stmt_bind_param($stmt, 'i', $idUser);
    mysqli_stmt_execute($stmt);

    $dataUser = mysqli_fetch_assoc(
        mysqli_stmt_get_result($stmt)
    );

    $foto = $dataUser['foto'] ?? '';

    mysqli_stmt_close($stmt);
}

$fotoSrc = $foto !== ''
    && file_exists(ROOT . '/uploads/' . $foto)
        ? '/uploads/' . htmlspecialchars($foto)
        : '/assets/images/default-profile.svg';

?>

<aside class="sidebar">

    <div class="sidebar-top">

        <div class="sidebar-profile">

            <form
                method="POST"
                action="/index.php?page=profil_ibu"
                enctype="multipart/form-data"
                id="fotoForm"
            >
                <input
                    type="hidden"
                    name="aksi"
                    value="foto"
                >

                <label
                    for="uploadFoto"
                    style="cursor:pointer; display:block;"
                >
                    <img
                        src="<?= $fotoSrc; ?>"
                        alt="Foto Profil"
                        class="sidebar-profile-photo"
                        title="Klik untuk ganti foto"
                    >
                </label>

                <input
                    type="file"
                    name="foto"
                    id="uploadFoto"
                    accept="image/jpeg,image/png,image/webp"
                    onchange="document.getElementById('fotoForm').submit()"
                    style="display:none;"
                >
            </form>

            <p class="sidebar-profile-name">
                <?= htmlspecialchars($_SESSION['nama'] ?? 'Pengguna'); ?>
            </p>

        </div>


        <nav class="sidebar-nav">

            <a
                href="/index.php?page=profil_ibu"
                class="sidebar-nav-item <?= $current === 'profil_ibu' ? 'active' : ''; ?>"
            >
                <span class="nav-icon">👤</span>
                Profil Ibu
            </a>

            <a
                href="/index.php?page=data_anak"
                class="sidebar-nav-item <?= $current === 'data_anak' ? 'active' : ''; ?>"
            >
                <span class="nav-icon">👶</span>
                Data Anak
            </a>

            <a
                href="/index.php?page=sumber_protein"
                class="sidebar-nav-item <?= $current === 'sumber_protein' ? 'active' : ''; ?>"
            >
                <span class="nav-icon">🌱</span>
                Sumber Protein
            </a>

            <a
                href="/index.php?page=camilan_sehat"
                class="sidebar-nav-item <?= $current === 'camilan_sehat' ? 'active' : ''; ?>"
            >
                <span class="nav-icon">🍎</span>
                Camilan Sehat
            </a>

            <a
                href="/index.php?page=planning_mingguan"
                class="sidebar-nav-item <?= $current === 'planning_mingguan' ? 'active' : ''; ?>"
            >
                <span class="nav-icon">📅</span>
                Planning Mingguan
            </a>

            <a
                href="/index.php?page=cara_mengolah"
                class="sidebar-nav-item <?= $current === 'cara_mengolah' ? 'active' : ''; ?>"
            >
                <span class="nav-icon">🍳</span>
                Cara Mengolah
            </a>

        </nav>

    </div>


    <a href="/logout.php" class="sidebar-logout">
        <span>⎋</span>
        Keluar
    </a>

</aside>