<?php
require __DIR__.'/../config.php'; require __DIR__.'/../layout.php'; require_role('dosen');
$uid = user()['id']; $pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
  $id = (int)($_POST['id'] ?? 0);
  $s = $pdo->prepare('SELECT (SELECT COUNT(*) FROM lamaran WHERE lowongan_id=l.id) n FROM lowongan l WHERE id=? AND dosen_id=?'); $s->execute([$id, $uid]); $row = $s->fetch();
  if ($row) {
    if (($_POST['aksi'] ?? '') === 'toggle') { $pdo->prepare("UPDATE lowongan SET status=IF(status='buka','tutup','buka') WHERE id=?")->execute([$id]); flash('Status lowongan diubah.'); }
    elseif (($_POST['aksi'] ?? '') === 'hapus') {
      if ($row['n'] == 0) { $pdo->prepare('DELETE FROM lowongan_kriteria WHERE lowongan_id=?')->execute([$id]); $pdo->prepare('DELETE FROM lowongan WHERE id=?')->execute([$id]); flash('Lowongan dihapus.'); }
      else flash('Lowongan yang sudah punya pelamar tidak bisa dihapus. Tutup saja.');
    }
  }
  go('/dosen/dashboard.php');
}
$s = $pdo->prepare("SELECT l.*, (SELECT COUNT(*) FROM lamaran WHERE lowongan_id=l.id) pelamar,
 (SELECT COUNT(*) FROM jawaban_kriteria jk JOIN lamaran la ON la.id=jk.lamaran_id WHERE la.lowongan_id=l.id AND jk.status_verifikasi='pending') pending
 FROM lowongan l WHERE l.dosen_id=? ORDER BY l.id DESC");
$s->execute([$uid]); $rows = $s->fetchAll();
page_start('Lowongan Saya', 'dashboard'); ?>
<div class="flex flex-wrap items-center justify-between gap-3">
 <div><h1 class="text-2xl font-semibold text-primary">Pengumuman Lowongan Asisten</h1>
 <p class="text-sm text-on-surface-variant">Kelola mata kuliah yang membuka penerimaan asisten dosen.</p></div>
 <a href="<?= BASE ?>/dosen/lowongan_form.php" class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold hover:bg-primary-container"><?= ic('add',18) ?> Buka Lowongan</a>
</div>
<div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-x-auto">
<table class="w-full text-sm"><thead class="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
 <tr><th class="p-3">Mata Kuliah</th><th class="p-3">Kuota</th><th class="p-3">Pelamar</th><th class="p-3">Deadline</th><th class="p-3">Status</th><th class="p-3">Aksi</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6" class="p-5 text-on-surface-variant">Belum ada lowongan.</td></tr><?php endif ?>
<?php foreach ($rows as $r): ?>
 <tr class="border-t border-surface-container">
  <td class="p-3"><b class="text-primary"><?= e($r['matkul']) ?></b><div class="text-xs text-on-surface-variant"><?= e($r['kode']) ?></div></td>
  <td class="p-3"><?= (int)$r['kuota'] ?></td>
  <td class="p-3"><?= (int)$r['pelamar'] ?><?php if ($r['pending']): ?> <span class="text-xs px-2 py-0.5 rounded-full bg-error-container text-on-error-container"><?= (int)$r['pending'] ?> bukti baru</span><?php endif ?></td>
  <td class="p-3"><?= date('d M Y', strtotime($r['deadline'])) ?></td>
  <td class="p-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $r['status'] === 'buka' ? 'bg-secondary-container text-on-secondary-container' : 'bg-surface-container text-on-surface-variant' ?>"><?= $r['status'] ?></span></td>
  <td class="p-3"><div class="flex flex-wrap gap-1 items-center">
   <a class="px-2 py-1 rounded bg-surface-container text-primary font-semibold" href="verifikasi.php?id=<?= $r['id'] ?>">Verifikasi</a>
   <a class="px-2 py-1 rounded bg-primary-container text-white font-semibold" href="hasil.php?id=<?= $r['id'] ?>">Hasil MOORA</a>
   <a class="px-2 py-1 rounded hover:bg-surface-container" href="lowongan_form.php?id=<?= $r['id'] ?>">Edit</a>
   <?php foreach (['toggle' => $r['status'] === 'buka' ? 'Tutup' : 'Buka', 'hapus' => 'Hapus'] as $a => $lbl): ?>
    <form method="post" <?= $a === 'hapus' ? 'onsubmit="return confirm(\'Hapus lowongan ini?\')"' : '' ?>><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="aksi" value="<?= $a ?>">
     <button class="px-2 py-1 rounded hover:bg-surface-container <?= $a === 'hapus' ? 'text-error' : '' ?>"><?= $lbl ?></button></form>
   <?php endforeach ?>
  </div></td></tr>
<?php endforeach ?></tbody></table></div>
<?php page_end();
