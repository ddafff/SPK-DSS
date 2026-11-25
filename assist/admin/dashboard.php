<?php
require __DIR__.'/../config.php'; require __DIR__.'/../layout.php'; require_role('admin');
$pdo = db();
$total_dosen  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='dosen'")->fetchColumn();
$total_mhs    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='mahasiswa'")->fetchColumn();
$total_lowongan = $pdo->query("SELECT COUNT(*) FROM lowongan")->fetchColumn();
$total_lamaran  = $pdo->query("SELECT COUNT(*) FROM lamaran")->fetchColumn();
page_start('Dashboard Admin', 'dashboard'); ?>
<div>
 <h1 class="text-2xl font-semibold text-primary">Dashboard Admin</h1>
 <p class="text-sm text-on-surface-variant mt-1">Selamat datang di panel administrasi ASSIST SPK.</p>
</div>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
 <?php
 $cards = [
   ['manage_accounts','Data Dosen',$total_dosen,'dosen terdaftar'],
   ['school','Mahasiswa',$total_mhs,'mahasiswa terdaftar'],
   ['work','Lowongan',$total_lowongan,'lowongan dibuat'],
   ['description','Lamaran',$total_lamaran,'lamaran masuk'],
 ];
 foreach ($cards as [$ico,$lbl,$val,$sub]): ?>
 <div class="bg-surface-container-lowest rounded-xl p-5 shadow-sm flex flex-col gap-2">
  <span class="text-secondary"><?= ic($ico,24) ?></span>
  <div class="text-3xl font-bold text-primary"><?= $val ?></div>
  <div class="text-sm font-semibold"><?= $lbl ?></div>
  <div class="text-xs text-on-surface-variant"><?= $sub ?></div>
 </div>
 <?php endforeach ?>
</div>
<div class="bg-surface-container-lowest rounded-xl p-6 shadow-sm">
 <h2 class="text-lg font-semibold text-primary mb-4">Aksi Cepat</h2>
 <div class="flex flex-wrap gap-3">
  <a href="<?= BASE ?>/admin/dosen.php" class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold hover:bg-primary-container"><?= ic('person_add',18) ?> Tambah Dosen</a>
 </div>
</div>
<?php page_end();
