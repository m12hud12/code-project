CREATE DATABASE IF NOT EXISTS toko_buku CHARACTER SET utf8mb4;
USE toko_buku;

-- Hapus tabel lama (urutan penting karena foreign key)
DROP TABLE IF EXISTS detail_pembelian;
DROP TABLE IF EXISTS pembeli;
DROP TABLE IF EXISTS buku;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  usia INT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE buku (
  id INT AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(150) NOT NULL,
  penulis VARCHAR(100) NOT NULL,
  genre VARCHAR(50) NOT NULL,
  harga INT NOT NULL,
  stok INT NOT NULL DEFAULT 0
);

CREATE TABLE pembeli (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  usia INT NOT NULL,
  genre_favorit VARCHAR(50) NOT NULL,
  total_buku INT NOT NULL,
  subtotal INT NOT NULL,
  diskon_persen INT NOT NULL,
  total_bayar INT NOT NULL,
  status ENUM('diproses','dikirim','selesai','dibatalkan','retur_diajukan','retur_disetujui','retur_ditolak')
         NOT NULL DEFAULT 'diproses',
  alasan VARCHAR(255) NULL,
  tgl_status TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE detail_pembelian (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pembeli_id INT NOT NULL,
  buku_id INT NOT NULL,
  jumlah INT NOT NULL,
  FOREIGN KEY (pembeli_id) REFERENCES pembeli(id) ON DELETE CASCADE,
  FOREIGN KEY (buku_id) REFERENCES buku(id)
);

INSERT INTO buku (judul, penulis, genre, harga) VALUES
('Laskar Pelangi','Andrea Hirata','Novel',89000),
('Bumi Manusia','Pramoedya Ananta Toer','Novel',110000),
('Filosofi Teras','Henry Manampiring','Self-Help',98000),
('Atomic Habits','James Clear','Self-Help',108000),
('Clean Code','Robert C. Martin','Teknologi',350000),
('Pemrograman PHP & MySQL','Abdul Kadir','Teknologi',120000),
('Sapiens','Yuval Noah Harari','Sejarah',139000),
('Sejarah Indonesia Modern','M.C. Ricklefs','Sejarah',150000),
('Cosmos','Carl Sagan','Sains',125000),
('Brief Answers to the Big Questions','Stephen Hawking','Sains',95000),
('Dilan 1990','Pidi Baiq','Novel',79000),
('Rich Dad Poor Dad','Robert Kiyosaki','Bisnis',99000);

-- Akun awal: admin@tokobuku.com / admin12345 ; user@tokobuku.com / user12345
INSERT INTO users (nama, email, usia, password, role) VALUES
('Administrator','admin@tokobuku.com',30,'$2b$12$4zKkaEORU/9aAusZ.vgDJOqT8.1f4/cVa0dxwq0GiTVX0MBFrikgW','admin'),
('User Demo','user@tokobuku.com',20,'$2b$12$pIARB9h3IgeRXiYwq26li.5miGphirckhVKwcUboJTr2z3hZZ9lD.','user');

-- Stok awal buku (contoh: satu buku stok menipis, satu buku habis)
UPDATE buku SET stok = 20;
UPDATE buku SET stok = 3 WHERE judul = 'Clean Code';
UPDATE buku SET stok = 0 WHERE judul LIKE 'Brief Answers%';
