<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle  = 'Manajemen Shift Kasir';
$activeMenu = 'shift';
$conn       = getConnection();
$errors     = [];
$success    = '';

// Ambil daftar outlet & kasir untuk form
$outletList = $conn->query("SELECT * FROM outlet WHERE status='aktif' ORDER BY nama_outlet");
$kasirList  = $conn->query("SELECT u.*, o.nama_outlet FROM users u LEFT JOIN outlet o ON u.outlet_id=o.id WHERE u.role='kasir' ORDER BY u.nama");

// Proses tambah/edit shift
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $outletId   = (int)($_POST['outlet_id'] ?? 0);
    $userId     = (int)($_POST['user_id'] ?? 0);
    $namaShift  = clean($_POST['nama_shift'] ?? '');
    $tanggal    = clean($_POST['tanggal'] ?? '');
    $jamMulai   = clean($_POST['jam_mulai'] ?? '');
    $jamSelesai = clean($_POST['jam_selesai'] ?? '');
    $keterangan = clean($_POST['keterangan'] ?? '');
    $editId     = (int)($_POST['edit_id'] ?? 0);

    // Validasi
    if ($outletId <= 0)     $errors[] = "Pilih outlet.";
    if ($userId <= 0)       $errors[] = "Pilih kasir.";
    if (empty($namaShift))  $errors[] = "Nama shift wajib diisi.";
    if (empty($tanggal))    $errors[] = "Tanggal wajib diisi.";
    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) $errors[] = "Format tanggal tidak valid.";
    if (empty($jamMulai))   $errors[] = "Jam mulai wajib diisi.";
    if (empty($jamSelesai)) $errors[] = "Jam selesai wajib diisi.";
    elseif ($jamSelesai <= $jamMulai) $errors[] = "Jam selesai harus lebih dari jam mulai.";

    if (empty($errors)) {
        if ($editId > 0) {
            $stmt = $conn->prepare("UPDATE shift SET outlet_id=?,user_id=?,nama_shift=?,tanggal=?,jam_mulai=?,jam_selesai=?,keterangan=? WHERE id=?");
            $stmt->bind_param("iisssssi", $outletId,$userId,$namaShift,$tanggal,$jamMulai,$jamSelesai,$keterangan,$editId);
        } else {
            $stmt = $conn->prepare("INSERT INTO shift (outlet_id,user_id,nama_shift,tanggal,jam_mulai,jam_selesai,keterangan) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("iisssss", $outletId,$userId,$namaShift,$tanggal,$jamMulai,$jamSelesai,$keterangan);
        }
        $stmt->execute(); $stmt->close();
        $success = $editId > 0 ? "Shift berhasil diperbarui!" : "Shift berhasil ditambahkan!";
    }
}

// Hapus
if (isset($_GET['hapus'])) {
    $stmtDel = $conn->prepare("DELETE FROM shift WHERE id = ?");
    $stmtDel->bind_param("i", (int)$_GET['hapus']); $stmtDel->execute(); $stmtDel->close();
    $success = "Shift berhasil dihapus.";
}

// Edit data
$editData = null;
if (isset($_GET['edit'])) {
    $stmtE = $conn->prepare("SELECT * FROM shift WHERE id=?");
    $stmtE->bind_param("i", (int)$_GET['edit']); $stmtE->execute();
    $editData = $stmtE->get_result()->fetch_assoc(); $stmtE->close();
}

// Filter outlet
$filterOutlet = (int)($_GET['outlet_id'] ?? 0);
$filterTgl    = clean($_GET['tgl'] ?? date('Y-m-d'));

$sqlShift = "
    SELECT s.*, o.nama_outlet, u.nama AS nama_kasir
    FROM shift s
    JOIN outlet o ON s.outlet_id = o.id
    JOIN users u ON s.user_id = u.id
    WHERE 1=1
";
if ($filterOutlet > 0) $sqlShift .= " AND s.outlet_id = $filterOutlet";
if (!empty($filterTgl)) $sqlShift .= " AND s.tanggal = '$filterTgl'";
$sqlShift .= " ORDER BY s.tanggal DESC, s.jam_mulai";
$shiftList = $conn->query($sqlShift);
$conn->close();

include '../includes/header.php';
?>

