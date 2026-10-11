<?php

/** @var mysqli $conn */

$idUser = (int) ($_SESSION['id_user'] ?? 0);

$stmt = mysqli_prepare(
    $conn,
    'SELECT * FROM users WHERE id_user = ?'
);

mysqli_stmt_bind_param($stmt, 'i', $idUser);
mysqli_stmt_execute($stmt);

$user = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
) ?: [];

mysqli_stmt_close($stmt);

$error = '';
$success = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = $_POST['aksi'] ?? '';


    /* FOTO PROFIL */
    if ($aksi === 'foto') {

        if (
            isset($_FILES['foto']) &&
            $_FILES['foto']['error'] === UPLOAD_ERR_OK
        ) {

            $ext = strtolower(
                pathinfo(
                    $_FILES['foto']['name'],
                    PATHINFO_EXTENSION
                )
            );

            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed, true)) {

                $error = 'Format gambar tidak didukung.';

            } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {

                $error = 'Ukuran gambar maksimal 2MB.';

            } else {

                $filename = 'foto_' . $idUser . '.' . $ext;

                $berhasil = move_uploaded_file(
                    $_FILES['foto']['tmp_name'],
                    ROOT . '/uploads/' . $filename
                );

                if ($berhasil) {

                    $upd = mysqli_prepare(
                        $conn,
                        'UPDATE users SET foto = ? WHERE id_user = ?'
                    );

                    mysqli_stmt_bind_param(
                        $upd,
                        'si',
                        $filename,
                        $idUser
                    );

                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);

                    $user['foto'] = $filename;
                    $success = 'Foto profil berhasil diperbarui.';

                } else {
                    $error = 'Foto gagal disimpan.';
                }
            }
        }


    /* EDIT PROFIL */
    } elseif ($aksi === 'profil') {

        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (
            $nama === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {

            $error = 'Nama dan email valid wajib diisi.';

        } else {

            $cek = mysqli_prepare(
                $conn,
                'SELECT id_user
                 FROM users
                 WHERE email = ?
                 AND id_user != ?'
            );

            mysqli_stmt_bind_param(
                $cek,
                'si',
                $email,
                $idUser
            );

            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);

            if (mysqli_stmt_num_rows($cek) > 0) {

                $error = 'Email sudah digunakan akun lain.';

            } else {

                $upd = mysqli_prepare(
                    $conn,
                    'UPDATE users
                     SET nama = ?, email = ?
                     WHERE id_user = ?'
                );

                mysqli_stmt_bind_param(
                    $upd,
                    'ssi',
                    $nama,
                    $email,
                    $idUser
                );

                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);

                $_SESSION['nama'] = $nama;

                $user['nama'] = $nama;
                $user['email'] = $email;

                $success = 'Profil berhasil diperbarui.';
            }

            mysqli_stmt_close($cek);
        }


    /* GANTI PASSWORD */
    } elseif ($aksi === 'password') {

        $lama = $_POST['password_lama'] ?? '';
        $baru = $_POST['password_baru'] ?? '';
        $konfirm = $_POST['password_konfirm'] ?? '';

        if (!password_verify($lama, $user['password'] ?? '')) {

            $error = 'Password lama tidak sesuai.';

        } elseif (strlen($baru) < 8) {

            $error = 'Password baru minimal 8 karakter.';

        } elseif ($baru !== $konfirm) {

            $error = 'Konfirmasi password tidak cocok.';

        } else {

            $hash = password_hash(
                $baru,
                PASSWORD_DEFAULT
            );

            $upd = mysqli_prepare(
                $conn,
                'UPDATE users
                 SET password = ?
                 WHERE id_user = ?'
            );

            mysqli_stmt_bind_param(
                $upd,
                'si',
                $hash,
                $idUser
            );

            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            $success = 'Password berhasil diubah.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Profil Saya — NutriPlan</title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="/assets/css/dashboard.css"
    >

</head>

<body>

<?php include ROOT . '/includes/navbar.php'; ?>

<div class="dashboard-wrap">

    <?php include ROOT . '/includes/sidebar.php'; ?>

    <main class="dashboard-content">

        <h2>Profil Saya</h2>


        <?php if ($error): ?>

            <div class="flash flash-error">
                <?= htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>


        <?php if ($success): ?>

            <div class="flash flash-success">
                <?= htmlspecialchars($success); ?>
            </div>

        <?php endif; ?>


        <!-- PROFIL -->
        <form
            method="POST"
            class="dash-form"
            style="margin-bottom:48px;"
        >

            <input
                type="hidden"
                name="aksi"
                value="profil"
            >

            <div class="dash-form-group">

                <label>Nama Lengkap</label>

                <input
                    type="text"
                    name="nama"
                    value="<?= htmlspecialchars($user['nama'] ?? ''); ?>"
                    required
                >

            </div>

            <div class="dash-form-group">

                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    value="<?= htmlspecialchars($user['email'] ?? ''); ?>"
                    required
                >

            </div>

            <button
                type="submit"
                class="btn-primary"
            >
                Simpan Perubahan
            </button>

        </form>


        <hr
            style="
                border:none;
                border-top:1px solid #e0e0e0;
                margin-bottom:40px;
            "
        >


        <!-- PASSWORD -->
        <h3
            style="
                font-size:22px;
                font-weight:600;
                margin-bottom:24px;
            "
        >
            Ganti Password
        </h3>

        <form
            method="POST"
            class="dash-form"
        >

            <input
                type="hidden"
                name="aksi"
                value="password"
            >

            <div class="dash-form-group">

                <label>Password Lama</label>

                <input
                    type="password"
                    name="password_lama"
                    placeholder="••••••••"
                    required
                >

            </div>

            <div class="dash-form-row">

                <div class="dash-form-group">

                    <label>Password Baru</label>

                    <input
                        type="password"
                        name="password_baru"
                        placeholder="Min. 8 karakter"
                        required
                    >

                </div>

                <div class="dash-form-group">

                    <label>Konfirmasi Password</label>

                    <input
                        type="password"
                        name="password_konfirm"
                        placeholder="Ulangi password baru"
                        required
                    >

                </div>

            </div>

            <button
                type="submit"
                class="btn-primary"
            >
                Ubah Password
            </button>

        </form>

    </main>

</div>

<script src="/assets/js/script.js"></script>

</body>
</html>