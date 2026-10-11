<?php

define('ROOT', dirname(__DIR__));

session_start();

require_once ROOT . '/config/koneksi.php';
require_once ROOT . '/helpers/auth.php';

requireAdmin();

$page = $_GET['page'] ?? 'dashboard';

$allowed = [
    'dashboard',
    'edukasi_list',
    'edukasi_tambah',
    'edukasi_edit',
    'edukasi_hapus',
    'profil'
];

$file = ROOT . "/admin/pages/{$page}.php";

if (in_array($page, $allowed, true) && file_exists($file)) {
    include $file;
    exit();
}

http_response_code(404);
echo 'Halaman tidak ditemukan.';