<?php
require __DIR__.'/../config.php'; require __DIR__.'/../layout.php'; require_role('dosen');
$pdo = db();
$krs = $pdo->query("SELECT * FROM kriteria WHERE jenis_input IN ('kuisioner','tes') AND is_active=1 ORDER BY kode")->fetchAll();
$kid = (int)($_GET['k'] ?? ($krs[0]['id'] ?? 0)); $kr = null;
foreach ($krs as $k) if ($k['id'] == $kid) $kr = $k;
if (!$kr && $krs) { $kr = $krs[0]; $kid = $kr['id']; }
$err = [];

// Parser teks dokumen: "Soal N: ...", opsi "A. ... (Bobot: n)", judul "N. Kriteria: Nama"
function parse_soal(string $t): array {
  $out = []; $cur = null; $nama = null;
  foreach (preg_split('/\R/', $t) as $line) {
    $line = trim($line); if ($line === '') continue;
    if (preg_match('/^\d+\.\s*Kriteria\s*:\s*(.+)$/iu', $line, $m)) { if ($cur) $out[] = $cur; $cur = null; $nama = trim($m[1]); }
    elseif (preg_match('/^Soal\s*\d+\s*[:.]\s*(.+)$/iu', $line, $m)) { if ($cur) $out[] = $cur; $cur = ['kriteria'=>$nama, 'q'=>$m[1], 'o'=>[]]; }
    elseif ($cur && preg_match('/^[A-Ea-e][.)]\s*(.+?)\s*\(\s*Bobot\s*:\s*(\d+)\s*\)\s*$/u', $line, $m)) $cur['o'][] = [$m[1], (int)$m[2]];
  }
  if ($cur) $out[] = $cur; return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && $kr) {
  $aksi = $_POST['aksi'] ?? '';
  if ($aksi === 'hapus') {
    $sid = (int)$_POST['id'];
    $c = $pdo->prepare('SELECT COUNT(*) FROM jawaban_soal WHERE soal_id=?'); $c->execute([$sid]);
    if ($c->fetchColumn()) flash('Soal sudah dijawab pelamar, tidak bisa dihapus. Ubah teksnya saja.');
    else { $pdo->prepare('DELETE FROM opsi_soal WHERE soal_id=?')->execute([$sid]); $pdo->prepare('DELETE FROM soal WHERE id=?')->execute([$sid]); flash('Soal dihapus.'); }
    go("/dosen/bank_soal.php?k=$kid");
  }
  if ($aksi === 'simpan') {
    $sid = (int)($_POST['id'] ?? 0); $q = trim($_POST['pertanyaan'] ?? ''); $ops = []; $tes = $kr['jenis_input'] === 'tes';
    if ($q === '') $err[] = 'Pertanyaan wajib diisi.';
    for ($i = 0; $i < 6; $i++) {
      $t = trim($_POST['teks'][$i] ?? ''); $oid = (int)($_POST['oid'][$i] ?? 0);
      if ($t === '') { if ($oid) $err[] = 'Opsi yang sudah ada tidak boleh dikosongkan.'; continue; }
      $b = $tes ? ((int)($_POST['benar'] ?? -1) === $i ? 1 : 0) : (int)($_POST['bobot'][$i] ?? 0);
      if (!$tes && ($b < 1 || $b > 5)) $err[] = 'Bobot opsi harus 1–5.';
      $ops[] = [$oid, $t, $b];
    }
    if (count($ops) < 2) $err[] = 'Minimal 2 opsi jawaban.';
    if ($tes && array_sum(array_column($ops, 2)) !== 1) $err[] = 'Pilih satu jawaban yang benar.';
    if (!$err) {
      $pdo->beginTransaction();
      if ($sid) $pdo->prepare('UPDATE soal SET pertanyaan=? WHERE id=? AND kriteria_id=?')->execute([$q, $sid, $kid]);
      else { $pdo->prepare('INSERT INTO soal(kriteria_id,pertanyaan) VALUES(?,?)')->execute([$kid, $q]); $sid = $pdo->lastInsertId(); }
      foreach ($ops as [$oid, $t, $b]) {
        if ($oid) $pdo->prepare('UPDATE opsi_soal SET teks=?, bobot=? WHERE id=? AND soal_id=?')->execute([$t, $b, $oid, $sid]);
        else $pdo->prepare('INSERT INTO opsi_soal(soal_id,teks,bobot) VALUES(?,?,?)')->execute([$sid, $t, $b]);
      }
      $pdo->commit(); flash('Soal disimpan.'); go("/dosen/bank_soal.php?k=$kid");
    }
  }
  if ($aksi === 'import') {
    $peta = []; foreach ($krs as $k) $peta[mb_strtolower($k['nama'])] = $k; $ok = 0; $lewat = 0; $gagal = [];
    $cek = $pdo->prepare('SELECT 1 FROM soal WHERE kriteria_id=? AND pertanyaan=?');
    foreach (parse_soal($_POST['teks_import'] ?? '') as $s) {
      $k = $s['kriteria'] ? ($peta[mb_strtolower($s['kriteria'])] ?? null) : $kr;
      if (!$k || $k['jenis_input'] !== 'kuisioner') { $gagal[] = $s['kriteria'] ?? 'tanpa judul kriteria'; continue; }
      $valid = count($s['o']) >= 2; foreach ($s['o'] as $o) if ($o[1] < 1 || $o[1] > 5) $valid = false;
      if (!$valid) { $gagal[] = mb_strimwidth($s['q'], 0, 40, '…'); continue; }
      $cek->execute([$k['id'], $s['q']]); if ($cek->fetch()) { $lewat++; continue; }
      $pdo->prepare('INSERT INTO soal(kriteria_id,pertanyaan) VALUES(?,?)')->execute([$k['id'], $s['q']]); $sid = $pdo->lastInsertId();
      foreach ($s['o'] as $o) $pdo->prepare('INSERT INTO opsi_soal(soal_id,teks,bobot) VALUES(?,?,?)')->execute([$sid, $o[0], $o[1]]);
      $ok++;
    }
    flash("Impor selesai: $ok soal masuk, $lewat dilewati (duplikat)".($gagal ? ', gagal: '.implode('; ', array_unique($gagal)) : '').'.'); go("/dosen/bank_soal.php?k=$kid");
  }
}

