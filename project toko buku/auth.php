<?php
// auth.php - sesi, proteksi halaman (user/admin), dan token CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . csrfToken() . '">';
}
function cekCsrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('Token keamanan tidak valid. Silakan muat ulang halaman.');
    }
}

function userLogin(): ?array
{
    return $_SESSION['user'] ?? null;
}
/** Wajib login (user atau admin), jika belum diarahkan ke login.php */
function wajibLogin(): array
{
    $u = userLogin();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}
/** Wajib admin, user biasa ditolak */
function wajibAdmin(): array
{
    $u = wajibLogin();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        die('Akses ditolak: halaman ini khusus admin. <a href="index.php">Kembali</a>');
    }
    return $u;
}
