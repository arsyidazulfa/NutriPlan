<?php

if (isLoggedIn()) {
    if (($_SESSION['role'] ?? '') === 'admin') {
        header('Location: /admin/');
    } else {
        header('Location: /index.php?page=beranda');
    }

    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {

        $error = 'Email dan password wajib diisi.';

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_user, nama, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);

        $user = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmt)
        );

        mysqli_stmt_close($stmt);

        if (
            $user
            && password_verify($password, $user['password'])
        ) {

            session_regenerate_id(true);

            $_SESSION['id_user'] = (int) $user['id_user'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header('Location: /admin/');
            } else {
                header('Location: /index.php?page=beranda');
            }

            exit();

        } else {

            $error = 'Email atau password salah.';

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

    <title>Masuk — NutriPlan</title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

</head>

<body>

<?php include ROOT . '/includes/navbar.php'; ?>


<div class="auth-page">

    <div class="auth-card">

        <h2>Masuk</h2>


        <?php if ($error): ?>

            <div class="flash flash-error">
                <?= htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>


        <form method="POST">

            <div class="form-field">

                <label for="email">
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>"
                    placeholder="contoh@email.com"
                    required
                >

            </div>


            <div class="form-field">

                <label for="password">
                    Password
                </label>

                <div class="input-wrap">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-pass"
                        data-target="password"
                        aria-label="Tampilkan password"
                    ></button>

                </div>

            </div>


            <div class="auth-actions">

                <button
                    type="submit"
                    class="btn-primary"
                    style="width:100%; font-size:18px; padding:16px;"
                >
                    Masuk
                </button>

            </div>

        </form>


        <p
            class="auth-footer"
            style="text-align:center; margin-top:24px;"
        >
            Belum punya akun?

            <a href="/index.php?page=registrasi">
                Daftar sekarang
            </a>
        </p>

    </div>

</div>


<script src="/assets/js/script.js"></script>

</body>
</html>