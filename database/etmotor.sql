CREATE DATABASE IF NOT EXISTS db_etmotor CHARACTER SET utf8mb4;
USE db_etmotor;

CREATE TABLE users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','pelanggan') NOT NULL DEFAULT 'pelanggan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE pelanggan (
    id_pelanggan INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    nama VARCHAR(100) NOT NULL,
    alamat TEXT,
    no_hp VARCHAR(20),
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
);

CREATE TABLE motor (
    id_motor INT AUTO_INCREMENT PRIMARY KEY,
    id_pelanggan INT NOT NULL,
    plat_nomor VARCHAR(15) NOT NULL,
    merk_tipe VARCHAR(100),
    spek_mesin TEXT,
    FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id_pelanggan) ON DELETE CASCADE,
    INDEX idx_plat (plat_nomor)
);

CREATE TABLE layanan (
    id_layanan INT AUTO_INCREMENT PRIMARY KEY,
    nama_layanan VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    estimasi_biaya DECIMAL(12,2) NOT NULL DEFAULT 0
);

CREATE TABLE spareparts (
    id_part INT AUTO_INCREMENT PRIMARY KEY,
    kode_part VARCHAR(20) UNIQUE,
    nama_part VARCHAR(100) NOT NULL,
    kategori VARCHAR(50),
    stok INT NOT NULL DEFAULT 0,
    harga DECIMAL(12,2) NOT NULL DEFAULT 0,
    INDEX idx_nama (nama_part),
    INDEX idx_kategori (kategori)
);

CREATE TABLE reservasi (
    id_reservasi INT AUTO_INCREMENT PRIMARY KEY,
    id_pelanggan INT NOT NULL,
    id_motor INT NOT NULL,
    id_layanan INT NOT NULL,
    tanggal DATE NOT NULL,
    jam TIME NOT NULL,
    status ENUM('menunggu','proses','selesai','batal') NOT NULL DEFAULT 'menunggu',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id_pelanggan) ON DELETE CASCADE,
    FOREIGN KEY (id_motor) REFERENCES motor(id_motor) ON DELETE CASCADE,
    FOREIGN KEY (id_layanan) REFERENCES layanan(id_layanan),
    INDEX idx_jadwal (tanggal, jam)
);

CREATE TABLE servis (
    id_servis INT AUTO_INCREMENT PRIMARY KEY,
    id_reservasi INT NOT NULL,
    catatan_mekanik TEXT,
    total_biaya DECIMAL(12,2) DEFAULT 0,
    metode_bayar ENUM('gopay','shopeepay','tunai') DEFAULT NULL,
    status_bayar ENUM('belum_bayar','lunas') NOT NULL DEFAULT 'belum_bayar',
    status ENUM('menunggu','proses','selesai') NOT NULL DEFAULT 'menunggu',
    selesai_at TIMESTAMP NULL,
    FOREIGN KEY (id_reservasi) REFERENCES reservasi(id_reservasi) ON DELETE CASCADE
);

CREATE TABLE detail_servis_part (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_servis INT NOT NULL,
    id_part INT NOT NULL,
    jumlah INT NOT NULL DEFAULT 1,
    harga_satuan DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (id_servis) REFERENCES servis(id_servis) ON DELETE CASCADE,
    FOREIGN KEY (id_part) REFERENCES spareparts(id_part)
);

INSERT INTO layanan (nama_layanan, deskripsi, estimasi_biaya) VALUES
('Servis Ringan', 'Pengecekan rutin dan penyetelan ringan', 75000),
('Servis Rutin (Ganti Oli)', 'Penggantian oli mesin berkala', 150000),
('Setting Dial Camshaft', 'Penyetelan ulang timing camshaft presisi', 250000),
('Tune Up Performa', 'Penyetelan performa mesin menyeluruh', 350000),
('Bore Up', 'Modifikasi peningkatan kapasitas silinder', 1500000);

INSERT INTO spareparts (kode_part, nama_part, kategori, stok, harga) VALUES
('PRT-001', 'Oli Mesin Motul 5100 1L', 'Cairan', 24, 85000),
('PRT-002', 'Rantai Keteng Butterfly 94L', 'Mesin', 5, 120000),
('PRT-003', 'Piston Forged Set 62mm', 'Tuning', 2, 950000),
('PRT-004', 'Baut Ancer Vario 160', 'Baut', 15, 15000);

-- CATATAN: Buat akun admin pertama lewat halaman Register,
-- lalu ubah kolom `role` di tabel `users` jadi 'admin' via phpMyAdmin/HeidiSQL.