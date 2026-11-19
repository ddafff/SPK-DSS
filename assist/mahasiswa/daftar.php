<?php
require __DIR__.'/../config.php'; require __DIR__.'/../layout.php'; require_role('mahasiswa');
$uid = user()['id']; $id = (int)($_GET['id'] ?? 0); $pdo = db();

$s = $pdo->prepare("SELECT l.*, d.nama dosen FROM lowongan l LEFT JOIN users d ON d.id=l.dosen_id WHERE l.id=? AND l.status='buka' AND l.deadline>=CURDATE()");
$s->execute([$id]); $lw = $s->fetch();
if (!$lw) { flash('Lowongan tidak ditemukan atau sudah ditutup.'); go('/mahasiswa/lowongan.php'); }
$s = $pdo->prepare('SELECT 1 FROM lamaran WHERE user_id=? AND lowongan_id=?'); $s->execute([$uid, $id]);
if ($s->fetch()) { flash('Anda sudah mendaftar pada lowongan ini.'); go('/mahasiswa/dashboard.php'); }

$s = $pdo->prepare('SELECT k.* FROM kriteria k JOIN lowongan_kriteria lk ON lk.kriteria_id=k.id WHERE lk.lowongan_id=? AND k.is_active=1 ORDER BY k.kode');
$s->execute([$id]); $kriteria = $s->fetchAll();

$soal = [];
foreach ($kriteria as $k) if (in_array($k['jenis_input'], ['kuisioner', 'tes'])) {
  $q = $pdo->prepare('SELECT * FROM soal WHERE kriteria_id=? ORDER BY id'); $q->execute([$k['id']]);
  foreach ($q->fetchAll() as $row) {
    $o = $pdo->prepare('SELECT id, teks, bobot FROM opsi_soal WHERE soal_id=?'); $o->execute([$row['id']]);
    $row['opsi'] = $o->fetchAll(); $soal[$k['id']][] = $row;
  }
}

function simpan_bukti(array $f, string $label, array &$err, array &$saved): ?string {
  if (($f['error'] ?? 4) !== UPLOAD_ERR_OK) { $err[] = "Bukti $label wajib diunggah."; return null; }
  if ($f['size'] > 5 * 1024 * 1024) { $err[] = "Bukti $label maksimal 5 MB."; return null; }
  $ext = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'application/pdf'=>'pdf'][(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])] ?? null;
  if (!$ext) { $err[] = "Bukti $label harus berupa JPG, PNG, atau PDF."; return null; }
  $name = bin2hex(random_bytes(16)).'.'.$ext;
  if (!move_uploaded_file($f['tmp_name'], __DIR__.'/../uploads/'.$name)) { $err[] = "Gagal menyimpan bukti $label."; return null; }
  $saved[] = $name; return $name;
}

