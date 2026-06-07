<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle  = 'Kelola Outlet';
$activeMenu = 'outlet';
$conn       = getConnection();
$errors     = [];
$success    = '';

// Proses tambah/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaOutlet = clean($_POST['nama_outlet'] ?? '');
    $kodeOutlet = strtoupper(clean($_POST['kode_outlet'] ?? ''));
    $alamat     = clean($_POST['alamat'] ?? '');
    $noTelp     = clean($_POST['no_telp'] ?? '');
    $statusOut  = clean($_POST['status'] ?? 'aktif');
    $editId     = (int)($_POST['edit_id'] ?? 0);

    // Validasi
    if (empty($namaOutlet)) $errors[] = "Nama outlet wajib diisi.";
    elseif (strlen($namaOutlet) < 3) $errors[] = "Nama outlet minimal 3 karakter.";

    if (empty($kodeOutlet)) $errors[] = "Kode outlet wajib diisi.";
    elseif (!preg_match('/^[A-Z0-9]{2,10}$/', $kodeOutlet)) $errors[] = "Kode outlet hanya huruf kapital/angka, 2-10 karakter.";

    if (!empty($noTelp) && !preg_match('/^[0-9\-\+\(\) ]{7,20}$/', $noTelp)) {
        $errors[] = "Format no. telp tidak valid.";
    }
    if (!in_array($statusOut, ['aktif','nonaktif'])) $errors[] = "Status tidak valid.";

    if (empty($errors)) {
        // Cek duplikat kode
        $stmtCek = $conn->prepare("SELECT id FROM outlet WHERE kode_outlet = ? AND id != ?");
        $stmtCek->bind_param("si", $kodeOutlet, $editId);
        $stmtCek->execute();
        if ($stmtCek->get_result()->num_rows > 0) $errors[] = "Kode outlet '$kodeOutlet' sudah digunakan.";
        $stmtCek->close();
    }

    if (empty($errors)) {
        if ($editId > 0) {
            $stmt = $conn->prepare("UPDATE outlet SET nama_outlet=?,kode_outlet=?,alamat=?,no_telp=?,status=? WHERE id=?");
            $stmt->bind_param("sssssi", $namaOutlet, $kodeOutlet, $alamat, $noTelp, $statusOut, $editId);
            $stmt->execute(); $stmt->close();
            $success = "Outlet berhasil diperbarui!";
        } else {
            $stmt = $conn->prepare("INSERT INTO outlet (nama_outlet,kode_outlet,alamat,no_telp,status) VALUES (?,?,?,?,?)");
            $stmt->bind_param("sssss", $namaOutlet, $kodeOutlet, $alamat, $noTelp, $statusOut);
            $stmt->execute(); $stmt->close();
            $success = "Outlet baru berhasil ditambahkan!";
        }
    }
}

// Hapus outlet
if (isset($_GET['hapus'])) {
    $hapusId = (int)$_GET['hapus'];
    $stmtCek = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE outlet_id = ?");
    $stmtCek->bind_param("i", $hapusId); $stmtCek->execute();
    $jumlahUser = $stmtCek->get_result()->fetch_assoc()['total']; $stmtCek->close();

    $stmtCek2 = $conn->prepare("SELECT COUNT(*) AS total FROM transaksi WHERE outlet_id = ?");
    $stmtCek2->bind_param("i", $hapusId); $stmtCek2->execute();
    $jumlahTrx = $stmtCek2->get_result()->fetch_assoc()['total']; $stmtCek2->close();

    if ($jumlahUser > 0 || $jumlahTrx > 0) {
        $errors[] = "Outlet tidak bisa dihapus karena masih memiliki $jumlahUser user dan $jumlahTrx transaksi.";
    } else {
        $stmtDel = $conn->prepare("DELETE FROM outlet WHERE id = ?");
        $stmtDel->bind_param("i", $hapusId); $stmtDel->execute(); $stmtDel->close();
        $success = "Outlet berhasil dihapus.";
    }
}

