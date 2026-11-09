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
    <h2 class="section-title">Edukasi Gizi</h2>
    <p class="section-subtitle">Pengetahuan nutrisi terpercaya untuk tumbuh kembang anak yang optimal</p>

    <?php if ($edukasi): ?>
    <div class="katalog-grid">
        <?php foreach ($edukasi as $e): ?>
        <a href="/index.php?page=edukasi_detail&id=<?= $e['id_edukasi'] ?>" class="katalog-card" style="text-decoration:none; color:inherit;">
            <div class="katalog-image-wrap">
                <?php if ($e['gambar'] && file_exists(ROOT . '/assets/images/' . $e['gambar'])): ?>
                    <img class="katalog-image" src="/assets/images/<?= htmlspecialchars($e['gambar']) ?>" alt="<?= htmlspecialchars($e['judul']) ?>">
                <?php else: ?>
                    <div class="katalog-image-placeholder">📰</div>
                <?php endif; ?>
            </div>
            <div class="katalog-card-body">
                <div class="katalog-waktu">
                    <span><?= date('d M Y', strtotime($e['created_at'])) ?></span>
                </div>
                <h3 class="katalog-card-title"><?= htmlspecialchars($e['judul']) ?></h3>
                <p class="katalog-description"><?= htmlspecialchars(strip_tags(substr($e['isi'], 0, 160))) ?></p>
                <span class="katalog-detail-btn" style="margin-top:auto;">
                    <span>Baca Artikel</span>
                    <span>→</span>
                </span>
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