$edit = null; $daftar = [];
if ($kr) {
  $s = $pdo->prepare('SELECT * FROM soal WHERE kriteria_id=? ORDER BY id'); $s->execute([$kid]);
  foreach ($s->fetchAll() as $row) {
    $o = $pdo->prepare('SELECT * FROM opsi_soal WHERE soal_id=? ORDER BY id'); $o->execute([$row['id']]); $row['opsi'] = $o->fetchAll();
    $daftar[] = $row; if ((int)($_GET['edit'] ?? 0) === (int)$row['id']) $edit = $row;
  }
}
$inp = 'w-full px-3 py-2 rounded-lg border border-surface-container-high bg-white text-sm';
page_start('Bank Soal', 'bank'); ?>
<h1 class="text-2xl font-semibold text-primary">Bank Soal & Kuesioner</h1>
<?php if (!$kr): ?><p class="text-sm text-on-surface-variant">Belum ada kriteria berjenis kuisioner atau tes.</p><?php page_end(); exit; endif ?>
<div class="flex flex-wrap gap-2"><?php foreach ($krs as $k): ?>
 <a href="?k=<?= $k['id'] ?>" class="px-3 py-1.5 rounded-lg text-sm <?= $k['id'] == $kid ? 'bg-primary-container text-white font-semibold' : 'bg-surface-container-lowest text-on-surface-variant hover:bg-surface-container-high' ?>"><?= e($k['nama']) ?></a><?php endforeach ?></div>
<?php if ($err): ?><div class="bg-error-container text-on-error-container rounded-lg px-4 py-3 text-sm"><?= implode('<br>', array_map('e', array_unique($err))) ?></div><?php endif ?>

