<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle  = 'Laporan & Statistik';
$activeMenu = 'admin-dashboard';
$conn       = getConnection();

// Filter bulan & tahun
$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
if ($bulan < 1 || $bulan > 12) $bulan = date('m');
if ($tahun < 2020 || $tahun > 2030) $tahun = date('Y');
$periode = sprintf('%04d-%02d', $tahun, $bulan);

// Total transaksi bulan ini
$q1 = $conn->query("SELECT COUNT(*) as total FROM transaksi WHERE DATE_FORMAT(tanggal_masuk,'%Y-%m') = '$periode'");
$totalTrx = $q1->fetch_assoc()['total'];

// Total pendapatan bulan ini
$q2 = $conn->query("SELECT IFNULL(SUM(total_harga),0) as total FROM transaksi WHERE DATE_FORMAT(tanggal_masuk,'%Y-%m') = '$periode'");
$totalPendapatan = $q2->fetch_assoc()['total'];

// Total pelanggan
$q3 = $conn->query("SELECT COUNT(*) as total FROM pelanggan");
$totalPelanggan = $q3->fetch_assoc()['total'];

// Total kg cucian bulan ini
$q4 = $conn->query("SELECT IFNULL(SUM(berat_kg),0) as total FROM transaksi WHERE DATE_FORMAT(tanggal_masuk,'%Y-%m') = '$periode'");
$totalKg = $q4->fetch_assoc()['total'];

// Transaksi per hari (untuk grafik sederhana)
$qHarian = $conn->query("
    SELECT DATE(tanggal_masuk) as tgl, COUNT(*) as jumlah, SUM(total_harga) as pendapatan
    FROM transaksi
    WHERE DATE_FORMAT(tanggal_masuk,'%Y-%m') = '$periode'
    GROUP BY DATE(tanggal_masuk)
    ORDER BY tgl
");
$harian = [];
while ($row = $qHarian->fetch_assoc()) {
    $harian[] = $row;
}

// Transaksi per kasir bulan ini
$qKasir = $conn->query("
    SELECT u.nama, COUNT(*) as jumlah, SUM(t.total_harga) as pendapatan
    FROM transaksi t
    JOIN users u ON t.kasir_id = u.id
    WHERE DATE_FORMAT(t.tanggal_masuk,'%Y-%m') = '$periode'
    GROUP BY t.kasir_id, u.nama
    ORDER BY pendapatan DESC
");

// Layanan terlaris
$qLayanan = $conn->query("
    SELECT l.nama_layanan, COUNT(*) as jumlah, SUM(t.total_harga) as pendapatan
    FROM transaksi t
    JOIN layanan l ON t.layanan_id = l.id
    WHERE DATE_FORMAT(t.tanggal_masuk,'%Y-%m') = '$periode'
    GROUP BY t.layanan_id, l.nama_layanan
    ORDER BY jumlah DESC
");

// 10 transaksi terbaru bulan ini
$qTerbaru = $conn->query("
    SELECT t.*, p.nama AS nama_pelanggan, l.nama_layanan, u.nama AS nama_kasir
    FROM transaksi t
    JOIN pelanggan p ON t.pelanggan_id = p.id
    JOIN layanan l ON t.layanan_id = l.id
    JOIN users u ON t.kasir_id = u.id
    WHERE DATE_FORMAT(t.tanggal_masuk,'%Y-%m') = '$periode'
    ORDER BY t.tanggal_masuk DESC
    LIMIT 10
");

$conn->close();
include '../includes/header.php';

// Nama bulan Indonesia
$namaBulan = [
    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
    7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
];
?>

<!-- Filter Periode -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="col-form-label small fw-semibold">Periode:</label>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" name="bulan">
                    <?php for ($i=1; $i<=12; $i++): ?>
                        <option value="<?= $i ?>" <?= $i == $bulan ? 'selected' : '' ?>><?= $namaBulan[$i] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" name="tahun">
                    <?php for ($y = date('Y'); $y >= 2023; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == $tahun ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">Tampilkan</button>
            </div>
            <div class="col-auto ms-auto">
                <span class="badge bg-secondary"><?= $namaBulan[$bulan] ?> <?= $tahun ?></span>
            </div>
        </form>
    </div>
</div>

<!-- Statistik Ringkasan -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="fs-1"><i class="bi bi-receipt-cutoff"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?= $totalTrx ?></div>
                    <div class="small opacity-75">Total Transaksi</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="fs-1"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="fw-bold" style="font-size:1.1rem"><?= formatRupiah($totalPendapatan) ?></div>
                    <div class="small opacity-75">Total Pendapatan</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-warning text-dark">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="fs-1"><i class="bi bi-people"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?= $totalPelanggan ?></div>
                    <div class="small opacity-75">Total Pelanggan</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="fs-1"><i class="bi bi-basket2"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?= number_format($totalKg, 1) ?> kg</div>
                    <div class="small opacity-75">Total Cucian</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Performa per Kasir -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person-badge me-1"></i> Performa Kasir
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>Kasir</th><th>Transaksi</th><th>Pendapatan</th></tr></thead>
                    <tbody>
                        <?php if ($qKasir->num_rows === 0): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Belum ada data</td></tr>
                        <?php else: while ($k = $qKasir->fetch_assoc()): ?>
                            <tr>
                                <td><?= clean($k['nama']) ?></td>
                                <td><span class="badge bg-primary"><?= $k['jumlah'] ?></span></td>
                                <td><?= formatRupiah($k['pendapatan']) ?></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Layanan Terlaris -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-star me-1"></i> Layanan Terlaris
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>Layanan</th><th>Transaksi</th><th>Pendapatan</th></tr></thead>
                    <tbody>
                        <?php if ($qLayanan->num_rows === 0): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Belum ada data</td></tr>
                        <?php else: while ($l = $qLayanan->fetch_assoc()): ?>
                            <tr>
                                <td><?= clean($l['nama_layanan']) ?></td>
                                <td><span class="badge bg-success"><?= $l['jumlah'] ?></span></td>
                                <td><?= formatRupiah($l['pendapatan']) ?></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Transaksi Terbaru -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-table me-1"></i> Transaksi Bulan <?= $namaBulan[$bulan] ?> <?= $tahun ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Pelanggan</th>
                        <th>Layanan</th>
                        <th>Berat</th>
                        <th>Total</th>
                        <th>Kasir</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $badges = [
                        'belum_diproses' => ['badge-belum', 'Belum Diproses'],
                        'diproses'       => ['bg-primary text-white', 'Diproses'],
                        'selesai'        => ['bg-success text-white', 'Selesai'],
                        'diambil'        => ['bg-secondary text-white', 'Diambil'],
                    ];
                    if ($qTerbaru->num_rows === 0):
                    ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada transaksi di periode ini</td></tr>
                    <?php else: while ($row = $qTerbaru->fetch_assoc()): ?>
                        <tr>
                            <td><code><?= clean($row['kode_transaksi']) ?></code></td>
                            <td><?= clean($row['nama_pelanggan']) ?></td>
                            <td><?= clean($row['nama_layanan']) ?></td>
                            <td><?= $row['berat_kg'] ?> kg</td>
                            <td><?= formatRupiah($row['total_harga']) ?></td>
                            <td><?= clean($row['nama_kasir']) ?></td>
                            <td>
                                <?php $b = $badges[$row['status']] ?? ['bg-secondary text-white', $row['status']]; ?>
                                <span class="badge <?= $b[0] ?>"><?= $b[1] ?></span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($row['tanggal_masuk'])) ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
