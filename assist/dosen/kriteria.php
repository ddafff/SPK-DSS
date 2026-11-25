<?php
require __DIR__.'/../config.php'; require __DIR__.'/../layout.php'; require_role('dosen');
$pdo = db(); $err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
  if (($_POST['aksi'] ?? '') === 'toggle') { $pdo->prepare('UPDATE kriteria SET is_active=1-is_active WHERE id=?')->execute([(int)$_POST['id']]); flash('Status kriteria diubah.'); go('/dosen/kriteria.php'); }
  $nama = trim($_POST['nama'] ?? ''); $jenis = $_POST['jenis_input'] ?? ''; $tipe = $_POST['tipe'] ?? '';
  $min = (float)($_POST['min_val'] ?? 0); $max = (float)($_POST['max_val'] ?? 100);
  if ($nama === '') $err[] = 'Nama kriteria wajib diisi.';
  if (!in_array($jenis, ['angka_bukti','huruf_bukti','kuisioner','tes'])) $err[] = 'Jenis input tidak valid.';
  if (!in_array($tipe, ['benefit','cost'])) $err[] = 'Tipe tidak valid.';
  if ($jenis === 'angka_bukti' && $min >= $max) $err[] = 'Nilai minimum harus lebih kecil dari maksimum.';
  if (!$err) {
    $kode = 'C'.($pdo->query('SELECT COUNT(*) FROM kriteria')->fetchColumn() + 1);
    $pdo->prepare('INSERT INTO kriteria(kode,nama,jenis_input,satuan,min_val,max_val,tipe) VALUES(?,?,?,?,?,?,?)')->execute([$kode, $nama, $jenis, trim($_POST['satuan'] ?? '') ?: null, $min, $max, $tipe]);
    flash("Kriteria $kode ditambahkan. Pasang ke lowongan lewat menu Edit lowongan."); go('/dosen/kriteria.php');
  }
}
$rows = $pdo->query('SELECT k.*, (SELECT COUNT(*) FROM lowongan_kriteria WHERE kriteria_id=k.id) dipakai FROM kriteria k ORDER BY k.kode')->fetchAll();
$jenisLbl = ['angka_bukti'=>'Angka + bukti','huruf_bukti'=>'Nilai huruf + bukti','kuisioner'=>'Kuisioner pilgan','tes'=>'Tes pemahaman'];
$inp = 'mt-1 w-full px-3 py-2.5 rounded-lg border border-surface-container-high bg-white font-normal';
page_start('Kriteria', 'kriteria'); ?>
<h1 class="text-2xl font-semibold text-primary">Manajemen Kriteria</h1>
<?php if ($err): ?><div class="bg-error-container text-on-error-container rounded-lg px-4 py-3 text-sm"><?= implode('<br>', array_map('e', $err)) ?></div><?php endif ?>
<div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-x-auto"><table class="w-full text-sm">
 <thead class="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant"><tr><th class="p-3">Kode</th><th class="p-3">Kriteria</th><th class="p-3">Jenis Input</th><th class="p-3">Tipe</th><th class="p-3">Dipakai</th><th class="p-3"></th></tr></thead><tbody>
 <?php foreach ($rows as $r): ?><tr class="border-t border-surface-container <?= $r['is_active'] ? '' : 'opacity-50' ?>">
  <td class="p-3 font-semibold"><?= e($r['kode']) ?></td><td class="p-3"><?= e($r['nama']) ?></td><td class="p-3"><?= $jenisLbl[$r['jenis_input']] ?></td><td class="p-3"><?= $r['tipe'] ?></td><td class="p-3"><?= (int)$r['dipakai'] ?> lowongan</td>
  <td class="p-3"><form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="aksi" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>">
   <button class="px-2 py-1 rounded hover:bg-surface-container <?= $r['is_active'] ? 'text-error' : 'text-primary' ?>"><?= $r['is_active'] ? 'Hapus' : 'Aktifkan' ?></button></form></td></tr><?php endforeach ?></tbody></table></div>
<p class="text-xs text-on-surface-variant">"Hapus" menonaktifkan kriteria (data lamaran lama tetap aman) dan kriteria itu tidak muncul lagi di formulir lowongan baru.</p>
<form method="post" class="bg-surface-container-lowest rounded-xl p-6 shadow-sm grid sm:grid-cols-3 gap-4"><input type="hidden" name="csrf" value="<?= csrf() ?>">
 <h2 class="sm:col-span-3 text-lg font-semibold text-primary">Tambah Kriteria</h2>
 <label class="text-sm font-semibold sm:col-span-2">Nama<input name="nama" required class="<?= $inp ?>"></label>
 <label class="text-sm font-semibold">Tipe MOORA<select name="tipe" class="<?= $inp ?>"><option value="benefit">Benefit (makin besar makin baik)</option><option value="cost">Cost (makin kecil makin baik)</option></select></label>
 <label class="text-sm font-semibold">Jenis Input<select name="jenis_input" class="<?= $inp ?>"><?php foreach ($jenisLbl as $k => $l) echo "<option value=\"$k\">$l</option>"; ?></select></label>
 <label class="text-sm font-semibold">Min (angka)<input type="number" step="0.01" name="min_val" value="0" class="<?= $inp ?>"></label>
 <label class="text-sm font-semibold">Maks (angka)<input type="number" step="0.01" name="max_val" value="100" class="<?= $inp ?>"></label>
 <label class="text-sm font-semibold">Satuan<input name="satuan" placeholder="mis. %" class="<?= $inp ?>"></label>
 <div class="sm:col-span-3 flex justify-end"><button class="px-5 py-2.5 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-container">Tambah</button></div>
</form>
<?php page_end();
