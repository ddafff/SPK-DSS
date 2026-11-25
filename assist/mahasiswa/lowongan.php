<?php
require __DIR__.'/../config.php'; require __DIR__.'/../layout.php'; require_role('mahasiswa');
$q = trim($_GET['q'] ?? ''); $rows = daftar_lowongan(user()['id'], $q);
page_start('Lowongan Asisten', 'lowongan'); ?>
<div>
 <h1 class="text-3xl font-bold text-primary tracking-tight">Daftar Lowongan Asisten Dosen</h1>
 <p class="text-on-surface-variant mt-1">Seleksi berbasis metode MOORA. Pilih mata kuliah yang sesuai dengan kompetensi Anda.</p>
</div>
<form method="get" class="bg-surface-container-lowest rounded-xl p-4 shadow-sm flex gap-2">
 <input name="q" value="<?= e($q) ?>" placeholder="Cari mata kuliah atau kode..." class="flex-1 px-3 py-2 rounded-lg bg-surface focus:outline-none focus:ring-2 focus:ring-primary-container">
 <button class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold">Cari</button>
</form>
<p class="text-sm text-on-surface-variant">Menampilkan <b class="text-primary"><?= count($rows) ?></b> lowongan terbuka</p>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"><?php foreach ($rows as $r) kartu_lowongan($r); ?></div>
<?php page_end();
