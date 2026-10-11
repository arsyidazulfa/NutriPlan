<?php
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['id_user']);
mysqli_stmt_execute($stmt);
$admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'profil') {
        $nama  = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (!$nama || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Nama dan email valid wajib diisi.';
        } else {
            $cek = mysqli_prepare($conn, "SELECT id_user FROM users WHERE email = ? AND id_user != ?");
            mysqli_stmt_bind_param($cek, 'si', $email, $_SESSION['id_user']);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);
            if (mysqli_stmt_num_rows($cek) > 0) {
                $error = 'Email sudah digunakan akun lain.';
            } else {
                $upd = mysqli_prepare($conn, "UPDATE users SET nama = ?, email = ? WHERE id_user = ?");
                mysqli_stmt_bind_param($upd, 'ssi', $nama, $email, $_SESSION['id_user']);
                mysqli_stmt_execute($upd);
                $_SESSION['nama'] = $nama;
                $admin['nama']  = $nama;
                $admin['email'] = $email;
                $success = 'Profil berhasil diperbarui.';
            }
        }

    } elseif ($aksi === 'password') {
        $lama    = $_POST['password_lama'] ?? '';
        $baru    = $_POST['password_baru'] ?? '';
        $konfirm = $_POST['password_konfirm'] ?? '';
        if (!password_verify($lama, $admin['password'])) {
            $error = 'Password lama tidak sesuai.';
        } elseif (strlen($baru) < 8) {
            $error = 'Password baru minimal 8 karakter.';
        } elseif ($baru !== $konfirm) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            $hash = password_hash($baru, PASSWORD_BCRYPT);
            $upd  = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id_user = ?");
            mysqli_stmt_bind_param($upd, 'si', $hash, $_SESSION['id_user']);
            mysqli_stmt_execute($upd);
            $success = 'Password berhasil diubah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Admin — NutriPlan Admin</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">

<?php include ROOT . '/includes/admin_sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <h1>Profil Admin</h1>
        <span class="admin-topbar-user">👤 <?= htmlspecialchars($_SESSION['nama']) ?></span>
    </div>

    <div class="admin-page">
        <?php if ($error): ?>
        <div class="flash flash-error" style="margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="flash flash-success" style="margin-bottom:20px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="admin-form-card" style="margin-bottom:32px;">
            <h3 style="font-size:18px; font-weight:600; margin-bottom:24px;">Data Profil</h3>
            <form method="POST">
                <input type="hidden" name="aksi" value="profil">
                <div class="admin-form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" value="<?= htmlspecialchars($admin['nama']) ?>" required>
                </div>
                <div class="admin-form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
                </div>
                <div class="admin-form-actions">
                    <button type="submit" class="btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>

        <div class="admin-form-card">
            <h3 style="font-size:16px; font-weight:600; margin-bottom:6px;">Ganti Password</h3>
            <p style="font-size:13px; color:#888; margin-bottom:22px;">Pastikan password baru kuat dan mudah Anda ingat.</p>
            <form method="POST">
                <input type="hidden" name="aksi" value="password">
                <div class="admin-form-group">
                    <label>Password Lama</label>
                    <div class="input-wrap">
                        <input type="password" name="password_lama" placeholder="Masukkan password lama" required>
                        <button type="button" class="toggle-pass" aria-label="Tampilkan password"></button>
                    </div>
                </div>
                <div class="admin-form-group">
                    <label>Password Baru</label>
                    <div class="input-wrap">
                        <input type="password" name="password_baru" placeholder="Minimal 8 karakter" required>
                        <button type="button" class="toggle-pass" aria-label="Tampilkan password"></button>
                    </div>
                    <div class="admin-form-hint">Gunakan minimal 8 karakter dengan kombinasi huruf dan angka.</div>
                </div>
                <div class="admin-form-group">
                    <label>Konfirmasi Password Baru</label>
                    <div class="input-wrap">
                        <input type="password" name="password_konfirm" placeholder="Ulangi password baru" required>
                        <button type="button" class="toggle-pass" aria-label="Tampilkan password"></button>
                    </div>
                </div>
                <div class="admin-form-actions">
                    <button type="submit" class="btn-primary">Ubah Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/assets/js/script.js"></script>
</body>
</html>
