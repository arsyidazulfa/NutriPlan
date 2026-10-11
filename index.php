<?php

define('ROOT', __DIR__);

session_start();

require_once ROOT . '/config/koneksi.php';
require_once ROOT . '/helpers/auth.php';
require_once ROOT . '/helpers/akg.php';

$page = $_GET['page'] ?? 'beranda';

/* HALAMAN ORANG TUA */

$ortuPages = [
    'profil_ibu',
    'data_anak',
    'menu',
    'menu_detail',
    'edukasi',
    'edukasi_detail',
    'sumber_protein',
    'planning_mingguan',
    'camilan_sehat',
    'cara_mengolah',
    'download_jadwal_pdf',
    'camilan_detail',
    'camilan_detail'
];

/* PROTEKSI LOGIN */

if (in_array($page, $ortuPages, true)) {
    requireLogin();
    requireOrtu();
}


/* FILE HALAMAN */

if ($page === 'download_jadwal_pdf') {

    $file = ROOT . '/pages/download_jadwal.php';

} else {

    $file = ROOT . "/pages/{$page}.php";

}


/* TAMPILKAN HALAMAN */

if (file_exists($file)) {
    include $file;
    exit();
}

/* 404 */

http_response_code(404);

include ROOT . '/pages/404.php';