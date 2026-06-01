<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireKasir();

$pageTitle  = 'Daftar Transaksi';
$activeMenu = 'daftar-transaksi';
$conn       = getConnection();

// Filter pencarian
$search     = clean($_GET['search'] ?? '');
$filterStatus = clean($_GET['status'] ?? '');
$filterTgl  = clean($_GET['tgl'] ?? '');

// Bangun query dengan filter
$where = "WHERE 1=1";
$params = [];
$types  = "";

if (!empty($search)) {
    $where .= " AND (t.kode_transaksi LIKE ? OR p.nama LIKE ? OR p.no_hp LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= "sss";
}
if (!empty($filterStatus)) {
    $where .= " AND t.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}
if (!empty($filterTgl)) {
    $where .= " AND DATE(t.tanggal_masuk) = ?";
    $params[] = $filterTgl;
    $types .= "s";
}

$sql = "
    SELECT t.*, p.nama AS nama_pelanggan, l.nama_layanan, u.nama AS nama_kasir
    FROM transaksi t
    JOIN pelanggan p ON t.pelanggan_id = p.id
    JOIN layanan l ON t.layanan_id = l.id
    JOIN users u ON t.kasir_id = u.id
    $where
    ORDER BY t.tanggal_masuk DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$conn->close();

include '../includes/header.php';
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <div class="row align-items-center g-2">
            <div class="col-md-4 fw-semibold">
                <i class="bi bi-list-ul me-1"></i> Daftar Transaksi
            </div>
            <!-- Form filter -->
            <div class="col-md-8">
                <form method="GET" class="row g-2">
                    <div class="col-sm-4">
                        <input type="text" class="form-control form-control-sm" name="search"
                               value="<?= clean($search) ?>" placeholder="Cari kode/nama/HP...">
                    </div>
                    <div class="col-sm-3">
                        <select class="form-select form-select-sm" name="status">
                            <option value="">Semua Status</option>
                            <option value="belum_diproses" <?= $filterStatus === 'belum_diproses' ? 'selected' : '' ?>>Belum Diproses</option>
                            <option value="diproses"       <?= $filterStatus === 'diproses' ? 'selected' : '' ?>>Diproses</option>
                            <option value="selesai"        <?= $filterStatus === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                            <option value="diambil"        <?= $filterStatus === 'diambil' ? 'selected' : '' ?>>Diambil</option>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <input type="date" class="form-control form-control-sm" name="tgl" value="<?= $filterTgl ?>">
                    </div>
                    <div class="col-sm-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
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
                    <?php
                    $no = 1;
                    $badges = [
                        'belum_diproses' => ['badge-belum', 'Belum Diproses'],
                        'diproses'       => ['bg-primary text-white', 'Diproses'],
                        'selesai'        => ['bg-success text-white', 'Selesai'],
                        'diambil'        => ['bg-secondary text-white', 'Diambil'],
                    ];
                    if ($result->num_rows === 0):
                    ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">
                            Tidak ada transaksi ditemukan.
                        </td></tr>
                    <?php else: while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><code><?= clean($row['kode_transaksi']) ?></code></td>
                            <td>
                                <?= clean($row['nama_pelanggan']) ?>
                                <div class="text-muted small"><?= clean($row['no_hp'] ?? '') ?></div>
                            </td>
                            <td><?= clean($row['nama_layanan']) ?></td>
                            <td><?= $row['berat_kg'] ?> kg</td>
                            <td><?= formatRupiah($row['total_harga']) ?></td>
                            <td>
                                <?php $b = $badges[$row['status']] ?? ['bg-secondary text-white', $row['status']]; ?>
                                <span class="badge <?= $b[0] ?>"><?= $b[1] ?></span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($row['tanggal_masuk'])) ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>/kasir/detail_transaksi.php?id=<?= $row['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/kasir/struk.php?id=<?= $row['id'] ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Struk" target="_blank">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
