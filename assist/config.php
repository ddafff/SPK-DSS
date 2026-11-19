<?php
// Sesuaikan BASE dengan nama folder di htdocs/www (mis. /assist)
const BASE = '/assist';
const DB_HOST = 'localhost', DB_NAME = 'assist_spk', DB_USER = 'root', DB_PASS = '';
const HURUF = ['A'=>4.0,'A-'=>3.7,'B+'=>3.3,'B'=>3.0,'B-'=>2.7,'C+'=>2.3,'C'=>2.0,'D'=>1.0,'E'=>0.0];
const STATUS_LAMARAN = ['verifikasi'=>'Verifikasi Berkas','lolos_berkas'=>'Lolos Berkas','ditolak'=>'Tidak Lolos','diterima'=>'Diterima','cadangan'=>'Cadangan'];
session_start();
function db(): PDO { static $p; return $p ??= new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_ok(): bool { return hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? ''); }
function user(): ?array { return $_SESSION['user'] ?? null; }
function go(string $path): void { header('Location: '.BASE.$path); exit; }
function require_role(string $r): void { $u = user(); if (!$u || $u['role'] !== $r) go('/index.php'); }
function flash(string $m): void { $_SESSION['flash'] = $m; }
function get_flash(): ?string { $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m; }
function ic(string $n, int $s = 20): string { return '<span class="material-symbols-outlined align-middle" style="font-size:'.$s.'px">'.$n.'</span>'; }
function daftar_lowongan(int $uid, string $q = ''): array {
  $s = db()->prepare("SELECT l.*, d.nama dosen,
    (SELECT COUNT(*) FROM lamaran x WHERE x.lowongan_id=l.id) pelamar,
    (SELECT COUNT(*) FROM lamaran x WHERE x.lowongan_id=l.id AND x.user_id=:u) sudah
    FROM lowongan l LEFT JOIN users d ON d.id=l.dosen_id
    WHERE l.status='buka' AND l.deadline>=CURDATE() AND (l.matkul LIKE :q1 OR l.kode LIKE :q2) ORDER BY l.deadline");
  $s->execute([':u'=>$uid, ':q1'=>"%$q%", ':q2'=>"%$q%"]);
  return $s->fetchAll();
}
