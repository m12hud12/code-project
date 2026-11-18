# Toko Buku Online (PHP + MySQL)

Sistem pendaftaran akun & penjualan buku dengan login User/Admin, stok buku,
pembatalan pesanan, dan retur.

## Instalasi
1. Salin folder ini ke `C:\laragon\www\` (Laragon) atau `htdocs` (XAMPP).
2. phpMyAdmin > Import:
   - Instalasi baru: `database.sql` (PERHATIAN: menghapus & membuat ulang semua tabel).
   - Sudah punya data dari versi sebelumnya: jalankan `migrasi_stok_retur.sql`
     (menambah stok & status pesanan, data lama tetap aman).
3. Sesuaikan user/password MySQL di `koneksi.php` bila perlu.
4. Buka http://localhost/toko-buku-final/login.php

## Akun awal (ganti password untuk penggunaan nyata)
- Admin: admin@tokobuku.com / admin12345
- User : user@tokobuku.com / user12345

## Struktur file
| File | Fungsi |
|---|---|
| database.sql | Tabel users, buku (+stok), pembeli (+status), detail_pembelian + data awal |
| migrasi_stok_retur.sql | Migrasi database lama ke versi stok & retur |
| koneksi.php | Koneksi PDO ke MySQL |
| fungsi.php | Diskon, total akhir, rupiah, validasi, aturan status/batal/retur, kembalikan stok |
| auth.php | Sesi, proteksi halaman user/admin, token CSRF |
| login.php / register.php / logout.php | Masuk, daftar (usia min. 17 + pop-up), keluar |
| index.php | Beli buku: genre favorit + rekomendasi, pencarian, katalog dengan stok |
| proses.php | Validasi (termasuk stok), diskon, simpan + kurangi stok (transaksi) |
| pesanan.php | Pelanggan membatalkan pesanan / mengajukan retur |
| dashboard.php | Detail pesanan, status, tombol batal/retur, ucapan terima kasih, riwayat |
| admin.php | Statistik, kelola buku & stok, proses pesanan, setujui/tolak retur, akun |
| navbar.php, style.css | Navigasi dan tampilan |

## Aturan diskon
3-5 buku 15% (Silver), 6-9 buku 25% (Gold), 10+ buku 35% (Platinum).

## Stok
- Katalog menampilkan stok. Stok <= 5 ditandai "Sisa N", stok 0 ditandai "Habis" (tidak bisa dipesan).
- Jumlah pesanan dibatasi stok dan maksimal 20 per judul (dicek di browser dan di server).
- Stok berkurang otomatis saat pesanan dibuat, dan kembali saat pesanan dibatalkan / retur disetujui.

## Alur status pesanan
diproses -> dikirim -> selesai   (diubah admin)

| Situasi | Aksi pelanggan | Syarat | Hasil |
|---|---|---|---|
| Salah memesan | Batalkan pesanan | Status "diproses" | Status "dibatalkan", stok kembali |
| Barang tidak sesuai | Ajukan retur | Status "dikirim"/"selesai", maks. 7 hari sejak pembelian | Status "retur_diajukan" |
| Retur diajukan | Admin setujui / tolak | - | Setujui: "retur_disetujui" + stok kembali; Tolak: "retur_ditolak" |

Pesanan dibatalkan / retur disetujui tidak dihitung dalam pendapatan dan buku terjual.
