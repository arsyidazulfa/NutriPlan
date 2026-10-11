<?php
$edukasi = [];
$stmt = mysqli_prepare($conn, "SELECT id_edukasi, judul, isi, gambar, created_at FROM edukasi ORDER BY created_at DESC");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $edukasi[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edukasi Gizi — NutriPlan</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php include ROOT . '/includes/navbar.php'; ?>

<section class="section">
    <h2 class="section-title">Artikel & Tips Gizi</h2>
    <p class="section-subtitle">Pengetahuan nutrisi terpercaya untuk tumbuh kembang anak yang optimal</p>

    <?php if ($edukasi): ?>
    <div class="edukasi-grid">
        <?php foreach ($edukasi as $e): ?>
        <a href="/index.php?page=edukasi_detail&id=<?= $e['id_edukasi'] ?>" class="news-card" style="text-decoration:none; color:inherit;">
            <?php if ($e['gambar'] && file_exists(ROOT . '/assets/images/' . $e['gambar'])): ?>
                <img class="news-card-img" src="/assets/images/<?= htmlspecialchars($e['gambar']) ?>" alt="<?= htmlspecialchars($e['judul']) ?>">
            <?php else: ?>
                <div class="news-card-img-placeholder">📰</div>
            <?php endif; ?>
            <div class="news-card-body">
                <div class="news-card-title"><?= htmlspecialchars($e['judul']) ?></div>
                <div class="news-card-excerpt"><?= htmlspecialchars(strip_tags(substr($e['isi'], 0, 160))) ?></div>
                <div style="margin-top:10px; font-size:12px; color:#aaa;">
                    <?= date('d M Y', strtotime($e['created_at'])) ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="text-align:center; color:#888; padding:60px 0; font-size:18px;">Belum ada artikel tersedia.</p>
    <?php endif; ?>
</section>

<?php include ROOT . '/includes/footer.php'; ?>
<script src="/assets/js/script.js"></script>
</body>
</html>
