<?php
// dashboard.php - detail pesanan, pembatalan/retur, dan riwayat pembelian
require_once 'auth.php';
require 'fungsi.php';
$user = wajibLogin();
$isAdmin = $user['role'] === 'admin';

$sukses = $_SESSION['sukses'] ?? null;
$galat  = $_SESSION['galat'] ?? null;
unset($_SESSION['sukses'], $_SESSION['galat']);
$id = (int) ($_GET['id'] ?? 0);

$pembeli = null; $detail = [];
if ($id > 0) {
    if ($isAdmin) {
        $st = $pdo->prepare('SELECT * FROM pembeli WHERE id = ?');
        $st->execute([$id]);
    } else {   // user hanya boleh melihat transaksinya sendiri
        $st = $pdo->prepare('SELECT * FROM pembeli WHERE id = ? AND user_id = ?');
        $st->execute([$id, $user['id']]);
    }
    $pembeli = $st->fetch();
}

if ($pembeli) {
    $st = $pdo->prepare('SELECT b.id, b.judul, b.genre, b.harga, d.jumlah
                         FROM detail_pembelian d JOIN buku b ON b.id = d.buku_id WHERE d.pembeli_id = ?');
    $st->execute([$id]);
    $detail = $st->fetchAll();
}

if ($isAdmin) {
    $semua = $pdo->query('SELECT * FROM pembeli ORDER BY id DESC LIMIT 20')->fetchAll();
} else {
    $st = $pdo->prepare('SELECT * FROM pembeli WHERE user_id = ? ORDER BY id DESC LIMIT 20');
    $st->execute([$user['id']]);
    $semua = $st->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - Toko Buku</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;z-index:9999;padding:16px}
    .modal-overlay[hidden]{display:none}
    .modal-box{background:#fff;border-radius:12px;padding:22px 26px;max-width:400px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,.3);animation:muncul .18s ease-out}
    .modal-box h2{margin:0 0 6px}
    .modal-aksi{display:flex;gap:10px;justify-content:flex-end;margin-top:4px}
    .btn-abu{background:#8a8a8a}.btn-abu:hover{background:#6e6e6e}
    @keyframes muncul{from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1}}
  </style>
</head>
<body>
<div class="container">
  <?php include 'navbar.php'; ?>
  <h1>📊 Dashboard</h1>
  <?php if ($sukses): ?><div class="alert success"><?= htmlspecialchars($sukses) ?></div><?php endif; ?>
  <?php if ($galat): ?><div class="alert error"><?= htmlspecialchars($galat) ?></div><?php endif; ?>

  <?php if ($pembeli): $s = $pembeli['status']; ?>
    <h2>Halo, <?= htmlspecialchars($pembeli['nama']) ?>!</h2>
    <p class="sub"><?= htmlspecialchars($pembeli['email']) ?><?= (int) $pembeli['usia'] > 0 ? ' · usia ' . (int) $pembeli['usia'] : '' ?> · genre favorit: <?= htmlspecialchars($pembeli['genre_favorit']) ?></p>
    <p>Pesanan #<?= $pembeli['id'] ?> · Status:
      <span class="badge-status st-<?= $s ?>"><?= labelStatus($s) ?></span></p>
    <?php if ($pembeli['alasan']): ?>
      <p class="note">Alasan: <?= htmlspecialchars($pembeli['alasan']) ?></p>
    <?php endif; ?>

    <h3>Daftar Buku yang Dibeli</h3>
    <table>
      <tr><th>No</th><th>Judul</th><th>Genre</th><th>Harga</th><th>Jumlah</th><th>Subtotal</th></tr>
      <?php for ($i = 0; $i < count($detail); $i++): $d = $detail[$i]; ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($d['judul']) ?></td>
          <td><?= htmlspecialchars($d['genre']) ?></td>
          <td><?= rupiah((int) $d['harga']) ?></td>
          <td><?= (int) $d['jumlah'] ?></td>
          <td><?= rupiah($d['harga'] * $d['jumlah']) ?></td>
        </tr>
      <?php endfor; ?>
    </table>
    <div class="ringkas">
      Subtotal <?= rupiah((int) $pembeli['subtotal']) ?> − Diskon <?= (int) $pembeli['diskon_persen'] ?>%
      = <strong>Total Bayar <?= rupiah((int) $pembeli['total_bayar']) ?></strong>
    </div>

    <?php if (!$isAdmin): ?>
      <?php if (bisaDibatalkan($pembeli)): ?>
        <div class="aksi-pesanan">
          <h3>❌ Salah memesan?</h3>
          <p class="note">Pesanan masih diproses sehingga dapat dibatalkan. Stok buku akan dikembalikan.</p>
          <form action="pesanan.php" method="post" class="inline-form"
                onsubmit="return confirm('Yakin ingin membatalkan pesanan ini?')">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $pembeli['id'] ?>">
            <input type="hidden" name="aksi" value="batal">
            <select name="alasan" required>
              <option value="">-- Pilih alasan --</option>
              <?php foreach (ALASAN_BATAL as $a): ?><option><?= htmlspecialchars($a) ?></option><?php endforeach; ?>
            </select>
            <input name="catatan" maxlength="150" placeholder="Catatan (opsional)">
            <button type="submit" class="btn-danger">Batalkan Pesanan</button>
          </form>
        </div>
      <?php elseif (bisaDiretur($pembeli)): ?>
        <div class="aksi-pesanan">
          <h3>↩️ Barang tidak sesuai?</h3>
          <p class="note">Ajukan retur sampai <?= date('d-m-Y', strtotime($pembeli['created_at']) + BATAS_RETUR_HARI * 86400) ?>
             (<?= BATAS_RETUR_HARI ?> hari sejak pembelian). Admin akan meninjau pengajuan Anda.</p>
          <form action="pesanan.php" method="post" class="inline-form"
                onsubmit="return confirm('Ajukan retur untuk pesanan ini?')">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $pembeli['id'] ?>">
            <input type="hidden" name="aksi" value="retur">
            <select name="alasan" required>
              <option value="">-- Pilih alasan --</option>
              <?php foreach (ALASAN_RETUR as $a): ?><option><?= htmlspecialchars($a) ?></option><?php endforeach; ?>
            </select>
            <input name="catatan" maxlength="150" placeholder="Jelaskan masalahnya (opsional)">
            <button type="submit" class="btn-danger">Ajukan Retur</button>
          </form>
        </div>
      <?php elseif ($s === 'retur_diajukan'): ?>
        <div class="alert info">⏳ Pengajuan retur Anda sedang ditinjau admin.</div>
      <?php elseif ($s === 'retur_disetujui'): ?>
        <div class="alert success">✅ Retur disetujui. Pengembalian dana akan diproses oleh toko.</div>
      <?php elseif ($s === 'retur_ditolak'): ?>
        <div class="alert error">Pengajuan retur ditolak oleh admin.</div>
      <?php elseif (in_array($s, ['dikirim', 'selesai'], true)): ?>
        <p class="note">Batas waktu pengajuan retur (<?= BATAS_RETUR_HARI ?> hari) sudah lewat.</p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (statusAktif($s) && !in_array($s, ['retur_diajukan', 'retur_ditolak'], true)): ?>
      <div class="terima-kasih">
        <h3>🙏 Terima Kasih, <?= htmlspecialchars($pembeli['nama']) ?>!</h3>
        <p>Terima kasih telah berbelanja di Toko Buku Online kami. Pesanan Anda sudah kami catat
           dan semoga buku-buku pilihan Anda memberi banyak ilmu dan inspirasi.
           Kami tunggu kunjungan Anda berikutnya! 📚</p>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <h3><?= $isAdmin ? 'Data Pembeli Terbaru' : 'Riwayat Pembelian Anda' ?> (diambil dari MySQL)</h3>
  <table>
    <tr><th>ID</th><th>Nama</th><th>Buku</th><th>Diskon</th><th>Total</th><th>Status</th><th>Waktu</th><?php if (!$isAdmin): ?><th>Aksi</th><?php endif; ?></tr>
    <?php foreach ($semua as $p): ?>
      <tr>
        <td><?= $p['id'] ?></td>
        <td><a href="dashboard.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['nama']) ?></a></td>
        <td><?= $p['total_buku'] ?></td>
        <td><?= $p['diskon_persen'] ?>%</td>
        <td><?= rupiah((int) $p['total_bayar']) ?></td>
        <td><span class="badge-status st-<?= $p['status'] ?>"><?= labelStatus($p['status']) ?></span></td>
        <td><?= $p['created_at'] ?></td>
        <?php if (!$isAdmin): ?>
        <td>
          <?php if (bisaDiretur($p)): ?>
            <button type="button" class="btn-kecil btn-danger btn-retur" data-id="<?= $p['id'] ?>">↩️ Ajukan Retur</button>
          <?php elseif ($p['status'] === 'diproses'): ?>
            <small class="note">Belum dikirim</small>
          <?php elseif (in_array($p['status'], ['dikirim', 'selesai'], true)): ?>
            <small class="note">Lewat <?= BATAS_RETUR_HARI ?> hari</small>
          <?php elseif ($p['status'] === 'retur_diajukan'): ?>
            <small class="note">Menunggu admin</small>
          <?php else: ?>-<?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </table>
  <p><?php if ($isAdmin): ?><a href="admin.php">← Kembali ke panel admin</a><?php else: ?><a href="index.php">← Kembali ke form</a><?php endif; ?></p>
</div>

<?php if (!$isAdmin): ?>
<!-- Pop-up pengajuan retur dari tabel riwayat -->
<div class="modal-overlay" id="modal-retur" role="dialog" aria-modal="true" aria-labelledby="judul-retur" hidden>
  <div class="modal-box">
    <h2 id="judul-retur">↩️ Ajukan Retur</h2>
    <p class="note">Pesanan #<span id="retur-no"></span>. Admin akan meninjau pengajuan Anda.</p>
    <form action="pesanan.php" method="post" class="kolom">
      <?= csrfField() ?>
      <input type="hidden" name="id" id="retur-id">
      <input type="hidden" name="aksi" value="retur">
      <label>Alasan retur
        <select name="alasan" required>
          <option value="">-- Pilih alasan --</option>
          <?php foreach (ALASAN_RETUR as $a): ?><option><?= htmlspecialchars($a) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Catatan (opsional)
        <input name="catatan" maxlength="150" placeholder="Jelaskan masalahnya">
      </label>
      <div class="modal-aksi">
        <button type="button" class="btn-abu" id="tutup-retur">Batal</button>
        <button type="submit" class="btn-danger">Kirim Pengajuan</button>
      </div>
    </form>
  </div>
</div>
<script>
const modalRetur = document.getElementById('modal-retur');
document.querySelectorAll('.btn-retur').forEach(btn => btn.addEventListener('click', () => {
  document.getElementById('retur-id').value = btn.dataset.id;
  document.getElementById('retur-no').textContent = btn.dataset.id;
  modalRetur.hidden = false;
}));
const tutupRetur = () => { modalRetur.hidden = true; };
document.getElementById('tutup-retur').addEventListener('click', tutupRetur);
modalRetur.addEventListener('click', e => { if (e.target === modalRetur) tutupRetur(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modalRetur.hidden) tutupRetur(); });
</script>
<?php endif; ?>
</body>
</html>