<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireKasir();

$pageTitle  = 'Data Pelanggan';
$activeMenu = 'pelanggan';
$conn       = getConnection();
$errors     = [];
$success    = '';

// Proses tambah/edit pelanggan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama    = clean($_POST['nama'] ?? '');
    $no_hp   = clean($_POST['no_hp'] ?? '');
    $alamat  = clean($_POST['alamat'] ?? '');
    $editId  = (int)($_POST['edit_id'] ?? 0);

    // Validasi
    if (empty($nama)) {
        $errors[] = "Nama pelanggan wajib diisi.";
    } elseif (strlen($nama) < 3) {
        $errors[] = "Nama minimal 3 karakter.";
    }
    if (empty($no_hp)) {
        $errors[] = "No. HP wajib diisi.";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $no_hp)) {
        $errors[] = "No. HP tidak valid (10-15 digit angka).";
    }

    if (empty($errors)) {
        if ($editId > 0) {
            // Update
            $stmt = $conn->prepare("UPDATE pelanggan SET nama=?, no_hp=?, alamat=? WHERE id=?");
            $stmt->bind_param("sssi", $nama, $no_hp, $alamat, $editId);
            $stmt->execute();
            $success = "Data pelanggan berhasil diperbarui!";
            $stmt->close();
        } else {
            // Cek duplikat HP
            $stmtCek = $conn->prepare("SELECT id FROM pelanggan WHERE no_hp = ?");
            $stmtCek->bind_param("s", $no_hp);
            $stmtCek->execute();
            if ($stmtCek->get_result()->num_rows > 0) {
                $errors[] = "No. HP sudah terdaftar!";
            } else {
                $stmt = $conn->prepare("INSERT INTO pelanggan (nama, no_hp, alamat) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $nama, $no_hp, $alamat);
                $stmt->execute();
                $success = "Pelanggan baru berhasil ditambahkan!";
                $stmt->close();
            }
            $stmtCek->close();
        }
    }
}

// Hapus pelanggan
if (isset($_GET['hapus'])) {
    $hapusId = (int)$_GET['hapus'];
    // Cek apakah pelanggan punya transaksi
    $stmtCek = $conn->prepare("SELECT COUNT(*) as total FROM transaksi WHERE pelanggan_id = ?");
    $stmtCek->bind_param("i", $hapusId);
    $stmtCek->execute();
    $jumlah = $stmtCek->get_result()->fetch_assoc()['total'];
    $stmtCek->close();

    if ($jumlah > 0) {
        $errors[] = "Pelanggan tidak bisa dihapus karena memiliki $jumlah transaksi.";
    } else {
        $stmtDel = $conn->prepare("DELETE FROM pelanggan WHERE id = ?");
        $stmtDel->bind_param("i", $hapusId);
        $stmtDel->execute();
        $stmtDel->close();
        $success = "Pelanggan berhasil dihapus.";
    }
}

// Data pelanggan untuk edit
$editData = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmtE  = $conn->prepare("SELECT * FROM pelanggan WHERE id = ?");
    $stmtE->bind_param("i", $editId);
    $stmtE->execute();
    $editData = $stmtE->get_result()->fetch_assoc();
    $stmtE->close();
}

// Ambil semua pelanggan
$search   = clean($_GET['search'] ?? '');
$whereSQL = "";
if (!empty($search)) {
    $like     = "%$search%";
    $stmtList = $conn->prepare("SELECT * FROM pelanggan WHERE nama LIKE ? OR no_hp LIKE ? ORDER BY nama");
    $stmtList->bind_param("ss", $like, $like);
} else {
    $stmtList = $conn->prepare("SELECT * FROM pelanggan ORDER BY nama");
}
$stmtList->execute();
$pelangganList = $stmtList->get_result();
$stmtList->close();
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
    <!-- Form Tambah/Edit -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person-plus me-1"></i>
                <?= $editData ? 'Edit Pelanggan' : 'Tambah Pelanggan' ?>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <?php if ($editData): ?>
                        <input type="hidden" name="edit_id" value="<?= $editData['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama"
                               value="<?= $editData ? clean($editData['nama']) : (isset($_POST['nama']) ? clean($_POST['nama']) : '') ?>"
                               placeholder="Nama lengkap" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">No. HP <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="no_hp"
                               value="<?= $editData ? clean($editData['no_hp']) : (isset($_POST['no_hp']) ? clean($_POST['no_hp']) : '') ?>"
                               placeholder="08xxx" maxlength="15" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alamat</label>
                        <textarea class="form-control" name="alamat" rows="2"
                                  placeholder="Alamat (opsional)"><?= $editData ? clean($editData['alamat']) : (isset($_POST['alamat']) ? clean($_POST['alamat']) : '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save me-1"></i>
                        <?= $editData ? 'Simpan Perubahan' : 'Tambah Pelanggan' ?>
                    </button>
                    <?php if ($editData): ?>
                        <a href="pelanggan.php" class="btn btn-outline-secondary w-100 mt-2">Batal</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Daftar Pelanggan -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-people me-1"></i> Daftar Pelanggan</span>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" name="search"
                           value="<?= clean($search) ?>" placeholder="Cari nama / HP...">
                    <button type="submit" class="btn btn-sm btn-primary">Cari</button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama</th>
                                <th>No. HP</th>
                                <th>Alamat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            if ($pelangganList->num_rows === 0):
                            ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data pelanggan</td></tr>
                            <?php else: while ($p = $pelangganList->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= clean($p['nama']) ?></td>
                                    <td><?= clean($p['no_hp']) ?></td>
                                    <td><?= clean($p['alamat']) ?: '-' ?></td>
                                    <td>
                                        <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="?hapus=<?= $p['id'] ?>" class="btn btn-sm btn-danger"
                                           title="Hapus"
                                           onclick="return confirm('Yakin hapus pelanggan ini?')">
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
</div>

<?php include '../includes/footer.php'; ?>
