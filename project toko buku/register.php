<?php
// register.php - pendaftaran akun user baru (role selalu 'user')
require_once 'auth.php';
if (userLogin()) { header('Location: index.php'); exit; }

$errors = [];
$umurDitolak = false;   // true -> tampilkan pop-up penolakan umur
$nama = $email = $usia = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cekCsrf();
    $nama  = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $usia  = trim($_POST['usia'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (strlen($nama) < 3) $errors[] = 'Nama minimal 3 karakter.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    $usiaInt = filter_var($usia, FILTER_VALIDATE_INT);
    if ($usiaInt === false) {
        $errors[] = 'Usia harus berupa angka.';
    } elseif ($usiaInt < 17) {
        $umurDitolak = true;
    } elseif ($usiaInt > 120) {
        $errors[] = 'Usia tidak valid.';
    }
    if (strlen($pass) < 8) $errors[] = 'Password minimal 8 karakter.';
    if ($pass !== $pass2) $errors[] = 'Konfirmasi password tidak sama.';

    if (!$errors && !$umurDitolak) {
        $cek = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $cek->execute([$email]);
        if ($cek->fetch()) {
            $errors[] = 'Email sudah terdaftar. Silakan login.';
        } else {
            $st = $pdo->prepare("INSERT INTO users (nama,email,usia,password,role) VALUES (?,?,?,?, 'user')");
            $st->execute([$nama, $email, $usiaInt, password_hash($pass, PASSWORD_DEFAULT)]);
            $_SESSION['info'] = 'Registrasi berhasil! Silakan login.';
            header('Location: login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar Akun - Toko Buku</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* Pop-up penolakan umur (disertakan di sini agar tidak bergantung pada style.css) */
    .modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;z-index:9999;padding:16px}
    .modal-overlay[hidden]{display:none}
    .modal-box{background:#fff;border-radius:12px;padding:26px 28px;max-width:380px;width:100%;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.3);animation:muncul .18s ease-out}
    .modal-box h2{margin:6px 0 10px;color:#b3261e}
    .modal-box p{margin:6px 0}
    .modal-ikon{font-size:2.4em}
    .modal-box button{margin-top:14px;min-width:120px}
    @keyframes muncul{from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1}}
  </style>
</head>
<body>
<div class="container sempit">
  <h1>📝 Daftar Akun</h1>
  <?php if ($errors): ?>
    <div class="alert error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" class="kolom" id="form-daftar">
    <?= csrfField() ?>
    <label>Nama Lengkap <input type="text" name="nama" required value="<?= htmlspecialchars($nama) ?>"></label>
    <label>Email <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>"></label>
    <label>Usia (minimal 17 tahun) <input type="number" name="usia" id="usia" max="120" required value="<?= htmlspecialchars($usia) ?>"></label>
    <label>Password (min. 8 karakter) <input type="password" name="password" required></label>
    <label>Ulangi Password <input type="password" name="password2" required></label>
    <button type="submit">Daftar</button>
  </form>
  <p>Sudah punya akun? <a href="login.php">Login</a></p>
</div>
<div class="modal-overlay" id="modal-umur" role="dialog" aria-modal="true" aria-labelledby="judul-modal"<?= $umurDitolak ? '' : ' hidden' ?>>
  <div class="modal-box">
    <div class="modal-ikon">⚠️</div>
    <h2 id="judul-modal">Pendaftaran Ditolak</h2>
    <p>Maaf, umur Anda tidak sesuai dengan ketentuan kami.</p>
    <p class="note">Pendaftaran hanya untuk pengguna berusia minimal 17 tahun.</p>
    <button type="button" id="tutup-modal">Mengerti</button>
  </div>
</div>
<script>
// Pop-up umur: muncul saat tombol Daftar ditekan dengan usia di bawah 17 (server juga memeriksa ulang)
const formDaftar = document.getElementById('form-daftar');
const inputUsia  = document.getElementById('usia');
const modal      = document.getElementById('modal-umur');
function tutupModal() { modal.hidden = true; inputUsia.focus(); inputUsia.select(); }
formDaftar.addEventListener('submit', e => {
  const v = parseInt(inputUsia.value, 10);
  if (!isNaN(v) && v < 17) { e.preventDefault(); modal.hidden = false; }
});
document.getElementById('tutup-modal').addEventListener('click', tutupModal);
modal.addEventListener('click', e => { if (e.target === modal) tutupModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) tutupModal(); });
</script>
</body>
</html>
