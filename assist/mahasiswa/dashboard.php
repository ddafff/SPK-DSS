<?php
require __DIR__.'/../config.php'; require __DIR__.'/../layout.php'; require_role('mahasiswa');
$s = db()->prepare('SELECT * FROM users WHERE id=?'); $s->execute([user()['id']]); $me = $s->fetch();
$semua = daftar_lowongan($me['id']);
$terkini = array_slice(array_filter($semua, fn($r) => !$r['sudah']), 0, 3);
$s = db()->prepare('SELECT la.*, l.kode, l.matkul, d.nama dosen FROM lamaran la JOIN lowongan l ON l.id=la.lowongan_id LEFT JOIN users d ON d.id=l.dosen_id WHERE la.user_id=? ORDER BY la.created_at DESC');
$s->execute([$me['id']]); $lamaran = $s->fetchAll();
page_start('Dashboard', 'dashboard'); ?>
<section class="bg-surface-container-lowest rounded-xl p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
 <div>
  <p class="text-xs font-semibold uppercase tracking-wider text-secondary"><?= ic('verified',16) ?> Sistem Pendukung Keputusan Seleksi</p>
  <h1 class="text-2xl font-semibold text-primary mt-1">Selamat datang, <?= e($me['nama']) ?></h1>
  <p class="text-sm text-on-surface-variant mt-1">NIM <?= e($me['nim']) ?> • <?= e($me['prodi']) ?> • Semester <?= (int)$me['semester'] ?></p>
 </div>
 <a href="<?= BASE ?>/mahasiswa/lowongan.php" class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold hover:bg-primary-container"><?= ic('search',18) ?> Eksplor Lowongan</a>
</section>

<section class="grid grid-cols-1 sm:grid-cols-2 gap-4">
 <div class="bg-surface-container-lowest rounded-xl p-5 shadow-sm"><p class="text-xs font-semibold uppercase text-on-surface-variant">Lowongan Tersedia</p><p class="text-3xl font-bold text-primary mt-1"><?= count($semua) ?></p></div>
 <div class="bg-surface-container-lowest rounded-xl p-5 shadow-sm"><p class="text-xs font-semibold uppercase text-on-surface-variant">Lamaran Saya</p><p class="text-3xl font-bold text-primary mt-1"><?= count($lamaran) ?></p></div>
</section>

<section class="space-y-3">
 <h2 class="text-lg font-semibold text-primary">Status Lamaran Saya</h2>
 <?php if (!$lamaran): ?><p class="bg-surface-container-lowest rounded-xl p-5 text-sm text-on-surface-variant shadow-sm">Anda belum mengirim lamaran.</p><?php endif ?>
 <?php foreach ($lamaran as $l): ?>
  <div class="bg-surface-container-lowest rounded-xl p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-2">
   <div><span class="font-semibold text-primary"><?= e($l['matkul']) ?></span> <span class="text-xs px-2 py-0.5 rounded bg-surface-container text-secondary"><?= e($l['kode']) ?></span>
    <p class="text-sm text-on-surface-variant">Dosen: <?= e($l['dosen'] ?? '-') ?> • Dikirim <?= date('d M Y', strtotime($l['created_at'])) ?></p></div>
   <span class="self-start px-3 py-1 rounded-full text-xs font-semibold bg-secondary-container text-on-secondary-container"><?= e(STATUS_LAMARAN[$l['status']]) ?></span>
  </div>
 <?php endforeach ?>
</section>

<section class="space-y-3">
 <h2 class="text-lg font-semibold text-primary">Lowongan Asisten Dosen Terkini</h2>
 <?php if (!$terkini): ?><p class="text-sm text-on-surface-variant">Tidak ada lowongan baru yang bisa dilamar.</p><?php endif ?>
 <div class="grid grid-cols-1 md:grid-cols-3 gap-4"><?php foreach ($terkini as $r) kartu_lowongan($r); ?></div>
</section>
<?php page_end();
