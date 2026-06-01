<?php
// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Cek role user
function getRole() {
    return $_SESSION['role'] ?? '';
}

// Redirect jika belum login
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "/index.php");
        exit();
    }
}

// Redirect jika bukan admin
function requireAdmin() {
    requireLogin();
    if (getRole() !== 'admin') {
        header("Location: " . BASE_URL . "/kasir/dashboard.php");
        exit();
    }
}

// Redirect jika bukan kasir (admin boleh akses kasir)
function requireKasir() {
    requireLogin();
    if (!in_array(getRole(), ['kasir', 'admin'])) {
        header("Location: " . BASE_URL . "/index.php");
        exit();
    }
}

// Sanitasi input untuk mencegah XSS
function clean($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Format rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Generate kode transaksi unik
function generateKodeTransaksi() {
    return 'LDR' . date('Ymd') . rand(100, 999);
}

// Base URL (sesuaikan jika subfolder berbeda)
define('BASE_URL', '/laundry');
?>
