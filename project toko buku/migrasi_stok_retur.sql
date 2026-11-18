-- Migrasi: tambah stok buku + status pesanan (batal/retur) tanpa menghapus data lama
USE toko_buku;

ALTER TABLE buku ADD COLUMN stok INT NOT NULL DEFAULT 0;
UPDATE buku SET stok = 20;

ALTER TABLE pembeli
  ADD COLUMN status ENUM('diproses','dikirim','selesai','dibatalkan','retur_diajukan','retur_disetujui','retur_ditolak')
      NOT NULL DEFAULT 'diproses',
  ADD COLUMN alasan VARCHAR(255) NULL,
  ADD COLUMN tgl_status TIMESTAMP NULL DEFAULT NULL;

-- Transaksi lama dianggap sudah selesai
UPDATE pembeli SET status = 'selesai';