<?php if ($success): ?><div class="alert alert-success alert-auto-hide"><i class="bi bi-check-circle-fill me-1"></i><?= $success ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?php foreach($errors as $e): ?><div><i class="bi bi-x-circle-fill me-1"></i><?= $e ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="row g-3">
    <!-- Form -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-calendar-plus me-1 text-primary"></i> <?= $editData ? 'Edit Shift' : 'Tambah Shift' ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($editData): ?><input type="hidden" name="edit_id" value="<?= $editData['id'] ?>"><?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Outlet <span class="text-danger">*</span></label>
                        <select class="form-select" name="outlet_id" id="outletSelect" required>
                            <option value="">-- Pilih Outlet --</option>
                            <?php $outletList->data_seek(0); while($o=$outletList->fetch_assoc()): ?>
                            <option value="<?= $o['id'] ?>" <?= ($editData && $editData['outlet_id']==$o['id']) ? 'selected' : '' ?>>
                                <?= clean($o['nama_outlet']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kasir <span class="text-danger">*</span></label>
                        <select class="form-select" name="user_id" required>
                            <option value="">-- Pilih Kasir --</option>
                            <?php $kasirList->data_seek(0); while($k=$kasirList->fetch_assoc()): ?>
                            <option value="<?= $k['id'] ?>" <?= ($editData && $editData['user_id']==$k['id']) ? 'selected' : '' ?>
                                    data-outlet="<?= $k['outlet_id'] ?>">
                                <?= clean($k['nama']) ?> <?= $k['nama_outlet'] ? '('.clean($k['nama_outlet']).')' : '' ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Shift <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_shift"
                               value="<?= $editData ? clean($editData['nama_shift']) : '' ?>"
                               placeholder="Contoh: Shift Pagi" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tanggal"
                               value="<?= $editData ? $editData['tanggal'] : date('Y-m-d') ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-semibold">Jam Mulai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="jam_mulai"
                                   value="<?= $editData ? $editData['jam_mulai'] : '' ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-semibold">Jam Selesai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="jam_selesai"
                                   value="<?= $editData ? $editData['jam_selesai'] : '' ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Keterangan</label>
                        <input type="text" class="form-control" name="keterangan"
                               value="<?= $editData ? clean($editData['keterangan']) : '' ?>" placeholder="Opsional">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save me-1"></i> <?= $editData ? 'Simpan Perubahan' : 'Tambah Shift' ?>
                    </button>
                    <?php if ($editData): ?><a href="shift.php" class="btn btn-outline-secondary w-100 mt-2">Batal</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel Shift -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-auto fw-semibold"><i class="bi bi-calendar3 me-1"></i>Jadwal Shift</div>
                    <div class="col">
                        <select class="form-select form-select-sm" name="outlet_id">
                            <option value="">Semua Outlet</option>
                            <?php $outletList->data_seek(0); while($o=$outletList->fetch_assoc()): ?>
                            <option value="<?= $o['id'] ?>" <?= $filterOutlet==$o['id']?'selected':'' ?>><?= clean($o['nama_outlet']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <input type="date" class="form-control form-control-sm" name="tgl" value="<?= $filterTgl ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <a href="shift.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Outlet</th><th>Kasir</th><th>Shift</th><th>Tanggal</th><th>Jam</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php if ($shiftList->num_rows === 0): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada shift ditemukan</td></tr>
                    <?php else: while($s=$shiftList->fetch_assoc()): ?>
                        <tr>
                            <td><?= clean($s['nama_outlet']) ?></td>
                            <td><?= clean($s['nama_kasir']) ?></td>
                            <td><span class="badge bg-primary"><?= clean($s['nama_shift']) ?></span></td>
                            <td><?= date('d/m/Y', strtotime($s['tanggal'])) ?></td>
                            <td><?= substr($s['jam_mulai'],0,5) ?> – <?= substr($s['jam_selesai'],0,5) ?></td>
                            <td>
                                <a href="?edit=<?= $s['id'] ?>&tgl=<?= $filterTgl ?>&outlet_id=<?= $filterOutlet ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <a href="?hapus=<?= $s['id'] ?>&tgl=<?= $filterTgl ?>&outlet_id=<?= $filterOutlet ?>" class="btn btn-sm btn-danger"
                                   onclick="return confirm('Hapus shift ini?')"><i class="bi bi-trash"></i></a>
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