$err = []; $hasil = []; $pilih = []; $saved = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_ok()) $err[] = 'Sesi tidak valid, muat ulang halaman.';
  foreach ($kriteria as $k) {
    $kid = $k['id']; $nm = $k['nama']; $nilai = null; $bukti = null; $st = 'otomatis';
    switch ($k['jenis_input']) {
      case 'angka_bukti':
        $v = trim($_POST['nilai'][$kid] ?? '');
        if (!is_numeric($v) || $v < $k['min_val'] || $v > $k['max_val']) $err[] = "$nm harus berupa angka {$k['min_val']}–{$k['max_val']}.";
        else $nilai = (float)$v;
        $bukti = simpan_bukti($_FILES["bukti_$kid"] ?? [], $nm, $err, $saved); $st = 'pending'; break;
      case 'huruf_bukti':
        $v = $_POST['nilai'][$kid] ?? '';
        if (!isset(HURUF[$v])) $err[] = "Pilih nilai huruf untuk $nm."; else $nilai = HURUF[$v];
        $bukti = simpan_bukti($_FILES["bukti_$kid"] ?? [], $nm, $err, $saved); $st = 'pending'; break;
      default: // kuisioner & tes
        $sum = 0; $benar = 0; $tot = count($soal[$kid] ?? []);
        foreach ($soal[$kid] ?? [] as $sl) {
          $pil = (int)($_POST['jawab'][$sl['id']] ?? 0); $ok = null;
          foreach ($sl['opsi'] as $o) if ($o['id'] == $pil) $ok = $o;
          if (!$ok) { $err[] = "Jawab semua soal pada $nm."; break; }
          $sum += $ok['bobot']; $benar += $ok['bobot'] > 0 ? 1 : 0; $pilih[$sl['id']] = $ok['id'];
        }
        $nilai = $k['jenis_input'] === 'tes' ? ($tot ? $benar / $tot * 100 : 0) : $sum;
    }
    $hasil[] = ['kid'=>$kid, 'nilai'=>$nilai, 'bukti'=>$bukti, 'st'=>$st];
  }
  if (empty($_POST['setuju'])) $err[] = 'Anda harus menyetujui pernyataan keaslian data.';
  if (!$err) {
    $pdo->beginTransaction();
    try {
      $pdo->prepare('INSERT INTO lamaran(user_id, lowongan_id) VALUES(?,?)')->execute([$uid, $id]); $lid = $pdo->lastInsertId();
      $ij = $pdo->prepare('INSERT INTO jawaban_kriteria(lamaran_id,kriteria_id,nilai,bukti,status_verifikasi) VALUES(?,?,?,?,?)');
      foreach ($hasil as $h) $ij->execute([$lid, $h['kid'], $h['nilai'], $h['bukti'], $h['st']]);
      $is = $pdo->prepare('INSERT INTO jawaban_soal(lamaran_id,soal_id,opsi_id) VALUES(?,?,?)');
      foreach ($pilih as $sid => $oid) $is->execute([$lid, $sid, $oid]);
      $pdo->commit(); flash('Lamaran untuk '.$lw['matkul'].' berhasil dikirim. Berkas Anda akan diverifikasi dosen.'); go('/mahasiswa/dashboard.php');
    } catch (PDOException $x) {
      $pdo->rollBack(); $err[] = $x->getCode() === '23000' ? 'Anda sudah mendaftar pada lowongan ini.' : 'Gagal menyimpan lamaran.';
    }
  }
  foreach ($saved as $n) @unlink(__DIR__.'/../uploads/'.$n); // bersihkan berkas bila gagal
}

$inp = 'w-full px-3 py-2.5 rounded-lg border border-surface-container-high bg-white focus:outline-none focus:ring-2 focus:ring-primary-container';
function upload_field(int $kid, string $label): void { ?>
  <div class="mt-3"><label class="block text-sm font-semibold">Foto/Scan bukti <?= e($label) ?> <span class="font-normal text-on-surface-variant">(JPG, PNG, PDF • maks. 5 MB)</span></label>
   <input type="file" name="bukti_<?= $kid ?>" accept="image/jpeg,image/png,application/pdf" required class="mt-1 block w-full text-sm file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-surface-container file:text-primary file:font-semibold">
  </div>
<?php }
page_start('Formulir Pendaftaran', 'lowongan'); ?>
<nav class="text-sm text-on-surface-variant"><a href="<?= BASE ?>/mahasiswa/lowongan.php" class="hover:text-primary">Lowongan Asisten</a> / <span class="text-primary font-semibold">Formulir <?= e($lw['kode']) ?></span></nav>
<section class="bg-surface-container-lowest rounded-xl p-6 shadow-sm">
 <p class="text-xs font-semibold uppercase tracking-wider text-secondary">Formulir Pendaftaran Calon Asisten • <?= e($lw['kode']) ?></p>
 <h1 class="text-2xl font-semibold text-primary mt-1"><?= e($lw['matkul']) ?></h1>
 <p class="text-sm text-on-surface-variant mt-1">Seluruh data di bawah menjadi input penilaian MOORA. Isi dengan jujur; bukti akan diverifikasi dosen. Batas: <?= date('d M Y', strtotime($lw['deadline'])) ?>.</p>
