-- =============================================
-- DATABASE SETUP - SISTEM KASIR LAUNDRY
-- Jalankan file ini sekali untuk setup database
-- =============================================

CREATE DATABASE IF NOT EXISTS laundry_db CHARACTER SET utf8 COLLATE utf8_general_ci;
USE laundry_db;

-- Tabel users (admin & kasir)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'kasir') NOT NULL DEFAULT 'kasir',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel layanan laundry
CREATE TABLE IF NOT EXISTS layanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_layanan VARCHAR(100) NOT NULL,
    harga_per_kg DECIMAL(10,2) NOT NULL,
    estimasi_hari INT NOT NULL DEFAULT 1,
    keterangan TEXT,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel pelanggan
CREATE TABLE IF NOT EXISTS pelanggan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    alamat TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel transaksi
CREATE TABLE IF NOT EXISTS transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_transaksi VARCHAR(20) NOT NULL UNIQUE,
    pelanggan_id INT NOT NULL,
    layanan_id INT NOT NULL,
    kasir_id INT NOT NULL,
    berat_kg DECIMAL(5,2) NOT NULL,
    total_harga DECIMAL(10,2) NOT NULL,
    bayar DECIMAL(10,2),
    kembalian DECIMAL(10,2),
    status ENUM('belum_diproses','diproses','selesai','diambil') DEFAULT 'belum_diproses',
    tanggal_masuk TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tanggal_selesai DATE,
    catatan TEXT,
    FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id),
    FOREIGN KEY (layanan_id) REFERENCES layanan(id),
    FOREIGN KEY (kasir_id) REFERENCES users(id)
);

-- =============================================
-- DATA AWAL (SEED DATA)
-- =============================================

-- Akun default: admin dan kasir
-- Password: admin123 (sudah di-hash dengan password_hash)
INSERT INTO users (nama, username, password, role) VALUES
('Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Kasir Satu', 'kasir1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kasir');
-- Password untuk semua akun di atas adalah: password

-- Layanan laundry
INSERT INTO layanan (nama_layanan, harga_per_kg, estimasi_hari, keterangan) VALUES
('Reguler', 5000.00, 3, 'Cuci + setrika, selesai 3 hari'),
('Express', 10000.00, 1, 'Cuci + setrika, selesai 1 hari'),
('Cuci Kering', 4000.00, 3, 'Cuci tanpa setrika'),
('Setrika Saja', 3000.00, 1, 'Setrika tanpa cuci');

-- Pelanggan contoh
INSERT INTO pelanggan (nama, no_hp, alamat) VALUES
('Budi Santoso', '08123456789', 'Jl. Merdeka No. 10'),
('Siti Rahayu', '08234567890', 'Jl. Pahlawan No. 5');
