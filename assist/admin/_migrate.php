<?php
require __DIR__.'/../config.php';
$pdo = db();

// 1. Alter ENUM to add 'admin'
try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('mahasiswa','dosen','admin') NOT NULL DEFAULT 'mahasiswa'");
    echo "OK: role ENUM updated\n";
} catch (Exception $e) {
    echo "ERR alter: " . $e->getMessage() . "\n";
}

// 2. Insert admin account (default password: 'password' - harus diganti)
$hash = password_hash('password', PASSWORD_DEFAULT);
try {
    $pdo->prepare("INSERT IGNORE INTO users(nama,email,password,role) VALUES(?,?,?,'admin')")
        ->execute(['Administrator', 'admin@univ.ac.id', $hash]);
    echo "OK: admin account inserted (email: admin@univ.ac.id, password: password)\n";
} catch (Exception $e) {
    echo "ERR insert: " . $e->getMessage() . "\n";
}

// 3. Verify
$r = $pdo->query("SELECT id,nama,email,role FROM users WHERE role='admin'")->fetchAll();
echo "Admin accounts:\n";
print_r($r);
echo "\nSelesai. Hapus file ini setelah migrasi berhasil.\n";
