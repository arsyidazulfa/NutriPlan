<?php
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /index.php?page=edukasi');
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT * FROM edukasi WHERE id_edukasi = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$artikel = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$artikel) {
    header('Location: /index.php?page=edukasi');
    exit();
}

// Fetch 3 related articles (latest, excluding current)
$stmtR = mysqli_prepare($conn,
    "SELECT id_edukasi, judul, isi, gambar FROM edukasi WHERE id_edukasi != ? ORDER BY created_at DESC LIMIT 3");
mysqli_stmt_bind_param($stmtR, 'i', $id);
mysqli_stmt_execute($stmtR);
$related = mysqli_fetch_all(mysqli_stmt_get_result($stmtR), MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($artikel['judul']) ?> — NutriPlan</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php include ROOT . '/includes/navbar.php'; ?>

<div class="edukasi-detail-wrap">
    <div class="edukasi-detail-container">

        <a href="/index.php?page=edukasi" class="edukasi-back">← Kembali ke Edukasi Gizi</a>

        <article class="edukasi-article">
            <div class="edukasi-article-meta">
                <span class="edukasi-date"><?= date('d F Y', strtotime($artikel['created_at'])) ?></span>
            </div>

            <h1 class="edukasi-article-title"><?= htmlspecialchars($artikel['judul']) ?></h1>

            <?php if ($artikel['gambar'] && file_exists(ROOT . '/assets/images/' . $artikel['gambar'])): ?>
            <img class="edukasi-article-img"
                 src="/assets/images/<?= htmlspecialchars($artikel['gambar']) ?>"
                 alt="<?= htmlspecialchars($artikel['judul']) ?>">
            <?php endif; ?>

            <div class="edukasi-prose">
                <?= nl2br(htmlspecialchars($artikel['isi'])) ?>
            </div>
        </article>

        <?php if ($related): ?>
        <section class="edukasi-related">
            <h3 class="edukasi-related-title">Artikel Lainnya</h3>
            <div class="edukasi-related-grid">
                <?php foreach ($related as $r): ?>
                <a href="/index.php?page=edukasi_detail&id=<?= $r['id_edukasi'] ?>" class="news-card" style="text-decoration:none; color:inherit;">
                    <?php if ($r['gambar'] && file_exists(ROOT . '/assets/images/' . $r['gambar'])): ?>
                        <img class="news-card-img" src="/assets/images/<?= htmlspecialchars($r['gambar']) ?>" alt="<?= htmlspecialchars($r['judul']) ?>">
                    <?php else: ?>
                        <div class="news-card-img-placeholder">📰</div>
                    <?php endif; ?>
                    <div class="news-card-body">
                        <div class="news-card-title"><?= htmlspecialchars($r['judul']) ?></div>
                        <div class="news-card-excerpt"><?= htmlspecialchars(strip_tags(substr($r['isi'], 0, 120))) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </div>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
<script src="/assets/js/script.js"></script>
</body>
</html>
