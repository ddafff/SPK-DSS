<?php
require __DIR__.'/config.php'; require __DIR__.'/layout.php';
if ($u = user()) go($u['role'] === 'dosen' ? '/dosen/dashboard.php' : '/mahasiswa/dashboard.php');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_ok()) $err = 'Sesi tidak valid, muat ulang halaman.';
  else {
    $s = db()->prepare('SELECT * FROM users WHERE email=?'); $s->execute([trim($_POST['email'] ?? '')]); $r = $s->fetch();
    if ($r && password_verify($_POST['password'] ?? '', $r['password'])) {
      session_regenerate_id(true);
      $_SESSION['user'] = ['id'=>$r['id'], 'nama'=>$r['nama'], 'role'=>$r['role'], 'nim'=>$r['nim']];
      go($r['role'] === 'dosen' ? '/dosen/dashboard.php' : '/mahasiswa/dashboard.php');
    }
    $err = 'Email atau kata sandi salah.';
  }
}
html_head('Masuk'); ?>
<body class="bg-surface text-on-surface font-sans min-h-screen flex flex-col">
<header class="bg-surface-container-lowest shadow-sm"><div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
 <span class="font-bold text-primary">ASSIST SPK</span>
 <span class="hidden sm:block text-xs text-on-surface-variant text-right">Sistem Pendukung Keputusan Seleksi Asisten Dosen<br><span class="text-secondary">Metode MOORA</span></span>
</div></header>
<main class="flex-1 flex items-center justify-center p-6"><div class="w-full max-w-md">
 <div class="text-center mb-6">
  <div class="inline-flex p-3 rounded-xl bg-surface-container-low text-primary mb-2"><?= ic('school',32) ?></div>
  <h1 class="text-2xl font-semibold text-primary">ASSIST</h1>
  <p class="text-sm text-on-surface-variant mt-1">Seleksi Asisten Dosen berbasis MOORA</p>
 </div>
 <div class="bg-surface-container-lowest rounded-xl shadow-sm p-8">
  <h2 class="text-lg font-semibold">Masuk ke Sistem</h2>
  <p class="text-sm text-on-surface-variant mb-5">Gunakan email dan kata sandi Anda. Mahasiswa dan dosen masuk lewat halaman yang sama.</p>
  <?php if ($m = get_flash()): ?><div class="mb-4 bg-secondary-container text-on-secondary-container rounded-lg px-3 py-2 text-sm"><?= e($m) ?></div><?php endif ?>
  <?php if ($err): ?><div class="mb-4 bg-error-container text-on-error-container rounded-lg px-3 py-2 text-sm"><?= e($err) ?></div><?php endif ?>
  <form method="post" class="space-y-4">
   <input type="hidden" name="csrf" value="<?= csrf() ?>">
   <label class="block text-sm font-semibold">Email
    <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" class="mt-1 w-full px-3 py-2.5 rounded-lg border border-surface-container-high bg-white font-normal focus:outline-none focus:ring-2 focus:ring-primary-container"></label>
   <label class="block text-sm font-semibold">Kata Sandi
    <input type="password" name="password" required class="mt-1 w-full px-3 py-2.5 rounded-lg border border-surface-container-high bg-white font-normal focus:outline-none focus:ring-2 focus:ring-primary-container"></label>
   <button class="w-full bg-primary-container hover:bg-primary text-white font-semibold py-3 rounded-lg">Masuk ke Akun</button>
  </form>
  <p class="mt-5 p-3 bg-surface-container-low rounded-lg text-center text-sm text-on-surface-variant">Belum punya akun mahasiswa? <a href="<?= BASE ?>/register.php" class="font-semibold text-primary hover:underline">Daftar Akun Baru</a></p>
 </div>
</div></main></body></html>
