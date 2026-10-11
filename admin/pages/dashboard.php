<?php

$total_user = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) c FROM users WHERE role='ortu'"
    )
)['c'];

$total_edukasi = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) c FROM edukasi"
    )
)['c'];

$recent_edukasi = mysqli_fetch_all(
    mysqli_query(
        $conn,
        "SELECT id_edukasi, judul, created_at
         FROM edukasi
         ORDER BY created_at DESC
         LIMIT 5"
    ),
    MYSQLI_ASSOC
);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard — NutriPlan Admin</title>

    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>

<body class="admin-body">

<?php include ROOT . '/includes/admin_sidebar.php'; ?>

<div class="admin-main">

    <div class="admin-topbar">
        <h1>Dashboard</h1>

        <span class="admin-topbar-user">
            👤 <?= htmlspecialchars($_SESSION['nama'] ?? 'Admin'); ?>
        </span>
    </div>


    <div class="admin-page">

        <div class="stats-grid">

            <div class="stat-card">

                <div class="stat-icon green">
                    👥
                </div>

                <div class="stat-info">
                    <h3><?= $total_user; ?></h3>
                    <p>Pengguna Terdaftar</p>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon orange">
                    📰
                </div>

                <div class="stat-info">
                    <h3><?= $total_edukasi; ?></h3>
                    <p>Artikel Edukasi</p>
                </div>

            </div>

        </div>


        <div class="admin-card">

            <div class="admin-card-header">

                <h3>Artikel Terbaru</h3>

                <a
                    href="/admin/index.php?page=edukasi_list"
                    class="btn-secondary"
                >
                    Lihat Semua
                </a>

            </div>


            <table class="admin-table">

                <thead>
                    <tr>
                        <th>Judul</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($recent_edukasi as $e): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($e['judul']); ?>
                            </td>

                            <td>
                                <?= date(
                                    'd/m/Y',
                                    strtotime($e['created_at'])
                                ); ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>


                    <?php if (!$recent_edukasi): ?>

                        <tr>
                            <td colspan="2">
                                Belum ada artikel edukasi.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<script src="/assets/js/script.js"></script>

</body>
</html>