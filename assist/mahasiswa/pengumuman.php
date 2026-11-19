<?php
require __DIR__.'/../config.php'; require __DIR__.'/../layout.php'; require_role('mahasiswa');
$pdo = db(); $uid = user()['id'];
// Hanya lowongan yang Anda lamar dan hasilnya sudah ditetapkan dosen
$s = $pdo->prepare("SELECT l.id, l.kode, l.matkul, l.kuota, la.status FROM lowongan l JOIN lamaran la ON la.lowongan_id=l.id AND la.user_id=?
 WHERE EXISTS (SELECT 1 FROM lamaran x WHERE x.lowongan_id=l.id AND x.status IN ('diterima','cadangan')) ORDER BY l.id DESC");
$s->execute([$uid]); $rows = $s->fetchAll();
$q = $pdo->prepare("SELECT u.nama, u.nim FROM lamaran la JOIN users u ON u.id=la.user_id WHERE la.lowongan_id=? AND la.status='diterima' ORDER BY u.nama");
$pesan = ['diterima'=>['Selamat, Anda diterima sebagai asisten dosen.', 'bg-secondary-container text-on-secondary-container'],
          'cadangan'=>['Anda berada pada daftar cadangan.', 'bg-surface-container text-primary'],
          'ditolak'=>['Mohon maaf, lamaran Anda tidak lolos verifikasi berkas.', 'bg-error-container text-on-error-container'],
          'lolos_berkas'=>['Anda lolos verifikasi berkas namun belum masuk daftar diterima.', 'bg-surface-container text-on-surface-variant'],
          'verifikasi'=>['Lamaran Anda belum diverifikasi.', 'bg-surface-container text-on-surface-variant']];
page_start('Pengumuman', 'pengumuman'); ?>
<h1 class="text-2xl font-semibold text-primary">Pengumuman Hasil Seleksi</h1>
<?php if (!$rows): ?><p class="bg-surface-container-lowest rounded-xl p-5 text-sm text-on-surface-variant shadow-sm">Belum ada hasil seleksi yang diumumkan untuk lowongan yang Anda lamar.</p><?php endif ?>
<?php foreach ($rows as $r): $q->execute([$r['id']]); $ok = $q->fetchAll(); ?>
<section class="bg-surface-container-lowest rounded-xl p-5 shadow-sm space-y-3">
 <div><span class="font-semibold text-primary"><?= e($r['matkul']) ?></span> <span class="text-xs px-2 py-0.5 rounded bg-surface-container text-secondary"><?= e($r['kode']) ?></span>
  <p class="text-sm text-on-surface-variant">Kuota <?= (int)$r['kuota'] ?> asisten</p></div>
 <p class="rounded-lg p-3 text-sm font-semibold <?= $pesan[$r['status']][1] ?>"><?= $pesan[$r['status']][0] ?></p>
 <div><p class="text-xs font-semibold uppercase text-on-surface-variant mb-1">Mahasiswa diterima</p>
  <ul class="text-sm space-y-1"><?php foreach ($ok as $o): ?><li><?= e($o['nama']) ?> <span class="text-on-surface-variant">• <?= e($o['nim']) ?></span></li><?php endforeach ?></ul></div>
</section>
<?php endforeach; page_end();
