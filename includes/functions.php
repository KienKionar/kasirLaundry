<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}
function getRole() {
    return $_SESSION['role'] ?? '';
}
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "/index.php");
        exit();
    }
}
function requireAdmin() {
    requireLogin();
    if (getRole() !== 'admin') {
        header("Location: " . BASE_URL . "/kasir/dashboard.php");
        exit();
    }
}
function requireKasir() {
    requireLogin();
    if (!in_array(getRole(), ['kasir', 'admin'])) {
        header("Location: " . BASE_URL . "/index.php");
        exit();
    }
}

// Sanitasi input XSS
function clean($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Format rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Generate kode transaksi unik berdasarkan kode outlet
function generateKodeTransaksi($kodeOutlet = 'LDR') {
    return strtoupper($kodeOutlet) . date('Ymd') . rand(100, 999);
}

// Ambil outlet_id dari session
function getOutletId() {
    return $_SESSION['outlet_id'] ?? null;
}
function getNamaOutlet() {
    return $_SESSION['nama_outlet'] ?? 'Semua Outlet';
}

// Validasi dan hitung diskon promo
function hitungDiskon($conn, $kodePromo, $totalHarga) {
    if (empty($kodePromo)) return ['diskon' => 0, 'promo_id' => null, 'pesan' => ''];

    $today = date('Y-m-d');
    $stmt  = $conn->prepare("
        SELECT * FROM promo
        WHERE kode_promo = ? AND status = 'aktif'
          AND berlaku_dari <= ? AND berlaku_hingga >= ?
          AND (kuota IS NULL OR terpakai < kuota)
    ");
    $stmt->bind_param("sss", $kodePromo, $today, $today);
    $stmt->execute();
    $promo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$promo) return ['diskon' => 0, 'promo_id' => null, 'pesan' => 'Kode promo tidak valid atau sudah kadaluarsa.'];
    if ($totalHarga < $promo['min_transaksi']) {
        return ['diskon' => 0, 'promo_id' => null, 'pesan' => 'Minimum transaksi ' . formatRupiah($promo['min_transaksi']) . ' untuk promo ini.'];
    }

    $diskon = 0;
    if ($promo['tipe_diskon'] === 'persen') {
        $diskon = ($totalHarga * $promo['nilai_diskon']) / 100;
        if ($promo['max_diskon'] && $diskon > $promo['max_diskon']) {
            $diskon = $promo['max_diskon'];
        }
    } else {
        $diskon = $promo['nilai_diskon'];
    }
    if ($diskon > $totalHarga) $diskon = $totalHarga;

    return ['diskon' => $diskon, 'promo_id' => $promo['id'], 'pesan' => 'OK', 'nama_promo' => $promo['nama_promo']];
}

define('BASE_URL', '/laundry');
