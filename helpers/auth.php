<?php

function requireLogin(): void
{
    if (!isset($_SESSION['id_user'])) {
        header('Location: /index.php?page=login');
        exit();
    }
}


function requireAdmin(): void
{
    if (
        !isset($_SESSION['id_user'])
        || ($_SESSION['role'] ?? '') !== 'admin'
    ) {
        header('Location: /index.php?page=login');
        exit();
    }
}


function requireOrtu(): void
{
    if (
        !isset($_SESSION['id_user'])
        || ($_SESSION['role'] ?? '') !== 'ortu'
    ) {
        header('Location: /index.php?page=login');
        exit();
    }
}


function isLoggedIn(): bool
{
    return isset($_SESSION['id_user']);
}


function isAdmin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}