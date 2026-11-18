<?php
// login.php - satu halaman login untuk user dan admin; tujuan redirect ditentukan oleh role
require_once 'auth.php';

if ($u = userLogin()) {
    header('Location: ' . ($u['role'] === 'admin' ? 'admin.php' : 'index.php'));
    exit;
}

$error = '';
$email = '';
$info  = $_SESSION['info'] ?? (isset($_GET['out']) ? 'Anda telah logout.' : '');
unset($_SESSION['info']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cekCsrf();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (time() < ($_SESSION['kunci'] ?? 0)) {
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam 1 menit.';
    } else {
        $st = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([$email]);
        $u = $st->fetch();

        if ($u && password_verify($pass, $u['password'])) {
            session_regenerate_id(true);               // cegah session fixation
            $_SESSION['gagal'] = 0;
            $_SESSION['user'] = ['id' => (int) $u['id'], 'nama' => $u['nama'],
                                 'email' => $u['email'], 'role' => $u['role'], 'usia' => $u['usia']];
            header('Location: ' . ($u['role'] === 'admin' ? 'admin.php' : 'index.php'));
            exit;
        }
        $_SESSION['gagal'] = ($_SESSION['gagal'] ?? 0) + 1;
        if ($_SESSION['gagal'] >= 5) {
            $_SESSION['kunci'] = time() + 60;
            $_SESSION['gagal'] = 0;
        }
        $error = 'Email atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - Toko Buku</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container sempit">
 <center><b><h1>🔐Login</h1></b></center>
  <p class="sub">Masuk sebagai user atau admin.</p>
  <?php if ($info): ?><div class="alert success"><?= htmlspecialchars($info) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" class="kolom">
    <?= csrfField() ?>
    <label>Email <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>" placeholder="masukkan email"></label>
    <label>Password <input type="password" name="password" required placeholder="masukkan password"></label>
    <button type="submit">Masuk</button>
  </form>
  <p>Belum punya akun? <a href="register.php">Daftar di sini</a></p>
</div>
</body>
</html>
