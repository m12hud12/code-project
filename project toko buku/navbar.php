<?php $nu = userLogin(); ?>
<nav class="navbar">
  <strong>📚 Toko Buku Online</strong>
  <span>
  <?php if ($nu): ?>
    <?= htmlspecialchars($nu['nama']) ?> <small class="badge"><?= $nu['role'] === 'admin' ? 'Admin' : 'User' ?></small>
    <?php if ($nu['role'] === 'admin'): ?>
      <a href="admin.php">Panel Admin</a><a href="dashboard.php">Transaksi</a>
    <?php else: ?>
      <a href="index.php">Beli Buku</a><a href="dashboard.php">Riwayat</a>
    <?php endif; ?>
    <a href="logout.php">Logout</a>
  <?php endif; ?>
  </span>
</nav>
