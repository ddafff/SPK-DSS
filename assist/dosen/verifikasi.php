<?php
require __DIR__.'/../config.php'; require __DIR__.'/../layout.php'; require_role('dosen');
$uid = user()['id']; $pdo = db(); $id = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare('SELECT * FROM lowongan WHERE id=? AND dosen_id=?'); $s->execute([$id, $uid]); $lw = $s->fetch();
if (!$lw) go('/dosen/dashboard.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && in_array($_POST['aksi'] ?? '', ['valid', 'ditolak'])) {
  $s = $pdo->prepare('UPDATE jawaban_kriteria jk JOIN lamaran la ON la.id=jk.lamaran_id SET jk.status_verifikasi=? WHERE jk.id=? AND la.lowongan_id=? AND jk.bukti IS NOT NULL');
  $s->execute([$_POST['aksi'], (int)$_POST['jk'], $id]);
  $lid = (int)$_POST['lamaran'];
  $c = $pdo->prepare("SELECT SUM(status_verifikasi='ditolak') tolak, SUM(status_verifikasi='pending') pend FROM jawaban_kriteria WHERE lamaran_id=?"); $c->execute([$lid]); $c = $c->fetch();
  $st = $c['tolak'] > 0 ? 'ditolak' : ($c['pend'] > 0 ? 'verifikasi' : 'lolos_berkas');
  $pdo->prepare("UPDATE lamaran SET status=? WHERE id=? AND lowongan_id=? AND status IN ('verifikasi','lolos_berkas','ditolak')")->execute([$st, $lid, $id]);
  go("/dosen/verifikasi.php?id=$id");
}
$s = $pdo->prepare('SELECT la.id, la.status, u.nama, u.nim FROM lamaran la JOIN users u ON u.id=la.user_id WHERE la.lowongan_id=? ORDER BY la.created_at'); $s->execute([$id]); $pel = $s->fetchAll();
$s = $pdo->prepare('SELECT jk.*, k.nama FROM jawaban_kriteria jk JOIN kriteria k ON k.id=jk.kriteria_id JOIN lamaran la ON la.id=jk.lamaran_id WHERE la.lowongan_id=? AND jk.bukti IS NOT NULL ORDER BY k.kode'); $s->execute([$id]);
$bukti = []; foreach ($s->fetchAll() as $b) $bukti[$b['lamaran_id']][] = $b;
$warna = ['pending'=>'bg-surface-container text-on-surface-variant', 'valid'=>'bg-secondary-container text-on-secondary-container', 'ditolak'=>'bg-error-container text-on-error-container'];
page_start('Verifikasi Berkas', 'dashboard'); ?>
<a href="dashboard.php" class="text-sm text-secondary hover:underline">← Kembali</a>
<h1 class="text-2xl font-semibold text-primary">Verifikasi Berkas • <?= e($lw['matkul']) ?></h1>
<?php if (!$pel): ?><p class="text-sm text-on-surface-variant">Belum ada pelamar.</p><?php endif ?>
<?php foreach ($pel as $p): ?>
<section class="bg-surface-container-lowest rounded-xl p-5 shadow-sm">
 <div class="flex justify-between items-center mb-3"><div><b class="text-primary"><?= e($p['nama']) ?></b> <span class="text-sm text-on-surface-variant">• <?= e($p['nim']) ?></span></div>
  <span class="text-xs font-semibold px-3 py-1 rounded-full bg-secondary-container text-on-secondary-container"><?= e(STATUS_LAMARAN[$p['status']]) ?></span></div>
 <?php foreach ($bukti[$p['id']] ?? [] as $b): ?>
  <div class="flex flex-wrap items-center gap-3 py-2 border-t border-surface-container text-sm">
   <span class="w-56"><?= e($b['nama']) ?>: <b><?= e(rtrim(rtrim($b['nilai'], '0'), '.')) ?></b></span>
   <a target="_blank" href="<?= BASE ?>/uploads/<?= e($b['bukti']) ?>" class="px-2 py-1 rounded bg-surface-container text-primary font-semibold">Lihat bukti</a>
   <span class="px-2 py-0.5 rounded-full text-xs <?= $warna[$b['status_verifikasi']] ?>"><?= $b['status_verifikasi'] ?></span>
   <?php foreach (['valid' => 'Valid', 'ditolak' => 'Tolak'] as $a => $l): ?>
    <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="jk" value="<?= $b['id'] ?>"><input type="hidden" name="lamaran" value="<?= $p['id'] ?>"><input type="hidden" name="aksi" value="<?= $a ?>">
     <button class="px-2 py-1 rounded hover:bg-surface-container <?= $a === 'ditolak' ? 'text-error' : '' ?>"><?= $l ?></button></form>
   <?php endforeach ?>
  </div>
 <?php endforeach ?>
</section>
<?php endforeach; page_end();
