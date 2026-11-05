<?php

/**
 * Pengganti foto menu selama kolom menu.gambar masih kosong:
 * ikon dan nama jenis lauk, supaya orang tua bisa mengenali lauknya sekilas.
 * Bila menu sudah punya foto, halaman tetap memakai fotonya.
 */
function ikonMenuHtml(array $menu): string
{
    $jenis = strtolower(trim((string) ($menu['jenis_lauk'] ?? '')));

    [$ikon, $label] = [
        'ayam' => ['🍗', 'Ayam'],
        'sapi' => ['🥩', 'Daging Sapi'],
        'kambing' => ['🍖', 'Daging Kambing'],
        'ikan' => ['🐟', 'Ikan'],
        'udang' => ['🦐', 'Udang'],
        'telur' => ['🍳', 'Telur'],
        'tahu' => ['🌱', 'Tahu'],
        'tempe' => ['🌱', 'Tempe'],
    ][$jenis] ?? ['🍱', ''];

    if ($label === '') {
        return $ikon;
    }

    return $ikon . '<span class="ikon-jenis-label">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}