// Edit data
$editData = null;
if (isset($_GET['edit'])) {
    $stmtE = $conn->prepare("SELECT * FROM outlet WHERE id = ?");
    $stmtE->bind_param("i", (int)$_GET['edit']); $stmtE->execute();
    $editData = $stmtE->get_result()->fetch_assoc(); $stmtE->close();
}

// Daftar outlet beserta jumlah kasir dan transaksi
$outletList = $conn->query("
    SELECT o.*,
           (SELECT COUNT(*) FROM users u WHERE u.outlet_id = o.id) AS jumlah_kasir,
           (SELECT COUNT(*) FROM transaksi t WHERE t.outlet_id = o.id) AS jumlah_transaksi
    FROM outlet o ORDER BY o.nama_outlet
");
$conn->close();
include '../includes/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success alert-auto-hide"><i class="bi bi-check-circle-fill me-1"></i><?= $success ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><?php foreach($errors as $e): ?><div><i class="bi bi-x-circle-fill me-1"></i><?= $e ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="row g-3">
    <!-- Form -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-shop me-1 text-primary"></i> <?= $editData ? 'Edit Outlet' : 'Tambah Outlet' ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($editData): ?>
                    <input type="hidden" name="edit_id" value="<?= $editData['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Outlet <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_outlet"
                               value="<?= $editData ? clean($editData['nama_outlet']) : '' ?>"
                               placeholder="Contoh: LaundryKu Pusat" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kode Outlet <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase" name="kode_outlet"
                               value="<?= $editData ? clean($editData['kode_outlet']) : '' ?>"
                               placeholder="Contoh: LDR01" maxlength="10"
                               oninput="this.value=this.value.toUpperCase()" required>
                        <div class="form-text">Dipakai sebagai prefix kode transaksi</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">No. Telp</label>
                        <input type="text" class="form-control" name="no_telp"
                               value="<?= $editData ? clean($editData['no_telp']) : '' ?>"
                               placeholder="021-12345678">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alamat</label>
                        <textarea class="form-control" name="alamat" rows="2"><?= $editData ? clean($editData['alamat']) : '' ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status">
                            <option value="aktif"     <?= ($editData && $editData['status']==='aktif')     ? 'selected' : (!$editData ? 'selected':'') ?>>Aktif</option>
                            <option value="nonaktif"  <?= ($editData && $editData['status']==='nonaktif')  ? 'selected' : '' ?>>Non-aktif</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save me-1"></i> <?= $editData ? 'Simpan Perubahan' : 'Tambah Outlet' ?>
                    </button>
                    <?php if ($editData): ?>
                    <a href="outlet.php" class="btn btn-outline-secondary w-100 mt-2">Batal</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel Outlet -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-building me-1"></i> Daftar Outlet
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>#</th><th>Outlet</th><th>Kode</th><th>Telp</th><th>Kasir</th><th>Transaksi</th><th>Status</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($outletList->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Belum ada outlet</td></tr>
                    <?php else: $no=1; while($o = $outletList->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div class="fw-semibold"><?= clean($o['nama_outlet']) ?></div>
                                <div class="text-muted small"><?= clean($o['alamat']) ?: '-' ?></div>
                            </td>
                            <td><span class="badge bg-dark"><?= clean($o['kode_outlet']) ?></span></td>
                            <td><?= clean($o['no_telp']) ?: '-' ?></td>
                            <td><span class="badge bg-primary"><?= $o['jumlah_kasir'] ?></span></td>
                            <td><span class="badge bg-info text-dark"><?= $o['jumlah_transaksi'] ?></span></td>
                            <td><span class="badge <?= $o['status']==='aktif'?'bg-success':'bg-secondary' ?>"><?= ucfirst($o['status']) ?></span></td>
                            <td>
                                <a href="?edit=<?= $o['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <a href="?hapus=<?= $o['id'] ?>" class="btn btn-sm btn-danger"
                                   onclick="return confirm('Yakin hapus outlet <?= clean($o['nama_outlet']) ?>?')">
                                   <i class="bi bi-trash"></i></a>
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
