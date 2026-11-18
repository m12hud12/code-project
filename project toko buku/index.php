<?php
require_once 'auth.php';
require 'fungsi.php';
$user = wajibLogin();
if ($user['role'] === 'admin') { header('Location: admin.php'); exit; }

$bukuList = $pdo->query('SELECT * FROM buku ORDER BY genre, judul')->fetchAll();
$errors   = $_SESSION['errors'] ?? [];
$old      = $_SESSION['old'] ?? [];
unset($_SESSION['errors'], $_SESSION['old']);
$genres = [];
foreach ($bukuList as $b) {
    $genres[strtolower($b['genre'])] ??= ucfirst($b['genre']);
}
$genres = array_values($genres);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Toko Buku Online - Pendaftaran & Pembelian</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
<?php include 'navbar.php'; ?>
  <h1>👋 Hello, <?= htmlspecialchars($user['nama']) ?>!</h1>
  <p class="sub">Pilih genre favorit, cari buku, dan dapatkan diskon sesuai jumlah buku.</p>

  <?php if ($errors): ?>
    <div class="alert error">
      <strong>Periksa kembali isian Anda:</strong>
      <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form action="proses.php" method="post" id="form">
    <?= csrfField() ?>
    <div class="grid">
      <label>Genre Favorit
        <select name="genre_favorit" id="genre" required>
          <option value="">-- Pilih genre --</option>
          <?php foreach ($genres as $g): ?>
            <option <?= (($old['genre_favorit'] ?? '') === $g) ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Cari Buku
        <input type="search" id="cari" placeholder="Judul, penulis, atau genre..." autocomplete="off">
      </label>
    </div>

    <div id="rekomendasi" class="rekom-box" hidden>
      <h3>⭐ Rekomendasi genre <span id="rekom-genre"></span></h3>
      <div class="cards" id="rekom-list"></div>
    </div>

    <h2>Katalog Buku</h2>
    <div class="tabel-scroll">
    <table>
      <tr><th>Judul</th><th>Penulis</th><th>Genre</th><th>Harga</th><th>Stok</th><th>Jumlah</th></tr>
      <?php foreach ($bukuList as $b): $stok = (int) $b['stok']; $maks = min(20, $stok); ?>
      <tr>
        <td><?= htmlspecialchars($b['judul']) ?></td>
        <td><?= htmlspecialchars($b['penulis']) ?></td>
        <td><span class="tag"><?= htmlspecialchars($b['genre']) ?></span></td>
        <td><?= rupiah((int) $b['harga']) ?></td>
        <td>
          <?php if ($stok <= 0): ?><span class="tag habis">Habis</span>
          <?php elseif ($stok <= 5): ?><span class="tag menipis">Sisa <?= $stok ?></span>
          <?php else: ?><?= $stok ?><?php endif; ?>
        </td>
        <td><input type="number" class="qty" min="0" max="<?= $maks ?>" data-harga="<?= $b['harga'] ?>"
             id="qty-<?= $b['id'] ?>" name="qty[<?= $b['id'] ?>]"
             value="<?= min($maks, (int) ($old['qty'][$b['id']] ?? 0)) ?>" <?= $stok <= 0 ? 'disabled' : '' ?>></td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <p id="tidak-ada" class="note" hidden>Buku tidak ditemukan.</p>

    <div class="ringkas" id="ringkas">Total: 0 buku | Diskon 0% | Bayar Rp 0</div>
    <p class="note">Diskon: 3-5 buku 15% (Silver), 6-9 buku 25% (Gold), 10+ buku 35% (Platinum).</p>
    <button type="submit">Daftar & Beli Sekarang</button>
  </form>
  <p><a href="dashboard.php">Riwayat pembelian saya →</a></p>
</div>

<script>
// Interaksi sisi client: hitung ringkasan secara dinamis tanpa reload
const qtys = document.querySelectorAll('.qty'), out = document.getElementById('ringkas');
function hitung() {
  let n = 0, sub = 0;
  qtys.forEach(q => { const j = parseInt(q.value) || 0; n += j; sub += j * q.dataset.harga; });
  const d = n >= 10 ? 35 : n >= 6 ? 25 : n >= 3 ? 15 : 0;
  const total = Math.round(sub - sub * d / 100);
  out.textContent = `Total: ${n} buku | Diskon ${d}% | Bayar Rp ${total.toLocaleString('id-ID')}`;
}
// Jumlah tidak boleh melebihi stok (atribut max) dan tidak boleh negatif
qtys.forEach(q => q.addEventListener('input', () => {
  const m = parseInt(q.max);
  if (m >= 0 && parseInt(q.value) > m) q.value = m;
  if (parseInt(q.value) < 0) q.value = 0;
  hitung();
}));
hitung();

// Rekomendasi langsung muncul saat genre favorit dipilih
const buku = <?= json_encode($bukuList, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const selGenre = document.getElementById('genre');
const boxRekom = document.getElementById('rekomendasi');
const listRekom = document.getElementById('rekom-list');

function tampilRekom() {
  const g = selGenre.value.toLowerCase();
  listRekom.innerHTML = '';
  if (!g) { boxRekom.hidden = true; return; }
  document.getElementById('rekom-genre').textContent = selGenre.value;
  const hasil = buku.filter(b => b.genre.toLowerCase() === g && parseInt(b.stok) > 0).slice(0, 4);
  hasil.forEach(b => {
    const card = document.createElement('div');
    card.className = 'card';
    const judul = document.createElement('strong'); judul.textContent = b.judul;
    const info = document.createElement('small');
    info.textContent = b.penulis + ' · Rp ' + Number(b.harga).toLocaleString('id-ID') + ' · Stok ' + b.stok;
    const btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'btn-kecil'; btn.textContent = '+ Tambah';
    btn.addEventListener('click', () => {
      const q = document.getElementById('qty-' + b.id);
      q.value = Math.min(parseInt(q.max) || 20, (parseInt(q.value) || 0) + 1);
      q.dispatchEvent(new Event('input'));
      q.closest('tr').scrollIntoView({ block: 'nearest' });
    });
    card.append(judul, document.createElement('br'), info, document.createElement('br'), btn);
    listRekom.appendChild(card);
  });
  boxRekom.hidden = hasil.length === 0;
}
selGenre.addEventListener('change', tampilRekom);
tampilRekom();

// Pencarian katalog secara langsung
const cari = document.getElementById('cari'), tidakAda = document.getElementById('tidak-ada');
function filterBuku() {
  const k = cari.value.trim().toLowerCase();
  let ada = 0;
  qtys.forEach(q => {
    const tr = q.closest('tr');
    const cocok = tr.textContent.toLowerCase().includes(k);
    tr.hidden = !cocok;
    if (cocok) ada++;
  });
  tidakAda.hidden = ada > 0;
}
cari.addEventListener('input', filterBuku);
</script>
</body>
</html>
