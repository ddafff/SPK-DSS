<?php
function html_head(string $title): void { ?>
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> • ASSIST SPK</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']},borderRadius:{DEFAULT:'.125rem',lg:'.25rem',xl:'.5rem'},colors:{primary:'#002548','primary-container':'#173b63',secondary:'#346289','secondary-container':'#a4d0fd','on-secondary-container':'#2b5980',surface:'#f8f9ff','surface-container-lowest':'#ffffff','surface-container-low':'#eff4ff','surface-container':'#e5eeff','surface-container-high':'#dce9ff','on-surface':'#0b1c30','on-surface-variant':'#43474e',outline:'#73777f',error:'#ba1a1a','error-container':'#ffdad6','on-error-container':'#93000a'}}}}</script>
</head>
<?php }

function page_start(string $title, string $active = ''): void {
  $u = user(); html_head($title);
  $nav = $u['role'] === 'dosen' ? ['dashboard'=>['Lowongan Saya','dashboard.php'], 'kriteria'=>['Kriteria','kriteria.php'], 'bank'=>['Bank Soal','bank_soal.php']] : ['dashboard'=>['Dashboard','dashboard.php'], 'lowongan'=>['Lowongan Asisten','lowongan.php'], 'lamaran'=>['Lamaran Saya','lamaran.php'], 'pengumuman'=>['Pengumuman','pengumuman.php']]; ?>
<body class="bg-surface text-on-surface font-sans min-h-screen flex flex-col">
<header class="fixed top-0 inset-x-0 z-50 h-16 bg-surface-container-lowest/90 backdrop-blur shadow-sm">
 <div class="h-full px-6 flex items-center justify-between">
  <div class="flex items-center gap-6">
   <div class="leading-none"><span class="text-lg font-bold text-primary tracking-tight">ASSIST</span><span class="block text-xs font-medium text-secondary">Portal <?= ucfirst($u['role']) ?></span></div>
   <nav class="flex gap-1">
    <?php foreach ($nav as $k => [$t, $f]): ?>
     <a href="<?= BASE ?>/<?= $u['role'] ?>/<?= $f ?>" class="px-3 sm:px-4 py-2 rounded-lg text-sm <?= $k === $active ? 'bg-primary-container text-white font-semibold' : 'text-on-surface-variant hover:bg-surface-container-high' ?>"><?= $t ?></a>
    <?php endforeach ?>
   </nav>
  </div>
  <div class="flex items-center gap-3">
   <span class="hidden sm:flex items-center gap-1 bg-surface-container-low px-3 py-1.5 rounded-full text-xs font-semibold text-secondary"><?= ic('school',16) ?> <?= $u['nim'] ? 'NIM: '.e($u['nim']) : e($u['nama']) ?></span>
   <a href="<?= BASE ?>/logout.php" title="Keluar" class="p-2 rounded-lg text-on-surface-variant hover:text-error hover:bg-surface-container-high"><?= ic('logout') ?></a>
  </div>
 </div>
</header>
<main class="flex-1 pt-16"><div class="max-w-7xl mx-auto px-6 py-6 space-y-6">
<?php if ($m = get_flash()): ?><div class="bg-secondary-container text-on-secondary-container rounded-lg px-4 py-3 text-sm"><?= e($m) ?></div><?php endif;
}

function page_end(): void { ?>
</div></main>
<footer class="bg-surface-container-lowest py-4 text-center text-xs text-on-surface-variant">ASSIST SPK MOORA • Sistem Pendukung Keputusan Seleksi Asisten Dosen</footer>
</body></html>
<?php }

function kartu_lowongan(array $r): void {
  $sisa = (new DateTime('today'))->diff(new DateTime($r['deadline']))->days; ?>
<div class="bg-surface-container-lowest rounded-xl p-5 shadow-sm hover:shadow-md transition flex flex-col justify-between gap-4">
 <div class="space-y-3">
  <div class="flex justify-between items-center">
   <span class="text-xs font-semibold tracking-wider text-secondary"><?= e($r['kode']) ?></span>
   <span class="text-xs font-medium px-2 py-0.5 rounded-full <?= $sisa <= 3 ? 'bg-error-container text-on-error-container' : 'bg-surface-container-low text-secondary' ?>">Sisa <?= $sisa ?> hari</span>
  </div>
  <h3 class="text-lg font-semibold text-primary"><?= e($r['matkul']) ?></h3>
  <p class="text-sm text-on-surface-variant"><?= e($r['dosen'] ?? '-') ?></p>
  <div class="text-sm space-y-1">
   <div><?= ic('group',18) ?> Kuota: <b><?= (int)$r['kuota'] ?> asisten</b> <span class="text-on-surface-variant">(<?= (int)$r['pelamar'] ?> pelamar)</span></div>
   <div><?= ic('calendar_today',18) ?> Batas: <?= date('d M Y', strtotime($r['deadline'])) ?></div>
  </div>
  <?php if ($r['deskripsi']): ?><p class="text-sm text-on-surface-variant bg-surface rounded-lg p-2.5"><?= e($r['deskripsi']) ?></p><?php endif ?>
 </div>
 <?php if ($r['sudah']): ?>
  <span class="text-center py-2 rounded-lg bg-surface-container text-secondary text-sm font-semibold">Sudah Mendaftar</span>
 <?php else: ?>
  <a href="<?= BASE ?>/mahasiswa/daftar.php?id=<?= (int)$r['id'] ?>" class="text-center py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-container">Daftar Sekarang</a>
 <?php endif ?>
</div>
<?php }
