<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireKasir();

$pageTitle  = 'Detail Transaksi';
$activeMenu = 'daftar-transaksi';
$conn       = getConnection();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: " . BASE_URL . "/kasir/daftar_transaksi.php");
    exit();
}

$success = '';
$error   = '';

// Proses update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = clean($_POST['status'] ?? '');
    $allowed   = ['belum_diproses', 'diproses', 'selesai', 'diambil'];

    if (!in_array($newStatus, $allowed)) {
        $error = "Status tidak valid.";
    } else {
        $stmtU = $conn->prepare("UPDATE transaksi SET status = ? WHERE id = ?");
        $stmtU->bind_param("si", $newStatus, $id);
        if ($stmtU->execute()) {
            $success = "Status berhasil diperbarui!";
        } else {
            $error = "Gagal memperbarui status.";
        }
        $stmtU->close();
    }
}

// Ambil detail transaksi
$stmt = $conn->prepare("
    SELECT t.*, p.nama AS nama_pelanggan, p.no_hp, p.alamat,
           l.nama_layanan, l.harga_per_kg,
           u.nama AS nama_kasir
    FROM transaksi t
    JOIN pelanggan p ON t.pelanggan_id = p.id
    JOIN layanan l ON t.layanan_id = l.id
    JOIN users u ON t.kasir_id = u.id
    WHERE t.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$trx) {
    die("Transaksi tidak ditemukan.");
}

include '../includes/header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success alert-auto-hide d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i> <?= $success ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-x-circle-fill"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Info Transaksi -->
    <div class="col-md-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-receipt me-1"></i> Detail Transaksi
                <code class="ms-2"><?= clean($trx['kode_transaksi']) ?></code>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted w-40">Tanggal Masuk</td>
                        <td><?= date('d/m/Y H:i', strtotime($trx['tanggal_masuk'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Est. Selesai</td>
                        <td><?= date('d/m/Y', strtotime($trx['tanggal_selesai'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Pelanggan</td>
                        <td><?= clean($trx['nama_pelanggan']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">No. HP</td>
                        <td><?= clean($trx['no_hp']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Alamat</td>
                        <td><?= clean($trx['alamat']) ?: '-' ?></td>
                    </tr>
                    <tr><td colspan="2"><hr class="my-1"></td></tr>
                    <tr>
                        <td class="text-muted">Layanan</td>
                        <td><?= clean($trx['nama_layanan']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Berat</td>
                        <td><?= $trx['berat_kg'] ?> kg × <?= formatRupiah($trx['harga_per_kg']) ?>/kg</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Total Harga</td>
                        <td class="fw-bold text-primary fs-5"><?= formatRupiah($trx['total_harga']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Bayar</td>
                        <td><?= formatRupiah($trx['bayar']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Kembalian</td>
                        <td class="fw-bold text-success"><?= formatRupiah($trx['kembalian']) ?></td>
                    </tr>
                    <tr><td colspan="2"><hr class="my-1"></td></tr>
                    <tr>
                        <td class="text-muted">Kasir</td>
                        <td><?= clean($trx['nama_kasir']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Catatan</td>
                        <td><?= clean($trx['catatan']) ?: '-' ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Update Status -->
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-arrow-repeat me-1"></i> Update Status
            </div>
            <div class="card-body">
                <p class="text-muted small">Status saat ini:</p>
                <?php
                $badges = [
                    'belum_diproses' => ['badge-belum', 'Belum Diproses'],
                    'diproses'       => ['bg-primary', 'Diproses'],
                    'selesai'        => ['bg-success', 'Selesai'],
                    'diambil'        => ['bg-secondary', 'Diambil'],
                ];
                $b = $badges[$trx['status']] ?? ['bg-secondary', $trx['status']];
                ?>
                <span class="badge <?= $b[0] ?> fs-6 mb-3"><?= $b[1] ?></span>

                <form action="" method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Ubah Status</label>
                        <select class="form-select" name="status">
                            <option value="belum_diproses" <?= $trx['status'] === 'belum_diproses' ? 'selected' : '' ?>>Belum Diproses</option>
                            <option value="diproses"       <?= $trx['status'] === 'diproses' ? 'selected' : '' ?>>Diproses</option>
                            <option value="selesai"        <?= $trx['status'] === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                            <option value="diambil"        <?= $trx['status'] === 'diambil' ? 'selected' : '' ?>>Diambil</option>
                        </select>
                    </div>
                    <button type="submit" name="update_status" class="btn btn-primary w-100">
                        <i class="bi bi-check-circle me-1"></i> Simpan Status
                    </button>
                </form>
            </div>
        </div>

        <!-- Tombol aksi -->
        <div class="d-grid gap-2 mt-3">
            <a href="<?= BASE_URL ?>/kasir/struk.php?id=<?= $trx['id'] ?>" target="_blank"
               class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Cetak Struk
            </a>
            <a href="daftar_transaksi.php" class="btn btn-outline-dark">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
