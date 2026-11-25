<?php
require __DIR__.'/../config.php'; require __DIR__.'/../layout.php'; require_role('dosen');
$uid = user()['id']; $pdo = db(); $id = (int)($_GET['id'] ?? 0); $lw = null; $pelamar = 0; $cur = [];
if ($id) {
  $s = $pdo->prepare('SELECT * FROM lowongan WHERE id=? AND dosen_id=?'); $s->execute([$id, $uid]); $lw = $s->fetch();
  if (!$lw) go('/dosen/dashboard.php');
  $s = $pdo->prepare('SELECT COUNT(*) FROM lamaran WHERE lowongan_id=?'); $s->execute([$id]); $pelamar = (int)$s->fetchColumn();
  $s = $pdo->prepare('SELECT kriteria_id, bobot FROM lowongan_kriteria WHERE lowongan_id=?'); $s->execute([$id]); $cur = $s->fetchAll(PDO::FETCH_KEY_PAIR);
}
$s = $pdo->prepare('SELECT k.* FROM kriteria k LEFT JOIN lowongan_kriteria lk ON lk.kriteria_id=k.id AND lk.lowongan_id=? WHERE k.is_active=1 OR lk.lowongan_id IS NOT NULL ORDER BY k.kode');
$s->execute([$id]); $kr = $s->fetchAll();
$err = []; $v = $lw ?? ['kode'=>'','matkul'=>'','kuota'=>1,'deskripsi'=>'','deadline'=>'','status'=>'buka'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_ok()) $err[] = 'Sesi tidak valid.';
  $v = ['kode'=>trim($_POST['kode'] ?? ''), 'matkul'=>trim($_POST['matkul'] ?? ''), 'kuota'=>(int)($_POST['kuota'] ?? 0), 'deskripsi'=>trim($_POST['deskripsi'] ?? ''), 'deadline'=>$_POST['deadline'] ?? '', 'status'=>$_POST['status'] ?? 'buka'];
  if ($v['kode'] === '' || $v['matkul'] === '') $err[] = 'Kode dan mata kuliah wajib diisi.';
  if ($v['kuota'] < 1) $err[] = 'Kuota minimal 1.';
  if (!strtotime($v['deadline'])) $err[] = 'Deadline tidak valid.';
  $w = [];
  foreach ($kr as $k) {
    $pakai = $pelamar ? isset($cur[$k['id']]) : isset($_POST['pakai'][$k['id']]);
    $b = (float)($_POST['bobot'][$k['id']] ?? 0);
    if ($pakai) { if ($b <= 0) $err[] = "Bobot {$k['nama']} harus lebih dari 0."; $w[$k['id']] = $b; }
  }
  if (!$w) $err[] = 'Pilih minimal satu kriteria.';
  if (!$err) {
    $tot = array_sum($w); $pdo->beginTransaction();
    if ($id) $pdo->prepare('UPDATE lowongan SET kode=?,matkul=?,kuota=?,deskripsi=?,deadline=?,status=? WHERE id=?')->execute([...array_values($v), $id]);
    else { $pdo->prepare('INSERT INTO lowongan(kode,matkul,kuota,deskripsi,deadline,status,dosen_id) VALUES(?,?,?,?,?,?,?)')->execute([...array_values($v), $uid]); $id = $pdo->lastInsertId(); }
    $pdo->prepare('DELETE FROM lowongan_kriteria WHERE lowongan_id=?')->execute([$id]);
    $ins = $pdo->prepare('INSERT INTO lowongan_kriteria VALUES(?,?,?)');
    foreach ($w as $kid => $b) $ins->execute([$id, $kid, round($b / $tot, 4)]); // bobot dinormalisasi agar total = 1
    $pdo->commit(); flash('Lowongan disimpan.'); go('/dosen/dashboard.php');
  }
}
$inp = 'mt-1 w-full px-3 py-2.5 rounded-lg border border-surface-container-high bg-white font-normal focus:outline-none focus:ring-2 focus:ring-primary-container';
page_start($lw ? 'Edit Lowongan' : 'Buka Lowongan', 'dashboard'); ?>
<h1 class="text-2xl font-semibold text-primary"><?= $lw ? 'Edit' : 'Buka' ?> Lowongan Asisten</h1>
<?php if ($err): ?><div class="bg-error-container text-on-error-container rounded-lg px-4 py-3 text-sm"><?= implode('<br>', array_map('e', $err)) ?></div><?php endif ?>
<form method="post" class="space-y-5"><input type="hidden" name="csrf" value="<?= csrf() ?>">
 <section class="bg-surface-container-lowest rounded-xl p-6 shadow-sm grid sm:grid-cols-2 gap-4">
  <label class="text-sm font-semibold">Kode<input name="kode" value="<?= e($v['kode']) ?>" required class="<?= $inp ?>"></label>
  <label class="text-sm font-semibold">Mata Kuliah<input name="matkul" value="<?= e($v['matkul']) ?>" required class="<?= $inp ?>"></label>
  <label class="text-sm font-semibold">Kuota Asisten<input type="number" min="1" name="kuota" value="<?= (int)$v['kuota'] ?>" required class="<?= $inp ?>"></label>
  <label class="text-sm font-semibold">Batas Pendaftaran<input type="date" name="deadline" value="<?= e($v['deadline']) ?>" required class="<?= $inp ?>"></label>
  <label class="text-sm font-semibold sm:col-span-2">Deskripsi / Syarat<textarea name="deskripsi" rows="3" class="<?= $inp ?>"><?= e($v['deskripsi']) ?></textarea></label>
  <label class="text-sm font-semibold">Status<select name="status" class="<?= $inp ?>"><option value="buka" <?= $v['status'] === 'buka' ? 'selected' : '' ?>>Buka</option><option value="tutup" <?= $v['status'] === 'tutup' ? 'selected' : '' ?>>Tutup</option></select></label>
 </section>
 <section class="bg-surface-container-lowest rounded-xl p-6 shadow-sm">
  <h2 class="text-lg font-semibold text-primary">Kriteria & Bobot</h2>
  <p class="text-sm text-on-surface-variant mb-3">Isi bobot sebagai angka bebas; sistem menormalkannya otomatis sehingga total = 100%.<?= $pelamar ? ' Kriteria dikunci karena sudah ada pelamar, hanya bobot yang bisa diubah.' : '' ?></p>
  <div class="space-y-2">
  <?php foreach ($kr as $k): $on = $id ? isset($cur[$k['id']]) : true; $b = $_POST['bobot'][$k['id']] ?? ($cur ? round(($cur[$k['id']] ?? 0) * 100, 2) : 12.5); ?>
   <div class="flex items-center gap-3 p-3 rounded-lg bg-surface text-sm">
    <input type="checkbox" name="pakai[<?= $k['id'] ?>]" <?= $on ? 'checked' : '' ?> <?= $pelamar ? 'disabled' : '' ?>>
    <span class="flex-1"><b><?= e($k['kode']) ?></b> <?= e($k['nama']) ?> <span class="text-xs text-on-surface-variant">(<?= $k['tipe'] ?>)</span></span>
    <input type="number" step="0.01" min="0" name="bobot[<?= $k['id'] ?>]" value="<?= e($b) ?>" class="w-24 px-2 py-1.5 rounded border border-surface-container-high bg-white"> 
   </div>
  <?php endforeach ?></div>
 </section>
 <div class="flex justify-end gap-2"><a href="dashboard.php" class="px-4 py-2.5 rounded-lg text-sm font-semibold text-on-surface-variant hover:bg-surface-container">Batal</a>
  <button class="px-5 py-2.5 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-container">Simpan</button></div>
</form>
<?php page_end();
