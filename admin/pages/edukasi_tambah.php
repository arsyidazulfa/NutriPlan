<?php
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul   = trim($_POST['judul'] ?? '');
    $isi     = trim($_POST['isi'] ?? '');
    $gambar  = '';

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
            $stmt = mysqli_prepare($conn, "INSERT INTO edukasi (judul, isi, gambar) VALUES (?,?,?)");
            mysqli_stmt_bind_param($stmt, 'sss', $judul, $isi, $gambar);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash'] = "Artikel \"$judul\" berhasil ditambahkan.";
                header('Location: /admin/index.php?page=edukasi_list');
                exit();
            } else {
                $error = 'Gagal menyimpan. Coba lagi.';
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
    <title>Tambah Artikel — NutriPlan Admin</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">

<?php include ROOT . '/includes/admin_sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <h1>Tambah Artikel Edukasi</h1>
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
                    <input type="text" name="judul"
                           value="<?= htmlspecialchars($_POST['judul'] ?? '') ?>"
                           placeholder="Judul artikel edukasi gizi" required>
                </div>

                <div class="admin-form-group">
                    <label>Isi Artikel *</label>
                    <textarea name="isi" style="min-height:320px;" placeholder="Tulis isi artikel di sini..." required><?= htmlspecialchars($_POST['isi'] ?? '') ?></textarea>
                    <div class="admin-form-hint">Gunakan Enter untuk paragraf baru.</div>
                </div>

                <div class="admin-form-group">
                    <label>Gambar Cover <span style="font-weight:400; color:#888;">(opsional, maks 2MB)</span></label>
                    <input type="file" name="gambar" accept="image/jpeg,image/png,image/webp"
                           onchange="previewImg(this, 'preview')">
                    <img id="preview" class="admin-upload-preview" alt="Preview">
                </div>

                <div class="admin-form-actions">
                    <a href="/admin/index.php?page=edukasi_list" class="btn-outline-primary">Batal</a>
                    <button type="submit" class="btn-primary">Simpan Artikel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/assets/js/script.js"></script>
</body>
</html>
