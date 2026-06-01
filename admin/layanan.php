<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle  = 'Data Layanan';
$activeMenu = 'layanan';
$conn       = getConnection();
$errors     = [];
$success    = '';

// Proses tambah/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaLayanan   = clean($_POST['nama_layanan'] ?? '');
    $hargaPerKg    = (float)($_POST['harga_per_kg'] ?? 0);
    $estimasiHari  = (int)($_POST['estimasi_hari'] ?? 1);
    $keterangan    = clean($_POST['keterangan'] ?? '');
    $status        = clean($_POST['status'] ?? 'aktif');
    $editId        = (int)($_POST['edit_id'] ?? 0);

    // Validasi
    if (empty($namaLayanan)) {
        $errors[] = "Nama layanan wajib diisi.";
    } elseif (strlen($namaLayanan) < 3) {
        $errors[] = "Nama layanan minimal 3 karakter.";
    }
    if ($hargaPerKg <= 0) {
        $errors[] = "Harga per kg harus lebih dari 0.";
    } elseif ($hargaPerKg > 1000000) {
        $errors[] = "Harga per kg terlalu besar.";
    }
    if ($estimasiHari < 1 || $estimasiHari > 30) {
        $errors[] = "Estimasi hari harus antara 1-30 hari.";
    }
    if (!in_array($status, ['aktif', 'nonaktif'])) {
        $errors[] = "Status tidak valid.";
    }

    if (empty($errors)) {
        if ($editId > 0) {
            $stmt = $conn->prepare("UPDATE layanan SET nama_layanan=?, harga_per_kg=?, estimasi_hari=?, keterangan=?, status=? WHERE id=?");
            $stmt->bind_param("sdissi", $namaLayanan, $hargaPerKg, $estimasiHari, $keterangan, $status, $editId);
            $stmt->execute();
            $success = "Layanan berhasil diperbarui!";
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO layanan (nama_layanan, harga_per_kg, estimasi_hari, keterangan, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sdiss", $namaLayanan, $hargaPerKg, $estimasiHari, $keterangan, $status);
            $stmt->execute();
            $success = "Layanan baru berhasil ditambahkan!";
            $stmt->close();
        }
    }
}

// Hapus layanan
if (isset($_GET['hapus'])) {
    $hapusId = (int)$_GET['hapus'];
    // Cek apakah ada transaksi
    $stmtCek = $conn->prepare("SELECT COUNT(*) as total FROM transaksi WHERE layanan_id = ?");
    $stmtCek->bind_param("i", $hapusId);
    $stmtCek->execute();
    $jumlah = $stmtCek->get_result()->fetch_assoc()['total'];
    $stmtCek->close();
    if ($jumlah > 0) {
        $errors[] = "Layanan tidak bisa dihapus karena sudah dipakai di $jumlah transaksi.";
    } else {
        $stmtDel = $conn->prepare("DELETE FROM layanan WHERE id = ?");
        $stmtDel->bind_param("i", $hapusId);
        $stmtDel->execute();
        $stmtDel->close();
        $success = "Layanan berhasil dihapus.";
    }
}

// Data untuk edit
$editData = null;
if (isset($_GET['edit'])) {
    $editId  = (int)$_GET['edit'];
    $stmtE   = $conn->prepare("SELECT * FROM layanan WHERE id = ?");
    $stmtE->bind_param("i", $editId);
    $stmtE->execute();
    $editData = $stmtE->get_result()->fetch_assoc();
    $stmtE->close();
}

$layananList = $conn->query("SELECT * FROM layanan ORDER BY nama_layanan");
$conn->close();
include '../includes/header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success alert-auto-hide"><i class="bi bi-check-circle-fill me-1"></i><?= $success ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><div><i class="bi bi-x-circle-fill me-1"></i><?= $e ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <!-- Form -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-tags me-1"></i> <?= $editData ? 'Edit Layanan' : 'Tambah Layanan' ?>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <?php if ($editData): ?>
                        <input type="hidden" name="edit_id" value="<?= $editData['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Layanan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_layanan"
                               value="<?= $editData ? clean($editData['nama_layanan']) : (isset($_POST['nama_layanan']) ? clean($_POST['nama_layanan']) : '') ?>"
                               placeholder="Contoh: Reguler" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Harga per kg (Rp) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="harga_per_kg"
                               value="<?= $editData ? $editData['harga_per_kg'] : (isset($_POST['harga_per_kg']) ? (float)$_POST['harga_per_kg'] : '') ?>"
                               placeholder="5000" min="0" step="500" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Estimasi Selesai (hari) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="estimasi_hari"
                               value="<?= $editData ? $editData['estimasi_hari'] : (isset($_POST['estimasi_hari']) ? (int)$_POST['estimasi_hari'] : '1') ?>"
                               min="1" max="30" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="2"
                                  placeholder="Deskripsi singkat layanan"><?= $editData ? clean($editData['keterangan']) : (isset($_POST['keterangan']) ? clean($_POST['keterangan']) : '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status">
                            <option value="aktif" <?= ($editData && $editData['status'] === 'aktif') || (!$editData) ? 'selected' : '' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($editData && $editData['status'] === 'nonaktif') ? 'selected' : '' ?>>Non-aktif</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save me-1"></i> <?= $editData ? 'Simpan Perubahan' : 'Tambah Layanan' ?>
                    </button>
                    <?php if ($editData): ?>
                        <a href="layanan.php" class="btn btn-outline-secondary w-100 mt-2">Batal</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-list-task me-1"></i> Daftar Layanan
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Layanan</th>
                            <th>Harga/kg</th>
                            <th>Estimasi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        if ($layananList->num_rows === 0):
                        ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada layanan</td></tr>
                        <?php else: while ($l = $layananList->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <?= clean($l['nama_layanan']) ?>
                                    <?php if ($l['keterangan']): ?>
                                        <div class="text-muted small"><?= clean($l['keterangan']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatRupiah($l['harga_per_kg']) ?></td>
                                <td><?= $l['estimasi_hari'] ?> hari</td>
                                <td>
                                    <span class="badge <?= $l['status'] === 'aktif' ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= ucfirst($l['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?edit=<?= $l['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="?hapus=<?= $l['id'] ?>" class="btn btn-sm btn-danger"
                                       onclick="return confirm('Yakin hapus layanan ini?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
