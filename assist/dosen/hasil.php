<?php
require __DIR__.'/../config.php'; require __DIR__.'/../layout.php'; require_role('dosen');
$uid = user()['id']; $pdo = db(); $id = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare('SELECT * FROM lowongan WHERE id=? AND dosen_id=?'); $s->execute([$id, $uid]); $lw = $s->fetch();
if (!$lw) go('/dosen/dashboard.php');
$s = $pdo->prepare('SELECT k.*, lk.bobot FROM kriteria k JOIN lowongan_kriteria lk ON lk.kriteria_id=k.id WHERE lk.lowongan_id=? ORDER BY k.kode'); $s->execute([$id]); $kr = $s->fetchAll();
$s = $pdo->prepare('SELECT la.id, la.status, u.nama, u.nim FROM lamaran la JOIN users u ON u.id=la.user_id WHERE la.lowongan_id=?'); $s->execute([$id]); $pel = $s->fetchAll();
$s = $pdo->prepare('SELECT jk.* FROM jawaban_kriteria jk JOIN lamaran la ON la.id=jk.lamaran_id WHERE la.lowongan_id=?'); $s->execute([$id]);
$X = []; $ok = []; foreach ($s->fetchAll() as $j) { $X[$j['lamaran_id']][$j['kriteria_id']] = (float)$j['nilai']; if (in_array($j['status_verifikasi'], ['pending', 'ditolak'])) $ok[$j['lamaran_id']] = false; }

// Pelamar yang diperingkat: semua kriteria terisi dan tidak ada bukti pending/ditolak
$layak = []; $belum = [];
foreach ($pel as $p) {
  $lengkap = true; foreach ($kr as $k) if (!isset($X[$p['id']][$k['id']])) $lengkap = false;
  if ($lengkap && ($ok[$p['id']] ?? true)) $layak[] = $p; else $belum[] = $p;
}
// ===== MOORA =====
$W = []; $tot = array_sum(array_column($kr, 'bobot')); foreach ($kr as $k) $W[$k['id']] = $k['bobot'] / $tot;
$den = []; foreach ($kr as $k) { $sq = 0; foreach ($layak as $p) $sq += $X[$p['id']][$k['id']] ** 2; $den[$k['id']] = sqrt($sq); }
foreach ($layak as &$p) {                                   // 1) normalisasi  2) Yi = Σ benefit − Σ cost
  $y = 0; foreach ($kr as $k) { $n = $den[$k['id']] > 0 ? $X[$p['id']][$k['id']] / $den[$k['id']] : 0; $y += ($k['tipe'] === 'cost' ? -1 : 1) * $W[$k['id']] * $n; }
  $p['y'] = $y;
} unset($p);
usort($layak, fn($a, $b) => $b['y'] <=> $a['y']);            // 3) ranking

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && $layak) {
  $u = $pdo->prepare('UPDATE lamaran SET status=? WHERE id=?');
  foreach ($layak as $i => $p) $u->execute([$i < $lw['kuota'] ? 'diterima' : 'cadangan', $p['id']]);
  flash('Hasil seleksi ditetapkan: '.min($lw['kuota'], count($layak)).' diterima, sisanya cadangan.'); go("/dosen/hasil.php?id=$id");
}
page_start('Hasil MOORA', 'dashboard'); ?>
<a href="dashboard.php" class="text-sm text-secondary hover:underline">← Kembali</a>
<h1 class="text-2xl font-semibold text-primary">Hasil Perankingan MOORA • <?= e($lw['matkul']) ?></h1>
<p class="text-sm text-on-surface-variant">Kuota <?= (int)$lw['kuota'] ?> asisten. Hanya pelamar dengan seluruh bukti berstatus valid yang diperingkat.</p>
<div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-x-auto"><table class="w-full text-sm">
 <thead class="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant"><tr><th class="p-3">Rank</th><th class="p-3">Pelamar</th>
  <?php foreach ($kr as $k): ?><th class="p-3"><?= e($k['kode']) ?><div class="normal-case font-normal"><?= round($W[$k['id']] * 100, 1) ?>% <?= $k['tipe'] === 'cost' ? '(cost)' : '' ?></div></th><?php endforeach ?>
  <th class="p-3">Yi</th><th class="p-3">Status</th></tr></thead><tbody>
 <?php if (!$layak): ?><tr><td colspan="<?= count($kr) + 4 ?>" class="p-5 text-on-surface-variant">Belum ada pelamar yang siap diperingkat.</td></tr><?php endif ?>
 <?php foreach ($layak as $i => $p): ?><tr class="border-t border-surface-container <?= $i < $lw['kuota'] ? 'bg-surface-container-low' : '' ?>">
  <td class="p-3 font-bold text-primary"><?= $i + 1 ?></td><td class="p-3"><?= e($p['nama']) ?><div class="text-xs text-on-surface-variant"><?= e($p['nim']) ?></div></td>
  <?php foreach ($kr as $k): ?><td class="p-3"><?= round($X[$p['id']][$k['id']], 2) ?></td><?php endforeach ?>
  <td class="p-3 font-semibold"><?= number_format($p['y'], 6) ?></td><td class="p-3"><?= e(STATUS_LAMARAN[$p['status']]) ?></td></tr><?php endforeach ?>
</tbody></table></div>
<?php if ($belum): ?><p class="text-sm text-on-surface-variant">Belum diperingkat (bukti belum valid/lengkap): <?= e(implode(', ', array_column($belum, 'nama'))) ?></p><?php endif ?>
<?php if ($layak): ?><form method="post" onsubmit="return confirm('Tetapkan hasil seleksi? Status pelamar akan diperbarui.')"><input type="hidden" name="csrf" value="<?= csrf() ?>">
 <button class="px-5 py-2.5 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-container">Tetapkan Hasil Seleksi</button></form><?php endif ?>
<?php page_end();
