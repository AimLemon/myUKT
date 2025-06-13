-- Tambahkan kolom email_verified ke tabel Mahasiswa
ALTER TABLE Mahasiswa ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0;
