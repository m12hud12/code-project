<?php
// admin.php - panel admin: statistik, kelola buku & stok, proses pesanan, retur, daftar akun
require_once 'auth.php';
require_once 'fungsi.php';
$admin = wajibAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cekCsrf();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah_buku') {
        $judul   = trim($_POST['judul'] ?? '');
        $penulis = trim($_POST['penulis'] ?? '');
        $genre   = trim($_POST['genre'] ?? '');
        $harga   = (int) ($_POST['harga'] ?? 0);
        $stok    = (int) ($_POST['stok'] ?? 0);
        if ($judul === '' || $penulis === '' || $genre === '' || $harga <= 0 || $stok < 0) {
            $_SESSION['flash'] = ['error', 'Semua kolom wajib diisi, harga > 0 dan stok tidak boleh negatif.'];
        } else {
            $pdo->prepare('INSERT INTO buku (judul,penulis,genre,harga,stok) VALUES (?,?,?,?,?)')
                ->execute([$judul, $penulis, $genre, $harga, $stok]);
            $_SESSION['flash'] = ['success', 'Buku "' . $judul . '" berhasil ditambahkan.'];
        }
    } elseif ($aksi === 'update_stok') {
        $stok = (int) ($_POST['stok'] ?? -1);
        if ($stok < 0) {
            $_SESSION['flash'] = ['error', 'Stok tidak boleh negatif.'];
        } else {
            $pdo->prepare('UPDATE buku SET stok = ? WHERE id = ?')->execute([$stok, (int) ($_POST['id'] ?? 0)]);
            $_SESSION['flash'] = ['success', 'Stok berhasil diperbarui.'];
        }
    } elseif ($aksi === 'hapus_buku') {
        try {
            $st = $pdo->prepare('DELETE FROM buku WHERE id = ?');
            $st->execute([(int) ($_POST['id'] ?? 0)]);
            $_SESSION['flash'] = ['success', 'Buku berhasil dihapus.'];
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['error', 'Buku sudah pernah dibeli sehingga tidak dapat dihapus.'];
        }
    } elseif ($aksi === 'ubah_status') {
        // Alur pesanan: diproses -> dikirim -> selesai (status asal harus cocok)
        $ke   = $_POST['ke'] ?? '';
        $dari = ['dikirim' => 'diproses', 'selesai' => 'dikirim'][$ke] ?? null;
        if ($dari === null) {
            $_SESSION['flash'] = ['error', 'Status tujuan tidak valid.'];
        } else {
            $st = $pdo->prepare('UPDATE pembeli SET status = ?, tgl_status = NOW() WHERE id = ? AND status = ?');
            $st->execute([$ke, (int) ($_POST['id'] ?? 0), $dari]);
            $_SESSION['flash'] = $st->rowCount() === 1
                ? ['success', 'Status pesanan diubah menjadi ' . labelStatus($ke) . '.']
                : ['error', 'Status pesanan sudah berubah atau tidak valid.'];
        }
    } elseif ($aksi === 'retur') {
        $setuju = ($_POST['keputusan'] ?? '') === 'setujui';
        $idP    = (int) ($_POST['id'] ?? 0);
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("UPDATE pembeli SET status = ?, tgl_status = NOW() WHERE id = ? AND status = 'retur_diajukan'");
            $st->execute([$setuju ? 'retur_disetujui' : 'retur_ditolak', $idP]);
            if ($st->rowCount() === 1) {
                if ($setuju) {
                    kembalikanStok($pdo, $idP);   // barang retur kembali ke stok
                }
                $pdo->commit();
                $_SESSION['flash'] = ['success', $setuju ? 'Retur disetujui dan stok dikembalikan.' : 'Retur ditolak.'];
            } else {
                $pdo->rollBack();
                $_SESSION['flash'] = ['error', 'Pengajuan retur tidak ditemukan atau sudah diproses.'];
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash'] = ['error', 'Terjadi kesalahan saat memproses retur.'];
        }
    }
    header('Location: admin.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$stat = $pdo->query("SELECT COUNT(*) AS transaksi,
        COALESCE(SUM(CASE WHEN status NOT IN ('dibatalkan','retur_disetujui') THEN total_bayar END),0) AS pendapatan,
        COALESCE(SUM(CASE WHEN status NOT IN ('dibatalkan','retur_disetujui') THEN total_buku END),0) AS terjual,
        COALESCE(SUM(status = 'retur_diajukan'),0) AS retur_menunggu
        FROM pembeli")->fetch();
$jmlUser  = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$menipis  = (int) $pdo->query('SELECT COUNT(*) FROM buku WHERE stok <= 5')->fetchColumn();
$bukuList = $pdo->query('SELECT * FROM buku ORDER BY genre, judul')->fetchAll();
$transaksi = $pdo->query('SELECT p.*, u.nama AS akun FROM pembeli p LEFT JOIN users u ON u.id = p.user_id
                          ORDER BY p.id DESC LIMIT 50')->fetchAll();
$users = $pdo->query('SELECT id, nama, email, usia, role, created_at FROM users ORDER BY id')->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel Admin - Toko Buku</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <?php include 'navbar.php'; ?>
  <h1>🛠️ Panel Admin</h1>
  <?php if ($flash): ?><div class="alert <?= $flash[0] ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

  <div class="stats">
    <div class="stat"><b><?= (int) $stat['transaksi'] ?></b>Transaksi</div>
    <div class="stat"><b><?= rupiah((int) $stat['pendapatan']) ?></b>Pendapatan</div>
    <div class="stat"><b><?= (int) $stat['terjual'] ?></b>Buku Terjual</div>
    <div class="stat"><b><?= $jmlUser ?></b>User Terdaftar</div>
    <div class="stat"><b><?= (int) $stat['retur_menunggu'] ?></b>Retur Menunggu</div>
    <div class="stat"><b><?= $menipis ?></b>Stok Menipis (≤5)</div>
  </div>

  <h2>Kelola Buku &amp; Stok</h2>
  <form method="post" class="inline-form">
    <?= csrfField() ?><input type="hidden" name="aksi" value="tambah_buku">
    <input name="judul" placeholder="Judul" required>
    <input name="penulis" placeholder="Penulis" required>
    <input name="genre" placeholder="Genre" list="genre-list" required>
    <input name="harga" type="number" min="1" placeholder="Harga" required>
    <input name="stok" type="number" min="0" placeholder="Stok" required>
    <button type="submit">+ Tambah</button>
  </form>
  <datalist id="genre-list">
    <?php foreach (array_unique(array_column($bukuList, 'genre')) as $g): ?><option value="<?= htmlspecialchars($g) ?>"><?php endforeach; ?>
  </datalist>
  <div class="tabel-scroll">
    <table>
      <tr><th>ID</th><th>Judul</th><th>Genre</th><th>Harga</th><th>Stok</th><th>Ubah Stok</th><th>Hapus</th></tr>
      <?php foreach ($bukuList as $b): $stok = (int) $b['stok']; ?>
        <tr>
          <td><?= $b['id'] ?></td>
          <td><?= htmlspecialchars($b['judul']) ?><br><small><?= htmlspecialchars($b['penulis']) ?></small></td>
          <td><?= htmlspecialchars($b['genre']) ?></td>
          <td><?= rupiah((int) $b['harga']) ?></td>
          <td>
            <?php if ($stok <= 0): ?><span class="tag habis">Habis</span>
            <?php elseif ($stok <= 5): ?><span class="tag menipis"><?= $stok ?> (menipis)</span>
            <?php else: ?><?= $stok ?><?php endif; ?>
          </td>
          <td>
            <form method="post" class="stok-form">
              <?= csrfField() ?><input type="hidden" name="aksi" value="update_stok">
              <input type="hidden" name="id" value="<?= $b['id'] ?>">
              <input type="number" name="stok" min="0" value="<?= $stok ?>">
              <button class="btn-kecil" type="submit">Simpan</button>
            </form>
          </td>
          <td>
            <form method="post" onsubmit="return confirm('Hapus buku ini?')">
              <?= csrfField() ?><input type="hidden" name="aksi" value="hapus_buku">
              <input type="hidden" name="id" value="<?= $b['id'] ?>">
              <button class="btn-kecil btn-danger" type="submit">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <h2>Pesanan &amp; Retur</h2>
  <div class="tabel-scroll">
    <table>
      <tr><th>ID</th><th>Pembeli</th><th>Buku</th><th>Total</th><th>Status</th><th>Waktu</th><th>Aksi</th></tr>
      <?php foreach ($transaksi as $t): $s = $t['status']; ?>
        <tr>
          <td><?= $t['id'] ?></td>
          <td><a href="dashboard.php?id=<?= $t['id'] ?>"><?= htmlspecialchars($t['nama']) ?></a>
              <br><small><?= htmlspecialchars($t['akun'] ?? '-') ?></small></td>
          <td><?= $t['total_buku'] ?></td>
          <td><?= rupiah((int) $t['total_bayar']) ?></td>
          <td><span class="badge-status st-<?= $s ?>"><?= labelStatus($s) ?></span>
              <?php if ($t['alasan']): ?><br><small><?= htmlspecialchars($t['alasan']) ?></small><?php endif; ?></td>
          <td><?= $t['created_at'] ?></td>
          <td class="aksi-sel">
            <?php if ($s === 'diproses' || $s === 'dikirim'): ?>
              <form method="post">
                <?= csrfField() ?><input type="hidden" name="aksi" value="ubah_status">
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                <input type="hidden" name="ke" value="<?= $s === 'diproses' ? 'dikirim' : 'selesai' ?>">
                <button class="btn-kecil" type="submit"><?= $s === 'diproses' ? '🚚 Kirim' : '✔ Selesai' ?></button>
              </form>
            <?php elseif ($s === 'retur_diajukan'): ?>
              <form method="post" onsubmit="return confirm('Setujui retur? Stok akan dikembalikan.')">
                <?= csrfField() ?><input type="hidden" name="aksi" value="retur">
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                <input type="hidden" name="keputusan" value="setujui">
                <button class="btn-kecil" type="submit">Setujui</button>
              </form>
              <form method="post" onsubmit="return confirm('Tolak pengajuan retur ini?')">
                <?= csrfField() ?><input type="hidden" name="aksi" value="retur">
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                <input type="hidden" name="keputusan" value="tolak">
                <button class="btn-kecil btn-danger" type="submit">Tolak</button>
              </form>
            <?php else: ?>-<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <h2>Daftar Akun</h2>
  <div class="tabel-scroll">
    <table>
      <tr><th>ID</th><th>Nama</th><th>Email</th><th>Usia</th><th>Role</th><th>Dibuat</th></tr>
      <?php foreach ($users as $u): ?>
        <tr><td><?= $u['id'] ?></td><td><?= htmlspecialchars($u['nama']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td><td><?= $u['usia'] ?? '-' ?></td>
            <td><?= $u['role'] ?></td><td><?= $u['created_at'] ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</body>
</html>
