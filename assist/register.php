<?php
require __DIR__.'/config.php'; require __DIR__.'/layout.php';
if (user()) go('/index.php');
$err = []; $v = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_ok()) $err[] = 'Sesi tidak valid, muat ulang halaman.';
  $nama = trim($v['nama'] ?? ''); $nim = trim($v['nim'] ?? ''); $email = trim($v['email'] ?? '');
  $prodi = trim($v['prodi'] ?? ''); $smt = (int)($v['semester'] ?? 0); $pw = $v['password'] ?? '';
  if ($nama === '') $err[] = 'Nama wajib diisi.';
  if (!preg_match('/^[A-Za-z0-9]{5,20}$/', $nim)) $err[] = 'NIM harus 5–20 karakter huruf/angka.';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Email tidak valid.';
  if ($smt < 1 || $smt > 14) $err[] = 'Semester harus 1–14.';
  if (strlen($pw) < 8) $err[] = 'Kata sandi minimal 8 karakter.';
  if ($pw !== ($v['password2'] ?? '')) $err[] = 'Konfirmasi kata sandi tidak sama.';
  if (!$err) {
    try {
      // role selalu 'mahasiswa'; akun dosen dibuat lewat seed/admin
      db()->prepare("INSERT INTO users(nama,email,password,role,nim,prodi,semester) VALUES(?,?,?,'mahasiswa',?,?,?)")
        ->execute([$nama, $email, password_hash($pw, PASSWORD_DEFAULT), $nim, $prodi, $smt]);
      flash('Akun berhasil dibuat. Silakan masuk.'); go('/index.php');
    } catch (PDOException $x) { $err[] = $x->getCode() === '23000' ? 'Email atau NIM sudah terdaftar.' : 'Gagal membuat akun.'; }
  }
}
function f(string $n, string $label, string $type = 'text', string $extra = ''): void {
  echo '<label class="block text-sm font-semibold">'.$label.'<input type="'.$type.'" name="'.$n.'" required '.$extra.' value="'.($type === 'password' ? '' : e($_POST[$n] ?? '')).'" class="mt-1 w-full px-3 py-2.5 rounded-lg border border-surface-container-high bg-white font-normal focus:outline-none focus:ring-2 focus:ring-primary-container"></label>';
}
html_head('Daftar Akun Mahasiswa'); ?>
<body class="bg-surface text-on-surface font-sans min-h-screen flex items-center justify-center p-6">
<div class="w-full max-w-lg bg-surface-container-lowest rounded-xl shadow-sm p-8">
 <h1 class="text-xl font-semibold text-primary">Daftar Akun Mahasiswa</h1>
 <p class="text-sm text-on-surface-variant mb-5">Akun dosen dibuat oleh admin, bukan lewat halaman ini.</p>
 <?php if ($err): ?><div class="mb-4 bg-error-container text-on-error-container rounded-lg px-3 py-2 text-sm"><?= implode('<br>', array_map('e', $err)) ?></div><?php endif ?>
 <form method="post" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
  <input type="hidden" name="csrf" value="<?= csrf() ?>">
  <div class="sm:col-span-2"><?php f('nama', 'Nama Lengkap') ?></div>
  <?php f('nim', 'NIM'); f('semester', 'Semester', 'number', 'min="1" max="14"'); ?>
  <div class="sm:col-span-2"><?php f('prodi', 'Program Studi'); ?></div>
  <div class="sm:col-span-2"><?php f('email', 'Email', 'email'); ?></div>
  <?php f('password', 'Kata Sandi', 'password', 'minlength="8"'); f('password2', 'Ulangi Kata Sandi', 'password'); ?>
  <button class="sm:col-span-2 bg-primary-container hover:bg-primary text-white font-semibold py-3 rounded-lg">Buat Akun</button>
 </form>
 <p class="mt-4 text-center text-sm text-on-surface-variant">Sudah punya akun? <a href="<?= BASE ?>/index.php" class="font-semibold text-primary hover:underline">Masuk</a></p>
</div></body></html>
