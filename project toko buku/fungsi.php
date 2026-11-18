<?php
// fungsi.php - fungsi kustom & algoritma validasi

/** Kondisi (if-else): kategori diskon berdasarkan jumlah buku */
function kategoriDiskon(int $jumlahBuku): array
{
    if ($jumlahBuku >= 10) {
        return ['nama' => 'Platinum', 'persen' => 35];
    } elseif ($jumlahBuku >= 6) {
        return ['nama' => 'Gold', 'persen' => 25];
    } elseif ($jumlahBuku >= 3) {
        return ['nama' => 'Silver', 'persen' => 15];
    }
    return ['nama' => 'Reguler', 'persen' => 0];
}

/** Fungsi kustom: total biaya akhir setelah diskon */
function hitungTotalAkhir(int $subtotal, int $persenDiskon): int
{
    return (int) round($subtotal - ($subtotal * $persenDiskon / 100));
}

function rupiah(int $angka): string
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/** Algoritma validasi input; mengembalikan array pesan error */
function validasiForm(array $d, int $totalBuku): array
{
    $err = [];
    if (strlen(trim($d['nama'])) < 3) {
        $err[] = 'Nama wajib diisi minimal 3 karakter.';
    }
    if (strpos($d['email'], '@') === false || !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        $err[] = 'Email tidak valid, harus mengandung karakter @.';
    }
    // usia 0 = akun lama tanpa data usia (usia 17+ sudah divalidasi saat pendaftaran akun)
    if ((int) $d['usia'] !== 0 && (!is_numeric($d['usia']) || (int) $d['usia'] < 17)) {
        $err[] = 'Usia minimal 17 tahun.';
    }
    if ($totalBuku < 1) {
        $err[] = 'Pilih minimal 1 buku untuk dibeli.';
    }
    return $err;
}

// ====== Stok, pembatalan, dan retur ======
const BATAS_RETUR_HARI = 7;
const ALASAN_BATAL = ['Salah memesan buku', 'Salah jumlah pesanan', 'Berubah pikiran'];
const ALASAN_RETUR = ['Barang tidak sesuai pesanan', 'Barang rusak / cacat', 'Salah kirim', 'Lainnya'];

function labelStatus(string $s): string
{
    $label = [
        'diproses' => 'Diproses', 'dikirim' => 'Dikirim', 'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan', 'retur_diajukan' => 'Retur Diajukan',
        'retur_disetujui' => 'Retur Disetujui', 'retur_ditolak' => 'Retur Ditolak',
    ];
    return $label[$s] ?? $s;
}

/** Pesanan yang dibatalkan / diretur tidak dihitung sebagai penjualan */
function statusAktif(string $s): bool
{
    return !in_array($s, ['dibatalkan', 'retur_disetujui'], true);
}

/** Pembatalan hanya boleh selama pesanan masih 'diproses' (belum dikirim) */
function bisaDibatalkan(array $p): bool
{
    return $p['status'] === 'diproses';
}

/** Retur hanya setelah dikirim, dan maksimal BATAS_RETUR_HARI hari sejak pembelian */
function bisaDiretur(array $p): bool
{
    return in_array($p['status'], ['dikirim', 'selesai'], true)
        && time() <= strtotime($p['created_at']) + BATAS_RETUR_HARI * 86400;
}

/** Kembalikan stok semua buku pada satu pesanan (dipakai saat batal / retur disetujui) */
function kembalikanStok(PDO $pdo, int $pembeliId): void
{
    $pdo->prepare('UPDATE buku b JOIN detail_pembelian d ON d.buku_id = b.id
                   SET b.stok = b.stok + d.jumlah WHERE d.pembeli_id = ?')->execute([$pembeliId]);
}
