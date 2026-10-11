<?php
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/index.php?page=edukasi_list'); exit(); }

$stmt = mysqli_prepare($conn, "SELECT * FROM edukasi WHERE id_edukasi = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$artikel = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$artikel) { header('Location: /admin/index.php?page=edukasi_list'); exit(); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul  = trim($_POST['judul'] ?? '');
    $isi    = trim($_POST['isi'] ?? '');
    $gambar = $artikel['gambar'];

    if (!$judul || !$isi) {
        $error = 'Judul dan isi artikel wajib diisi.';
    } else {
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $ext     = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            if (!in_array($ext, $allowed)) {
                $error = 'Format gambar tidak didukung.';
            } elseif ($_FILES['gambar']['size'] > 2 * 1024 * 1024) {
                $error = 'Ukuran gambar maksimal 2MB.';
            } else {
                $gambar = 'edukasi_' . time() . '_' . mt_rand(100,999) . '.' . $ext;
                move_uploaded_file($_FILES['gambar']['tmp_name'], ROOT . '/assets/images/' . $gambar);
            }
        }

        if (!$error) {
            $upd = mysqli_prepare($conn, "UPDATE edukasi SET judul=?, isi=?, gambar=? WHERE id_edukasi=?");
            mysqli_stmt_bind_param($upd, 'sssi', $judul, $isi, $gambar, $id);
            if (mysqli_stmt_execute($upd)) {
                $_SESSION['flash'] = "Artikel \"$judul\" berhasil diperbarui.";
                header('Location: /admin/index.php?page=edukasi_list');
                exit();
            } else {
                $error = 'Gagal menyimpan. Coba lagi.';
            }
        }
    }

    $artikel = array_merge($artikel, [
        'judul' => $_POST['judul'] ?? $artikel['judul'],
        'isi'   => $_POST['isi']   ?? $artikel['isi'],
    ]);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Artikel — NutriPlan Admin</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">

<?php include ROOT . '/includes/admin_sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <h1>Edit Artikel Edukasi</h1>
        <span class="admin-topbar-user">👤 <?= htmlspecialchars($_SESSION['nama']) ?></span>
    </div>

    <div class="admin-page">
        <?php if ($error): ?>
        <div class="flash flash-error" style="margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="admin-form-card">
            <form method="POST" enctype="multipart/form-data">

                <div class="admin-form-group">
                    <label>Judul Artikel *</label>
                    <input type="text" name="judul" value="<?= htmlspecialchars($artikel['judul']) ?>" required>
                </div>

                <div class="admin-form-group">
                    <label>Isi Artikel *</label>
                    <textarea name="isi" style="min-height:320px;" required><?= htmlspecialchars($artikel['isi']) ?></textarea>
                </div>

                <div class="admin-form-group">
                    <label>Gambar Cover <span style="font-weight:400; color:#888;">(kosongkan untuk tidak mengubah)</span></label>
                    <?php if ($artikel['gambar'] && file_exists(ROOT . '/assets/images/' . $artikel['gambar'])): ?>
                    <div style="margin-bottom:8px;">
                        <img src="/assets/images/<?= htmlspecialchars($artikel['gambar']) ?>"
                             style="width:160px; height:100px; object-fit:cover; border-radius:8px; border:1px solid #e0e0e0;" alt="">
                    </div>
                    <?php endif; ?>
                    <input type="file" name="gambar" accept="image/jpeg,image/png,image/webp"
                           onchange="previewImg(this, 'preview')">
                    <img id="preview" class="admin-upload-preview" alt="Preview">
                </div>

                <div class="admin-form-actions">
                    <a href="/admin/index.php?page=edukasi_list" class="btn-outline-primary">Batal</a>
                    <button type="submit" class="btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/assets/js/script.js"></script>
</body>
</html>
