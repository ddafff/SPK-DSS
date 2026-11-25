<?php
require __DIR__.'/../config.php'; require __DIR__.'/../layout.php'; require_role('admin');
$pdo = db();
$err = []; $success = '';

// --- Proses POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
  $aksi = $_POST['aksi'] ?? '';

  // Tambah dosen baru
  if ($aksi === 'tambah') {
    $nama  = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pw    = $_POST['password'] ?? '';
    $pw2   = $_POST['password2'] ?? '';
    $nidn  = trim($_POST['nidn'] ?? '');
    if ($nama === '')  $err[] = 'Nama wajib diisi.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Email tidak valid.';
    if (strlen($pw) < 8) $err[] = 'Kata sandi minimal 8 karakter.';
    if ($pw !== $pw2)  $err[] = 'Konfirmasi kata sandi tidak sama.';
    if (!$err) {
      try {
        $pdo->prepare("INSERT INTO users(nama,email,password,role,nim) VALUES(?,?,?,'dosen',?)")
            ->execute([$nama, $email, password_hash($pw, PASSWORD_DEFAULT), $nidn ?: null]);
        flash('Akun dosen '.$nama.' berhasil dibuat.'); go('/admin/dosen.php');
      } catch (PDOException $x) {
        $err[] = $x->getCode() === '23000' ? 'Email atau NIDN sudah terdaftar.' : 'Gagal membuat akun: '.$x->getMessage();
      }
    }
  }

  // Reset password dosen
  if ($aksi === 'reset_pw') {
    $id  = (int)($_POST['id'] ?? 0);
    $pw  = $_POST['pw_baru'] ?? '';
    $pw2 = $_POST['pw_baru2'] ?? '';
    if (strlen($pw) < 8) $err[] = 'Kata sandi minimal 8 karakter.';
    elseif ($pw !== $pw2) $err[] = 'Konfirmasi kata sandi tidak sama.';
    else {
      $pdo->prepare("UPDATE users SET password=? WHERE id=? AND role='dosen'")
          ->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
      flash('Password dosen berhasil direset.'); go('/admin/dosen.php');
    }
  }

  // Hapus dosen
  if ($aksi === 'hapus') {
    $id = (int)($_POST['id'] ?? 0);
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM lowongan WHERE dosen_id=?"); $cnt->execute([$id]);
    if ($cnt->fetchColumn() > 0) {
      flash('Dosen ini memiliki lowongan aktif, tidak dapat dihapus.'); go('/admin/dosen.php');
    } else {
      $pdo->prepare("DELETE FROM users WHERE id=? AND role='dosen'")->execute([$id]);
      flash('Akun dosen dihapus.'); go('/admin/dosen.php');
    }
  }
}

$dosen = $pdo->query("SELECT u.*, COUNT(l.id) AS total_lowongan FROM users u LEFT JOIN lowongan l ON l.dosen_id=u.id WHERE u.role='dosen' GROUP BY u.id ORDER BY u.nama")->fetchAll();
$edit_id = (int)($_GET['reset'] ?? 0);
$edit_dosen = null;
if ($edit_id) {
  $s = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='dosen'"); $s->execute([$edit_id]); $edit_dosen = $s->fetch();
}

$inp = 'mt-1 w-full px-3 py-2.5 rounded-lg border border-surface-container-high bg-white font-normal focus:outline-none focus:ring-2 focus:ring-primary-container';
page_start('Data Dosen', 'dosen'); ?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-3">
 <div>
  <h1 class="text-2xl font-semibold text-primary">Data Dosen</h1>
  <p class="text-sm text-on-surface-variant">Kelola akun dosen yang dapat membuka lowongan asisten.</p>
 </div>
</div>

<?php if ($err): ?>
<div class="bg-error-container text-on-error-container rounded-lg px-4 py-3 text-sm"><?= implode('<br>', array_map('e', $err)) ?></div>
<?php endif ?>

