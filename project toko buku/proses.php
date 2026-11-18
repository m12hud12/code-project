<?php
// proses.php - menerima data POST dari browser, validasi, simpan ke MySQL, redirect ke dashboard
require_once 'auth.php';
require 'fungsi.php';
$user = wajibLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
cekCsrf();
if ($user['role'] === 'admin') { header('Location: admin.php'); exit; }

// Nama, email, dan usia diambil dari akun yang sedang login (bukan dari form)
$akun = $pdo->prepare('SELECT nama, email, usia FROM users WHERE id = ?');
$akun->execute([$user['id']]);
$akun = $akun->fetch();

$data = [
    'nama'          => $akun['nama'],
    'email'         => $akun['email'],
    'usia'          => (int) ($akun['usia'] ?? 0),   // diisi saat pendaftaran akun
    'genre_favorit' => $_POST['genre_favorit'] ?? '',
    'qty'           => $_POST['qty'] ?? [],
];

// Ambil harga buku dari DB (jangan percaya harga dari client)
$harga = [];
$judulBuku = []; $stokBuku = [];
foreach ($pdo->query('SELECT id, judul, harga, stok FROM buku') as $r) {
    $harga[$r['id']]     = (int) $r['harga'];
    $judulBuku[$r['id']] = $r['judul'];
    $stokBuku[$r['id']]  = (int) $r['stok'];
}

// Perulangan: hitung jumlah buku & subtotal
$totalBuku = 0; $subtotal = 0; $items = [];
foreach ($data['qty'] as $idBuku => $jml) {
    $jml = (int) $jml;
    if ($jml > 0 && isset($harga[$idBuku])) {
        $items[$idBuku] = $jml;
        $totalBuku += $jml;
        $subtotal  += $jml * $harga[$idBuku];
    }
}

$errors = validasiForm($data, $totalBuku);
foreach ($items as $idBuku => $jml) {   // validasi stok dan batas per judul
    if ($jml > 20) {
        $errors[] = 'Maksimal 20 eksemplar per judul: "' . $judulBuku[$idBuku] . '".';
    }
    if ($jml > $stokBuku[$idBuku]) {
        $errors[] = 'Stok "' . $judulBuku[$idBuku] . '" tersisa ' . $stokBuku[$idBuku] . ' (Anda memesan ' . $jml . ').';
    }
}
if ($errors) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old']    = $data;
    header('Location: index.php');
    exit;
}

$diskon = kategoriDiskon($totalBuku);
$total  = hitungTotalAkhir($subtotal, $diskon['persen']);

// Simpan ke database (transaksi + prepared statement)
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('INSERT INTO pembeli (user_id,nama,email,usia,genre_favorit,total_buku,subtotal,diskon_persen,total_bayar)
                         VALUES (?,?,?,?,?,?,?,?,?)');
    $st->execute([$user['id'], $data['nama'], $data['email'], (int) $data['usia'], $data['genre_favorit'],
                  $totalBuku, $subtotal, $diskon['persen'], $total]);
    $pembeliId = (int) $pdo->lastInsertId();

    $sd = $pdo->prepare('INSERT INTO detail_pembelian (pembeli_id,buku_id,jumlah) VALUES (?,?,?)');
    // Kurangi stok secara atomik: hanya berhasil bila stok masih cukup (aman dari pembelian bersamaan)
    $ss = $pdo->prepare('UPDATE buku SET stok = stok - ? WHERE id = ? AND stok >= ?');
    foreach ($items as $idBuku => $jml) {
        $sd->execute([$pembeliId, $idBuku, $jml]);
        $ss->execute([$jml, $idBuku, $jml]);
        if ($ss->rowCount() !== 1) {
            throw new RuntimeException('Stok "' . $judulBuku[$idBuku] . '" tidak mencukupi. Silakan kurangi jumlahnya.');
        }
    }
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['errors'] = [$e instanceof RuntimeException ? $e->getMessage() : 'Gagal menyimpan pesanan. Silakan coba lagi.'];
    $_SESSION['old'] = $data;
    header('Location: index.php');
    exit;
}

$_SESSION['sukses'] = 'Pesanan berhasil dibuat! Kategori diskon Anda: ' . $diskon['nama'] . ' (' . $diskon['persen'] . '%).';
header('Location: dashboard.php?id=' . $pembeliId);
exit;
