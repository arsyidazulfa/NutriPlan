<?php
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$edukasi = mysqli_fetch_all(
    mysqli_query($conn, "SELECT id_edukasi, judul, gambar, created_at FROM edukasi ORDER BY created_at DESC"),
    MYSQLI_ASSOC
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Edukasi — NutriPlan Admin</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">

<?php include ROOT . '/includes/admin_sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <h1>Kelola Edukasi</h1>
        <span class="admin-topbar-user">👤 <?= htmlspecialchars($_SESSION['nama']) ?></span>
    </div>

    <div class="admin-page">
        <?php if ($flash): ?>
        <div class="flash flash-success" style="margin-bottom:20px;"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Daftar Artikel (<?= count($edukasi) ?>)</h3>
                <a href="/admin/index.php?page=edukasi_tambah" class="btn-primary" style="font-size:14px; padding:10px 20px;">+ Tambah Artikel</a>
            </div>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Gambar</th>
                        <th>Judul</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($edukasi as $i => $e): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <?php if ($e['gambar'] && file_exists(ROOT . '/assets/images/' . $e['gambar'])): ?>
                                <img src="/assets/images/<?= htmlspecialchars($e['gambar']) ?>" alt="">
                            <?php else: ?>
                                <span style="font-size:24px;">📰</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($e['judul']) ?></td>
                        <td style="white-space:nowrap;"><?= date('d M Y', strtotime($e['created_at'])) ?></td>
                        <td>
                            <div class="admin-table-actions">
                                <a href="/admin/index.php?page=edukasi_edit&id=<?= $e['id_edukasi'] ?>"
                                   class="btn-secondary" style="font-size:13px; padding:6px 14px;">Edit</a>
                                <form method="POST" action="/admin/index.php?page=edukasi_hapus"
                                      onsubmit="return confirm('Hapus artikel ini?')">
                                    <input type="hidden" name="id" value="<?= $e['id_edukasi'] ?>">
                                    <button type="submit" class="btn-danger" style="font-size:13px; padding:6px 14px;">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$edukasi): ?>
                    <tr><td colspan="5" style="text-align:center; color:#888; padding:40px;">Belum ada artikel.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="/assets/js/script.js"></script>
</body>
</html>
