<?php
// pesanan.php - pelanggan membatalkan pesanan (salah pesan) atau mengajukan retur (barang tidak sesuai)
require_once 'auth.php';
require 'fungsi.php';
$user = wajibLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}
cekCsrf();
if ($user['role'] === 'admin') {
    header('Location: admin.php');
    exit;
}

$id      = (int) ($_POST['id'] ?? 0);
$aksi    = $_POST['aksi'] ?? '';
$pilihan = $_POST['alasan'] ?? '';
$catatan = substr(trim($_POST['catatan'] ?? ''), 0, 150);

/** Simpan pesan lalu kembali ke dashboard */
function kembali(string $jenis, string $pesan, int $id): void
{
    $_SESSION[$jenis] = $pesan;   // 'sukses' atau 'galat'
    header('Location: dashboard.php?id=' . $id);
    exit;
}

$daftar = $aksi === 'batal' ? ALASAN_BATAL : ($aksi === 'retur' ? ALASAN_RETUR : []);
if (!in_array($pilihan, $daftar, true)) {
    kembali('galat', 'Pilih alasan yang valid.', $id);
}
$alasan = $pilihan . ($catatan !== '' ? ' - ' . $catatan : '');

try {
    $pdo->beginTransaction();
    // Kunci baris pesanan (milik user ini) agar tidak bentrok dengan aksi admin
    $st = $pdo->prepare('SELECT * FROM pembeli WHERE id = ? AND user_id = ? FOR UPDATE');
    $st->execute([$id, $user['id']]);
    $p = $st->fetch();

    if (!$p) {
        $pdo->rollBack();
        kembali('galat', 'Pesanan tidak ditemukan.', 0);
    }

    if ($aksi === 'batal') {
        if (!bisaDibatalkan($p)) {
            $pdo->rollBack();
            kembali('galat', $p['status'] === 'dibatalkan'
                ? 'Pesanan ini sudah dibatalkan sebelumnya.'
                : 'Pesanan sudah dikirim sehingga tidak dapat dibatalkan. Jika barang bermasalah, ajukan retur.', $id);
        }
        $pdo->prepare("UPDATE pembeli SET status = 'dibatalkan', alasan = ?, tgl_status = NOW() WHERE id = ?")
            ->execute([$alasan, $id]);
        kembalikanStok($pdo, $id);
        $pdo->commit();
        kembali('sukses', 'Pesanan berhasil dibatalkan dan stok buku dikembalikan.', $id);
    }

    // aksi === 'retur'
    if (!bisaDiretur($p)) {
        $pdo->rollBack();
        if ($p['status'] === 'diproses') {
            $pesan = 'Pesanan belum dikirim. Gunakan fitur batalkan pesanan.';
        } elseif (in_array($p['status'], ['dikirim', 'selesai'], true)) {
            $pesan = 'Batas waktu retur (' . BATAS_RETUR_HARI . ' hari sejak pembelian) sudah lewat.';
        } else {
            $pesan = 'Pesanan ini tidak dapat diretur (status: ' . labelStatus($p['status']) . ').';
        }
        kembali('galat', $pesan, $id);
    }
    $pdo->prepare("UPDATE pembeli SET status = 'retur_diajukan', alasan = ?, tgl_status = NOW() WHERE id = ?")
        ->execute([$alasan, $id]);
    $pdo->commit();
    kembali('sukses', 'Pengajuan retur terkirim. Admin akan meninjau pengajuan Anda.', $id);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    kembali('galat', 'Terjadi kesalahan. Silakan coba lagi.', $id);
}