</section>
<?php if ($err): ?><div class="bg-error-container text-on-error-container rounded-lg px-4 py-3 text-sm"><b>Periksa kembali:</b><ul class="list-disc ml-5"><?php foreach (array_unique($err) as $m) echo '<li>'.e($m).'</li>'; ?></ul><p class="mt-1">Berkas bukti perlu dipilih ulang.</p></div><?php endif ?>

<form method="post" enctype="multipart/form-data" class="space-y-5">
 <input type="hidden" name="csrf" value="<?= csrf() ?>">
 <?php foreach ($kriteria as $i => $k): $kid = $k['id']; ?>
 <section class="bg-surface-container-lowest rounded-xl p-6 shadow-sm">
  <div class="flex items-center gap-3 mb-4">
   <div class="w-9 h-9 rounded-lg bg-primary-container text-white flex items-center justify-center font-semibold text-sm"><?= e($k['kode']) ?></div>
   <h2 class="text-lg font-semibold text-primary"><?= e($k['nama']) ?></h2>
  </div>
  <?php if ($k['jenis_input'] === 'angka_bukti'): ?>
   <label class="block text-sm font-semibold">Nilai <?= e($k['nama']) ?> <?= $k['satuan'] ? '('.e($k['satuan']).')' : '' ?></label>
   <input type="number" step="0.01" min="<?= e($k['min_val']) ?>" max="<?= e($k['max_val']) ?>" name="nilai[<?= $kid ?>]" required value="<?= e($_POST['nilai'][$kid] ?? '') ?>" class="mt-1 sm:w-64 <?= $inp ?>">
   <?php upload_field($kid, $k['nama']); ?>
  <?php elseif ($k['jenis_input'] === 'huruf_bukti'): ?>
   <label class="block text-sm font-semibold">Nilai huruf mata kuliah <?= e($lw['matkul']) ?></label>
   <select name="nilai[<?= $kid ?>]" required class="mt-1 sm:w-64 <?= $inp ?>"><option value="">Pilih nilai</option>
    <?php foreach (array_keys(HURUF) as $h): ?><option <?= ($_POST['nilai'][$kid] ?? '') === $h ? 'selected' : '' ?>><?= $h ?></option><?php endforeach ?></select>
   <?php upload_field($kid, $k['nama']); ?>
  <?php else: ?>
   <?php if (empty($soal[$kid])): ?><p class="text-sm text-on-surface-variant">Belum ada soal untuk kriteria ini.</p><?php endif ?>
   <?php foreach ($soal[$kid] ?? [] as $n => $sl): $op = $sl['opsi']; shuffle($op); ?>
    <fieldset class="mb-5 last:mb-0">
     <legend class="text-sm font-semibold mb-2"><?= $n + 1 ?>. <?= e($sl['pertanyaan']) ?></legend>
     <div class="space-y-2">
      <?php foreach ($op as $o): ?>
       <label class="flex items-start gap-3 p-3 rounded-lg bg-surface hover:bg-surface-container cursor-pointer text-sm">
        <input type="radio" name="jawab[<?= $sl['id'] ?>]" value="<?= $o['id'] ?>" required <?= (int)($_POST['jawab'][$sl['id']] ?? 0) === (int)$o['id'] ? 'checked' : '' ?> class="mt-0.5">
        <span><?= e($o['teks']) ?></span></label>
      <?php endforeach ?>
     </div>
    </fieldset>
   <?php endforeach ?>
  <?php endif ?>
 </section>
 <?php endforeach ?>

 <section class="bg-surface-container-lowest rounded-xl p-6 shadow-sm">
  <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="setuju" value="1" required class="mt-1">
   <span>Saya menyatakan seluruh data dan berkas yang saya kirim adalah benar. Pemalsuan bukti dapat berakibat diskualifikasi.</span></label>
  <div class="flex justify-end gap-2 mt-5">
   <a href="<?= BASE ?>/mahasiswa/lowongan.php" class="px-4 py-2.5 rounded-lg text-sm font-semibold text-on-surface-variant hover:bg-surface-container">Batalkan</a>
   <button class="px-5 py-2.5 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-container">Kirim Lamaran <?= ic('send',16) ?></button>
  </div>
 </section>
</form>
<?php page_end();