<p class="text-sm text-on-surface-variant"><?= count($daftar) ?> soal pada <b><?= e($kr['nama']) ?></b> • <?= $kr['jenis_input'] === 'tes' ? 'Tes: satu jawaban benar (benar = 1 poin, dinilai persentase).' : 'Kuisioner: tiap opsi punya bobot 1–5; nilai kriteria = jumlah bobot jawaban.' ?></p>
<?php foreach ($daftar as $n => $s): ?>
<section class="bg-surface-container-lowest rounded-xl p-5 shadow-sm">
 <div class="flex justify-between gap-3"><p class="font-semibold text-primary"><?= $n + 1 ?>. <?= e($s['pertanyaan']) ?></p>
  <div class="flex gap-1 shrink-0"><a href="?k=<?= $kid ?>&edit=<?= $s['id'] ?>#form" class="px-2 py-1 rounded text-sm hover:bg-surface-container">Edit</a>
   <form method="post" onsubmit="return confirm('Hapus soal ini?')"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="px-2 py-1 rounded text-sm text-error hover:bg-surface-container">Hapus</button></form></div></div>
 <ul class="mt-2 space-y-1 text-sm"><?php foreach ($s['opsi'] as $i => $o): ?>
  <li class="flex gap-2"><span class="font-semibold"><?= chr(65 + $i) ?>.</span><span class="flex-1"><?= e($o['teks']) ?></span>
   <span class="text-xs px-2 py-0.5 rounded-full h-fit <?= $kr['jenis_input'] === 'tes' && $o['bobot'] ? 'bg-secondary-container text-on-secondary-container' : 'bg-surface-container text-on-surface-variant' ?>"><?= $kr['jenis_input'] === 'tes' ? ($o['bobot'] ? 'benar' : '—') : 'bobot '.$o['bobot'] ?></span></li><?php endforeach ?></ul>
</section><?php endforeach ?>

<form method="post" id="form" class="bg-surface-container-lowest rounded-xl p-6 shadow-sm space-y-3">
 <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
 <h2 class="text-lg font-semibold text-primary"><?= $edit ? 'Edit Soal' : 'Tambah Soal' ?></h2>
 <?php if ($edit): ?><p class="text-xs text-on-surface-variant">Opsi yang sudah ada tidak bisa dihapus agar jawaban pelamar tetap konsisten; ubah teks/bobotnya saja.</p><?php endif ?>
 <textarea name="pertanyaan" rows="2" required placeholder="Pertanyaan" class="<?= $inp ?>"><?= e($_POST['pertanyaan'] ?? $edit['pertanyaan'] ?? '') ?></textarea>
 <?php for ($i = 0; $i < 6; $i++): $o = $edit['opsi'][$i] ?? null; if ($i >= 5 && !$o) break; ?>
  <div class="flex items-center gap-2"><input type="hidden" name="oid[<?= $i ?>]" value="<?= (int)($o['id'] ?? 0) ?>"><span class="w-5 font-semibold"><?= chr(65 + $i) ?></span>
   <input name="teks[<?= $i ?>]" value="<?= e($_POST['teks'][$i] ?? $o['teks'] ?? '') ?>" placeholder="Teks opsi <?= chr(65 + $i) ?><?= $i < 2 ? '' : ' (opsional)' ?>" class="<?= $inp ?>">
   <?php if ($kr['jenis_input'] === 'tes'): ?><label class="text-xs whitespace-nowrap"><input type="radio" name="benar" value="<?= $i ?>" <?= ($o['bobot'] ?? 0) == 1 ? 'checked' : '' ?>> benar</label>
   <?php else: ?><input type="number" min="1" max="5" name="bobot[<?= $i ?>]" value="<?= e($_POST['bobot'][$i] ?? $o['bobot'] ?? '') ?>" placeholder="1-5" class="w-20 <?= $inp ?>"><?php endif ?></div>
 <?php endfor ?>
 <div class="flex justify-end gap-2"><?php if ($edit): ?><a href="?k=<?= $kid ?>" class="px-4 py-2 rounded-lg text-sm text-on-surface-variant hover:bg-surface-container">Batal</a><?php endif ?>
  <button class="px-5 py-2.5 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-container">Simpan Soal</button></div>
</form>

<form method="post" class="bg-surface-container-lowest rounded-xl p-6 shadow-sm space-y-3">
 <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="aksi" value="import">
 <h2 class="text-lg font-semibold text-primary">Impor Soal Kuisioner dari Teks</h2>
 <p class="text-sm text-on-surface-variant">Tempel teks dengan format dokumen: judul <code>1. Kriteria: Kemampuan Teknis</code>, lalu <code>Soal 1: ...</code> dan opsi <code>A. ... (Bobot: 5)</code>. Judul kriteria dicocokkan dengan nama kriteria; soal yang sama persis dilewati.</p>
 <textarea name="teks_import" rows="8" class="<?= $inp ?> font-mono"></textarea>
 <div class="flex justify-end"><button class="px-5 py-2.5 rounded-lg bg-primary-container text-white text-sm font-semibold hover:bg-primary">Impor</button></div>
</form>
<?php page_end();
