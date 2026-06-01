<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireKasir(); // Hanya kasir dan admin yang bisa akses

$pageTitle  = 'Dashboard Kasir';
$activeMenu = 'kasir-dashboard';
$conn       = getConnection();

// Ambil statistik hari ini
$today = date('Y-m-d');

// Total transaksi hari ini
$q1 = $conn->query("SELECT COUNT(*) as total FROM transaksi WHERE DATE(tanggal_masuk) = '$today'");
$totalHariIni = $q1->fetch_assoc()['total'];

// Pendapatan hari ini
$q2 = $conn->query("SELECT IFNULL(SUM(total_harga),0) as total FROM transaksi WHERE DATE(tanggal_masuk) = '$today'");
$pendapatanHariIni = $q2->fetch_assoc()['total'];

// Transaksi pending (belum diproses)
$q3 = $conn->query("SELECT COUNT(*) as total FROM transaksi WHERE status = 'belum_diproses'");
$pending = $q3->fetch_assoc()['total'];

// Transaksi sudah selesai tapi belum diambil
$q4 = $conn->query("SELECT COUNT(*) as total FROM transaksi WHERE status = 'selesai'");
$siapDiambil = $q4->fetch_assoc()['total'];

// Daftar transaksi terbaru (10 terakhir)
$qRecent = $conn->query("
    SELECT t.*, p.nama AS nama_pelanggan, l.nama_layanan
    FROM transaksi t
    JOIN pelanggan p ON t.pelanggan_id = p.id
    JOIN layanan l ON t.layanan_id = l.id
    ORDER BY t.tanggal_masuk DESC
    LIMIT 10
");

$conn->close();
include '../includes/header.php';
?>

<!-- Kartu Statistik -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="fs-1"><i class="bi bi-receipt"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?= $totalHariIni ?></div>
                    <div class="small opacity-75">Transaksi Hari Ini</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="fs-1"><i class="bi bi-cash-coin"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?= formatRupiah($pendapatanHariIni) ?></div>
                    <div class="small opacity-75">Pendapatan Hari Ini</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-warning text-dark">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="fs-1"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?= $pending ?></div>
                    <div class="small opacity-75">Menunggu Diproses</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="fs-1"><i class="bi bi-bag-check"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?= $siapDiambil ?></div>
                    <div class="small opacity-75">Siap Diambil</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tombol Aksi Cepat -->
<div class="d-flex gap-2 mb-4">
    <a href="<?= BASE_URL ?>/kasir/transaksi_baru.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Transaksi Baru
    </a>
    <a href="<?= BASE_URL ?>/kasir/daftar_transaksi.php" class="btn btn-outline-secondary">
        <i class="bi bi-list-ul me-1"></i> Semua Transaksi
    </a>
</div>

<!-- Tabel Transaksi Terbaru -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-clock-history me-1"></i> Transaksi Terbaru
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
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($qRecent->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Belum ada transaksi</td></tr>
                    <?php else: ?>
                        <?php while ($row = $qRecent->fetch_assoc()): ?>
                        <tr>
                            <td><code><?= clean($row['kode_transaksi']) ?></code></td>
                            <td><?= clean($row['nama_pelanggan']) ?></td>
                            <td><?= clean($row['nama_layanan']) ?></td>
                            <td><?= $row['berat_kg'] ?> kg</td>
                            <td><?= formatRupiah($row['total_harga']) ?></td>
                            <td>
                                <?php
                                $badges = [
                                    'belum_diproses' => ['badge-belum', 'Belum Diproses'],
                                    'diproses'       => ['bg-primary', 'Diproses'],
                                    'selesai'        => ['bg-success', 'Selesai'],
                                    'diambil'        => ['bg-secondary', 'Diambil'],
                                ];
                                $b = $badges[$row['status']] ?? ['bg-secondary', $row['status']];
                                ?>
                                <span class="badge <?= $b[0] ?>"><?= $b[1] ?></span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($row['tanggal_masuk'])) ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>/kasir/detail_transaksi.php?id=<?= $row['id'] ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
