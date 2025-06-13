-- SQL DDL untuk database MyUKT
CREATE DATABASE IF NOT EXISTS MyUKT;
USE MyUKT;

-- 1. Mahasiswa
CREATE TABLE IF NOT EXISTS Mahasiswa (
    id_mahasiswa INT AUTO_INCREMENT PRIMARY KEY,
    nim VARCHAR(32) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(100) NOT NULL,
    status_aktif ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif'
);

-- 2. Admin
CREATE TABLE IF NOT EXISTS Admin (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(100) NOT NULL
);

-- 3. Tagihan
CREATE TABLE IF NOT EXISTS Tagihan (
    id_tagihan INT AUTO_INCREMENT PRIMARY KEY,
    id_mahasiswa INT NOT NULL,
    semester VARCHAR(20) NOT NULL,
    tahun_ajaran VARCHAR(20) NOT NULL,
    jumlah_tagihan INT NOT NULL,
    batas_waktu DATE NOT NULL,
    status_tagihan ENUM('Belum Bayar','Lunas') NOT NULL DEFAULT 'Belum Bayar',
    FOREIGN KEY (id_mahasiswa) REFERENCES Mahasiswa(id_mahasiswa) ON DELETE CASCADE
);

-- 4. Pembayaran
CREATE TABLE IF NOT EXISTS Pembayaran (
    id_pembayaran INT AUTO_INCREMENT PRIMARY KEY,
    id_tagihan INT NOT NULL,
    tanggal_bayar DATETIME NOT NULL,
    metode_pembayaran VARCHAR(50),
    jumlah_dibayar INT NOT NULL,
    bukti_bayar VARCHAR(255),
    status_verifikasi ENUM('Menunggu','Terverifikasi','Ditolak') NOT NULL DEFAULT 'Menunggu',
    catatan_admin VARCHAR(255),
    FOREIGN KEY (id_tagihan) REFERENCES Tagihan(id_tagihan) ON DELETE CASCADE
);

-- 5. Riwayat_Pembayaran
CREATE TABLE IF NOT EXISTS Riwayat_Pembayaran (
    id_riwayat INT AUTO_INCREMENT PRIMARY KEY,
    id_pembayaran INT NOT NULL,
    id_mahasiswa INT NOT NULL,
    aksi VARCHAR(100) NOT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pembayaran) REFERENCES Pembayaran(id_pembayaran) ON DELETE CASCADE,
    FOREIGN KEY (id_mahasiswa) REFERENCES Mahasiswa(id_mahasiswa) ON DELETE CASCADE
);

-- 6. Notifikasi
CREATE TABLE IF NOT EXISTS Notifikasi (
    id_notifikasi INT AUTO_INCREMENT PRIMARY KEY,
    id_mahasiswa INT NOT NULL,
    judul VARCHAR(100) NOT NULL,
    isi TEXT NOT NULL,
    waktu_kirim DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status_baca ENUM('terbaca','belum') NOT NULL DEFAULT 'belum',
    FOREIGN KEY (id_mahasiswa) REFERENCES Mahasiswa(id_mahasiswa) ON DELETE CASCADE
);

-- Dummy admin
INSERT INTO Admin (nama, email, password) VALUES ('Admin', 'admin@myukt.com', 'admin123')
    ON DUPLICATE KEY UPDATE nama=VALUES(nama);
