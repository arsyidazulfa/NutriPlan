<?php
// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /index.php?page=menu');
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirm  = $_POST['konfirm'] ?? '';

    if (!$nama)                          $errors[] = 'Nama lengkap wajib diisi.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';
    if (strlen($password) < 8)           $errors[] = 'Password minimal 8 karakter.';
    if ($password !== $konfirm)          $errors[] = 'Konfirmasi password tidak cocok.';

    if (!$errors) {
        $stmt = mysqli_prepare($conn, "SELECT id_user FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = 'Email sudah terdaftar.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins  = mysqli_prepare($conn, "INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, 'ortu')");
            mysqli_stmt_bind_param($ins, 'sss', $nama, $email, $hash);
            if (mysqli_stmt_execute($ins)) {
                $success = 'Akun berhasil dibuat! Silahkan masuk.';
            } else {
                $errors[] = 'Terjadi kesalahan. Coba lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar — NutriPlan</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php include ROOT . '/includes/navbar.php'; ?>

<div class="auth-page">
    <div class="auth-card">
        <h2>Daftar Akun</h2>

        <?php foreach ($errors as $e): ?>
            <div class="flash flash-error"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <?php if ($success): ?>
            <div class="flash flash-success"><?= htmlspecialchars($success) ?></div>
            <p style="text-align:center; margin-top:8px;">
                <a href="/index.php?page=login" class="btn-primary" style="font-size:16px; padding:12px 32px;">Masuk Sekarang</a>
            </p>
        <?php else: ?>

        <form method="POST" action="">
            <div class="form-field">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama"
                       value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
                       placeholder="Nama ibu / wali" required>
            </div>

            <div class="form-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="contoh@email.com" required>
            </div>

            <div class="form-field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password"
                           placeholder="Minimal 8 karakter" required>
                    <button type="button" class="toggle-pass" data-target="password" aria-label="Tampilkan password"></button>
                </div>
            </div>

            <div class="form-field">
                <label for="konfirm">Konfirmasi Password</label>
                <div class="input-wrap">
                    <input type="password" id="konfirm" name="konfirm"
                           placeholder="Ulangi password" required>
                    <button type="button" class="toggle-pass" data-target="konfirm" aria-label="Tampilkan password"></button>
                </div>
            </div>

            <div class="auth-actions">
                <button type="submit" class="btn-primary" style="width:100%; font-size:18px; padding:16px;">Daftar</button>
            </div>
        </form>

        <?php endif; ?>

        <p class="auth-footer" style="text-align:center; margin-top:24px;">
            Sudah punya akun? <a href="/index.php?page=login">Masuk di sini</a>
        </p>
    </div>
</div>

<script src="/assets/js/script.js"></script>
</body>
</html>
