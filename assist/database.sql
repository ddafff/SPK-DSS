CREATE DATABASE IF NOT EXISTS assist_spk CHARACTER SET utf8mb4;
USE assist_spk;
CREATE TABLE users(id INT AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(100) NOT NULL, email VARCHAR(100) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, role ENUM('mahasiswa','dosen','admin') NOT NULL DEFAULT 'mahasiswa', nim VARCHAR(20) UNIQUE, prodi VARCHAR(80), semester TINYINT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE lowongan(id INT AUTO_INCREMENT PRIMARY KEY, kode VARCHAR(10) NOT NULL, matkul VARCHAR(100) NOT NULL, dosen_id INT, kuota TINYINT NOT NULL DEFAULT 1, deskripsi TEXT, deadline DATE NOT NULL, status ENUM('buka','tutup') DEFAULT 'buka', FOREIGN KEY(dosen_id) REFERENCES users(id));
CREATE TABLE kriteria(id INT AUTO_INCREMENT PRIMARY KEY, kode VARCHAR(5), nama VARCHAR(60) NOT NULL, jenis_input ENUM('angka_bukti','huruf_bukti','kuisioner','tes') NOT NULL, satuan VARCHAR(20), min_val DECIMAL(5,2) DEFAULT 0, max_val DECIMAL(5,2) DEFAULT 100, tipe ENUM('benefit','cost') DEFAULT 'benefit', is_active TINYINT(1) DEFAULT 1);
CREATE TABLE lowongan_kriteria(lowongan_id INT, kriteria_id INT, bobot DECIMAL(5,4) NOT NULL, PRIMARY KEY(lowongan_id,kriteria_id), FOREIGN KEY(lowongan_id) REFERENCES lowongan(id), FOREIGN KEY(kriteria_id) REFERENCES kriteria(id));
CREATE TABLE soal(id INT AUTO_INCREMENT PRIMARY KEY, kriteria_id INT NOT NULL, pertanyaan TEXT NOT NULL, FOREIGN KEY(kriteria_id) REFERENCES kriteria(id));
CREATE TABLE opsi_soal(id INT AUTO_INCREMENT PRIMARY KEY, soal_id INT NOT NULL, teks TEXT NOT NULL, bobot TINYINT NOT NULL, FOREIGN KEY(soal_id) REFERENCES soal(id));
CREATE TABLE lamaran(id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, lowongan_id INT NOT NULL, status ENUM('verifikasi','lolos_berkas','ditolak','diterima','cadangan') DEFAULT 'verifikasi', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id,lowongan_id), FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(lowongan_id) REFERENCES lowongan(id));
CREATE TABLE jawaban_kriteria(id INT AUTO_INCREMENT PRIMARY KEY, lamaran_id INT NOT NULL, kriteria_id INT NOT NULL, nilai DECIMAL(8,2) NOT NULL, bukti VARCHAR(64), status_verifikasi ENUM('pending','valid','ditolak','otomatis') DEFAULT 'pending', UNIQUE(lamaran_id,kriteria_id), FOREIGN KEY(lamaran_id) REFERENCES lamaran(id), FOREIGN KEY(kriteria_id) REFERENCES kriteria(id));
CREATE TABLE jawaban_soal(lamaran_id INT, soal_id INT, opsi_id INT, PRIMARY KEY(lamaran_id,soal_id), FOREIGN KEY(lamaran_id) REFERENCES lamaran(id));

-- Seed. Akun admin: admin@univ.ac.id / password  (GANTI setelah login pertama)
-- Seed. Akun dosen: dosen@univ.ac.id / password  (GANTI setelah login pertama)
INSERT INTO users(nama,email,password,role) VALUES('Administrator','admin@univ.ac.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin');
INSERT INTO users(nama,email,password,role) VALUES('Dr. Aris Sudarma, M.Kom','dosen@univ.ac.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','dosen');
INSERT INTO lowongan(kode,matkul,dosen_id,kuota,deskripsi,deadline) VALUES
('INF304','Pemrograman Web',1,3,'Mendampingi praktikum web (HTML, CSS, JS, PHP, MySQL).',DATE_ADD(CURDATE(),INTERVAL 10 DAY)),
('INF201','Struktur Data & Algoritma',1,4,NULL,DATE_ADD(CURDATE(),INTERVAL 14 DAY)),
('INF308','Basis Data Lanjut',1,2,NULL,DATE_ADD(CURDATE(),INTERVAL 7 DAY));
INSERT INTO kriteria(kode,nama,jenis_input,satuan,min_val,max_val) VALUES
('C1','IPK','angka_bukti','skala 4.00',0,4),
('C2','Nilai Mata Kuliah Terkait','huruf_bukti',NULL,0,4),
('C3','Penguasaan Materi','tes',NULL,0,100),
('C4','Kemampuan Teknis','kuisioner',NULL,0,0),
('C5','Kemampuan Komunikasi','kuisioner',NULL,0,0),
('C6','Kedisiplinan (Kehadiran)','angka_bukti','%',0,100),
('C7','Tanggung Jawab','kuisioner',NULL,0,0),
('C8','Kerja Sama','kuisioner',NULL,0,0);
INSERT INTO lowongan_kriteria SELECT l.id,k.id,0.125 FROM lowongan l CROSS JOIN kriteria k;
-- Contoh soal (sisa soal dari dokumen Anda ditambahkan dengan pola yang sama)
INSERT INTO soal(kriteria_id,pertanyaan) VALUES
(4,'Saat sesi praktikum, source code salah satu mahasiswa mengalami error yang belum pernah Anda temui sebelumnya. Apa tindakan Anda?'),
(3,'Apa fungsi utama PDO prepared statement pada PHP?');
INSERT INTO opsi_soal(soal_id,teks,bobot) VALUES
(1,'Meminta mahasiswa tersebut pindah ke komputer lain atau menyalin punya temannya agar cepat selesai.',2),
(1,'Langsung membaca pesan error-nya, menganalisis baris kode/konfigurasi yang bermasalah, dan mencari solusinya bersama mahasiswa tersebut.',5),
(1,'Menyerahkan masalah tersebut sepenuhnya kepada dosen pengampu karena merasa di luar tanggung jawab Anda.',1),
(1,'Mencoba me-restart aplikasi berulang kali dengan harapan error hilang sendiri.',3),
(1,'Meminta mahasiswa mencari solusinya sendiri di internet terlebih dahulu sementara Anda membantu mahasiswa lain.',4),
(2,'Memisahkan data input dari struktur query SQL sehingga mencegah SQL Injection.',1),
(2,'Mempercepat tampilan halaman di browser.',0),
(2,'Mengompres ukuran file yang diunggah.',0),
(2,'Mengenkripsi seluruh isi database.',0);
