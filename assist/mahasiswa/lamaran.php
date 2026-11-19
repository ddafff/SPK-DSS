<?php
require __DIR__.'/../config.php'; require __DIR__.'/../layout.php'; require_role('mahasiswa');
$pdo = db(); $uid = user()['id'];
$s = $pdo->prepare('SELECT la.*, l.kode, l.matkul, d.nama dosen FROM lamaran la JOIN lowongan l ON l.id=la.lowongan_id LEFT JOIN users d ON d.id=l.dosen_id WHERE la.user_id=? ORDER BY la.created_at DESC');
$s->execute([$uid]); $lamaran = $s->fetchAll();
$s = $pdo->prepare('SELECT jk.*, k.nama, k.jenis_input FROM jawaban_kriteria jk JOIN kriteria k ON k.id=jk.kriteria_id JOIN lamaran la ON la.id=jk.lamaran_id WHERE la.user_id=? ORDER BY k.kode');
$s->execute([$uid]); $jawab = []; foreach ($s->fetchAll() as $j) $jawab[$j['lamaran_id']][] = $j;
$lbl = ['pending'=>['Menunggu verifikasi', 'bg-surface-container text-on-surface-variant'], 'valid'=>['Bukti valid', 'bg-secondary-container text-on-secondary-container'], 'ditolak'=>['Bukti ditolak', 'bg-error-container text-on-error-container'], 'otomatis'=>['Dinilai otomatis', 'bg-surface-container text-secondary']];
page_start('Lamaran Saya', 'lamaran'); ?>
<h1 class="text-2xl font-semibold text-primary">Lamaran Saya</h1>
<?php if (!$lamaran): ?><p class="bg-surface-container-lowest rounded-xl p-5 text-sm text-on-surface-variant shadow-sm">Anda belum mengirim lamaran. <a class="font-semibold text-primary hover:underline" href="lowongan.php">Lihat lowongan</a></p><?php endif ?>
<?php foreach ($lamaran as $l): ?>
<section class="bg-surface-container-lowest rounded-xl p-5 shadow-sm">
 <div class="flex flex-wrap justify-between gap-2 mb-3">
  <div><span class="font-semibold text-primary"><?= e($l['matkul']) ?></span> <span class="text-xs px-2 py-0.5 rounded bg-surface-container text-secondary"><?= e($l['kode']) ?></span>
   <p class="text-sm text-on-surface-variant">Dosen: <?= e($l['dosen'] ?? '-') ?> • Dikirim <?= date('d M Y H:i', strtotime($l['created_at'])) ?></p></div>
  <span class="self-start px-3 py-1 rounded-full text-xs font-semibold bg-secondary-container text-on-secondary-container"><?= e(STATUS_LAMARAN[$l['status']]) ?></span>
 </div>
 <div class="divide-y divide-surface-container text-sm">
 <?php foreach ($jawab[$l['id']] ?? [] as $j): ?>
  <div class="flex flex-wrap items-center justify-between gap-2 py-2"><span><?= e($j['nama']) ?></span>
   <span class="flex items-center gap-2"><b><?= e(rtrim(rtrim($j['nilai'], '0'), '.')) ?></b><span class="px-2 py-0.5 rounded-full text-xs <?= $lbl[$j['status_verifikasi']][1] ?>"><?= $lbl[$j['status_verifikasi']][0] ?></span></span></div>
 <?php endforeach ?></div>
 <?php if ($l['status'] === 'ditolak'): ?><p class="mt-3 text-sm text-on-error-container bg-error-container rounded-lg p-3">Salah satu bukti Anda ditolak dosen sehingga lamaran tidak diproses lebih lanjut.</p><?php endif ?>
</section>
<?php endforeach; page_end();