<!-- Tabel dosen -->
<div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-x-auto">
 <table class="w-full text-sm"><thead class="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
  <tr><th class="p-3">Nama</th><th class="p-3">Email</th><th class="p-3">NIDN</th><th class="p-3">Lowongan</th><th class="p-3">Bergabung</th><th class="p-3">Aksi</th></tr>
 </thead><tbody>
 <?php if (!$dosen): ?><tr><td colspan="6" class="p-5 text-on-surface-variant">Belum ada data dosen.</td></tr><?php endif ?>
 <?php foreach ($dosen as $d): ?>
  <tr class="border-t border-surface-container">
   <td class="p-3"><b class="text-primary"><?= e($d['nama']) ?></b></td>
   <td class="p-3 text-on-surface-variant"><?= e($d['email']) ?></td>
   <td class="p-3"><?= e($d['nim'] ?? '-') ?></td>
   <td class="p-3"><?= (int)$d['total_lowongan'] ?> lowongan</td>
   <td class="p-3 text-on-surface-variant"><?= date('d M Y', strtotime($d['created_at'])) ?></td>
   <td class="p-3"><div class="flex flex-wrap gap-1 items-center">
    <a href="?reset=<?= $d['id'] ?>#form-reset" class="px-2 py-1 rounded bg-surface-container text-primary font-semibold text-xs">Reset PW</a>
    <?php if ($d['total_lowongan'] == 0): ?>
    <form method="post" onsubmit="return confirm('Hapus akun dosen <?= e(addslashes($d['nama'])) ?>?')">
     <input type="hidden" name="csrf" value="<?= csrf() ?>">
     <input type="hidden" name="aksi" value="hapus">
     <input type="hidden" name="id" value="<?= $d['id'] ?>">
     <button class="px-2 py-1 rounded hover:bg-surface-container text-error text-xs font-semibold">Hapus</button>
    </form>
    <?php endif ?>
   </div></td>
  </tr>
 <?php endforeach ?>
 </tbody></table>
</div>

<div class="grid md:grid-cols-2 gap-6">
 <!-- Form tambah dosen -->
 <form method="post" class="bg-surface-container-lowest rounded-xl p-6 shadow-sm space-y-4">
  <input type="hidden" name="csrf" value="<?= csrf() ?>">
  <input type="hidden" name="aksi" value="tambah">
  <h2 class="text-lg font-semibold text-primary"><?= ic('person_add',20) ?> Tambah Akun Dosen</h2>
  <label class="block text-sm font-semibold">Nama Lengkap (+ Gelar)
   <input type="text" name="nama" required value="<?= e($_POST['nama'] ?? '') ?>" class="<?= $inp ?>">
  </label>
  <label class="block text-sm font-semibold">NIDN <span class="text-on-surface-variant font-normal">(opsional)</span>
   <input type="text" name="nidn" value="<?= e($_POST['nidn'] ?? '') ?>" class="<?= $inp ?>">
  </label>
  <label class="block text-sm font-semibold">Email
   <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" class="<?= $inp ?>">
  </label>
  <div class="grid grid-cols-2 gap-4">
   <label class="block text-sm font-semibold">Kata Sandi
    <input type="password" name="password" required minlength="8" class="<?= $inp ?>">
   </label>
   <label class="block text-sm font-semibold">Ulangi Kata Sandi
    <input type="password" name="password2" required minlength="8" class="<?= $inp ?>">
   </label>
  </div>
  <div class="flex justify-end">
   <button class="px-5 py-2.5 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-container"><?= ic('save',16) ?> Simpan Dosen</button>
  </div>
 </form>

 <!-- Form reset password -->
 <div id="form-reset" class="bg-surface-container-lowest rounded-xl p-6 shadow-sm space-y-4">
  <h2 class="text-lg font-semibold text-primary"><?= ic('lock_reset',20) ?> Reset Password Dosen</h2>
  <?php if ($edit_dosen): ?>
  <p class="text-sm text-on-surface-variant">Reset password untuk: <b class="text-on-surface"><?= e($edit_dosen['nama']) ?></b></p>
  <form method="post" class="space-y-4">
   <input type="hidden" name="csrf" value="<?= csrf() ?>">
   <input type="hidden" name="aksi" value="reset_pw">
   <input type="hidden" name="id" value="<?= $edit_dosen['id'] ?>">
   <label class="block text-sm font-semibold">Kata Sandi Baru
    <input type="password" name="pw_baru" required minlength="8" class="<?= $inp ?>">
   </label>
   <label class="block text-sm font-semibold">Ulangi Kata Sandi Baru
    <input type="password" name="pw_baru2" required minlength="8" class="<?= $inp ?>">
   </label>
   <div class="flex gap-2 justify-end">
    <a href="<?= BASE ?>/admin/dosen.php" class="px-4 py-2 rounded-lg bg-surface-container text-on-surface text-sm font-semibold hover:bg-surface-container-high">Batal</a>
    <button class="px-5 py-2.5 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-container"><?= ic('lock_reset',16) ?> Reset</button>
   </div>
  </form>
  <?php else: ?>
  <p class="text-sm text-on-surface-variant">Pilih dosen dari tabel di atas, lalu klik <b>Reset PW</b> untuk mengatur ulang kata sandi.</p>
  <?php endif ?>
 </div>
</div>
<?php page_end();
