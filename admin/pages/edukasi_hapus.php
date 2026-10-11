<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/index.php?page=edukasi_list');
    exit();
}

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT gambar FROM edukasi WHERE id_edukasi = $id"));
    if ($row && $row['gambar'] && file_exists(ROOT . '/assets/images/' . $row['gambar'])) {
        unlink(ROOT . '/assets/images/' . $row['gambar']);
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM edukasi WHERE id_edukasi = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $_SESSION['flash'] = 'Artikel berhasil dihapus.';
}

header('Location: /admin/index.php?page=edukasi_list');
exit();
