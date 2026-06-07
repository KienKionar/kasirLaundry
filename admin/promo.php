<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle  = 'Kode Promo';
$activeMenu = 'promo';
$conn       = getConnection();
$errors     = [];
$success    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kodePromo    = strtoupper(clean($_POST['kode_promo'] ?? ''));
    $namaPromo    = clean($_POST['nama_promo'] ?? '');
    $tipeDiskon   = clean($_POST['tipe_diskon'] ?? 'persen');
    $nilaiDiskon  = (float)($_POST['nilai_diskon'] ?? 0);
    $maxDiskon    = $_POST['max_diskon'] !== '' ? (float)$_POST['max_diskon'] : null;
    $minTransaksi = (float)($_POST['min_transaksi'] ?? 0);
    $berlakuDari  = clean($_POST['berlaku_dari'] ?? '');
    $berlakuHingga= clean($_POST['berlaku_hingga'] ?? '');
    $kuota        = $_POST['kuota'] !== '' ? (int)$_POST['kuota'] : null;
    $statusPromo  = clean($_POST['status'] ?? 'aktif');
    $editId       = (int)($_POST['edit_id'] ?? 0);

    // Validasi
    if (empty($kodePromo)) $errors[] = "Kode promo wajib diisi.";
    elseif (!preg_match('/^[A-Z0-9_]{3,20}$/', $kodePromo)) $errors[] = "Kode promo hanya huruf kapital, angka, underscore (3-20 karakter).";
    if (empty($namaPromo)) $errors[] = "Nama promo wajib diisi.";
    if (!in_array($tipeDiskon, ['persen','nominal'])) $errors[] = "Tipe diskon tidak valid.";
    if ($nilaiDiskon <= 0) $errors[] = "Nilai diskon harus lebih dari 0.";
    if ($tipeDiskon === 'persen' && $nilaiDiskon > 100) $errors[] = "Diskon persen maksimal 100%.";
    if (empty($berlakuDari) || empty($berlakuHingga)) $errors[] = "Tanggal berlaku wajib diisi.";
    elseif ($berlakuHingga < $berlakuDari) $errors[] = "Tanggal akhir harus setelah tanggal mulai.";
    if ($minTransaksi < 0) $errors[] = "Minimum transaksi tidak boleh negatif.";
    if ($kuota !== null && $kuota < 1) $errors[] = "Kuota minimal 1 jika diisi.";

    if (empty($errors)) {
        $stmtCek = $conn->prepare("SELECT id FROM promo WHERE kode_promo=? AND id!=?");
        $stmtCek->bind_param("si", $kodePromo, $editId); $stmtCek->execute();
        if ($stmtCek->get_result()->num_rows > 0) $errors[] = "Kode promo '$kodePromo' sudah digunakan.";
        $stmtCek->close();
    }

    if (empty($errors)) {
        if ($editId > 0) {
            $stmt = $conn->prepare("UPDATE promo SET kode_promo=?,nama_promo=?,tipe_diskon=?,nilai_diskon=?,max_diskon=?,min_transaksi=?,berlaku_dari=?,berlaku_hingga=?,kuota=?,status=? WHERE id=?");
            $stmt->bind_param("sssdddssssi", $kodePromo,$namaPromo,$tipeDiskon,$nilaiDiskon,$maxDiskon,$minTransaksi,$berlakuDari,$berlakuHingga,$kuota,$statusPromo,$editId);
        } else {
            $stmt = $conn->prepare("INSERT INTO promo (kode_promo,nama_promo,tipe_diskon,nilai_diskon,max_diskon,min_transaksi,berlaku_dari,berlaku_hingga,kuota,status) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("sssdddssis", $kodePromo,$namaPromo,$tipeDiskon,$nilaiDiskon,$maxDiskon,$minTransaksi,$berlakuDari,$berlakuHingga,$kuota,$statusPromo);
        }
        $stmt->execute(); $stmt->close();
        $success = $editId > 0 ? "Promo berhasil diperbarui!" : "Promo berhasil ditambahkan!";
    }
}

// Hapus
if (isset($_GET['hapus'])) {
    $hapusId = (int)$_GET['hapus'];
    $stmtDel = $conn->prepare("DELETE FROM promo WHERE id=?");
    $stmtDel->bind_param("i", $hapusId);
    $stmtDel->execute();
    $stmtDel->close();
    $success = "Promo berhasil dihapus.";
}

// Edit
$editData = null;
if (isset($_GET['edit'])) {
    $stmtE = $conn->prepare("SELECT * FROM promo WHERE id=?");
    $editId = (int)$_GET['edit'];
    $stmtE->bind_param("i", $editId); $stmtE->execute();
    $editData = $stmtE->get_result()->fetch_assoc(); $stmtE->close();
}

$promoList = $conn->query("SELECT * FROM promo ORDER BY created_at DESC");
$conn->close();
include '../includes/header.php';
$today = date('Y-m-d');
?>

<?php if ($success): ?><div class="alert alert-success alert-auto-hide"><i class="bi bi-check-circle-fill me-1"></i><?= $success ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?php foreach($errors as $e): ?><div><i class="bi bi-x-circle-fill me-1"></i><?= $e ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="row g-3">
    <!-- Form -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-ticket-perforated me-1 text-success"></i> <?= $editData ? 'Edit Promo' : 'Buat Promo Baru' ?>
            </div>
            <div class="card-body">
                <form method="POST" id="formPromo">
                    <?php if ($editData): ?><input type="hidden" name="edit_id" value="<?= $editData['id'] ?>"><?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kode Promo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase fw-bold" name="kode_promo"
                               value="<?= $editData ? clean($editData['kode_promo']) : '' ?>"
                               placeholder="Contoh: HEMAT10" maxlength="20"
                               oninput="this.value=this.value.toUpperCase()" required>
                        <div class="form-text">Huruf kapital, angka, underscore</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Promo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_promo"
                               value="<?= $editData ? clean($editData['nama_promo']) : '' ?>"
                               placeholder="Deskripsi singkat promo" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tipe Diskon <span class="text-danger">*</span></label>
                        <select class="form-select" name="tipe_diskon" id="tipeDiskon" onchange="toggleMaxDiskon()">
                            <option value="persen" <?= ($editData && $editData['tipe_diskon']==='persen') || !$editData ? 'selected':'' ?>>Persentase (%)</option>
                            <option value="nominal" <?= ($editData && $editData['tipe_diskon']==='nominal') ? 'selected':'' ?>>Nominal (Rp)</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-semibold">Nilai Diskon <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="nilai_diskon"
                                   value="<?= $editData ? $editData['nilai_diskon'] : '' ?>"
                                   placeholder="10" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-6 mb-3" id="wrapMaxDiskon">
                            <label class="form-label small fw-semibold">Maks. Diskon (Rp)</label>
                            <input type="number" class="form-control" name="max_diskon"
                                   value="<?= $editData ? $editData['max_diskon'] : '' ?>"
                                   placeholder="Kosong = tak terbatas" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Min. Transaksi (Rp)</label>
                        <input type="number" class="form-control" name="min_transaksi"
                               value="<?= $editData ? $editData['min_transaksi'] : '0' ?>" min="0">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-semibold">Berlaku Dari <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="berlaku_dari"
                                   value="<?= $editData ? $editData['berlaku_dari'] : $today ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-semibold">Berlaku Hingga <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="berlaku_hingga"
                                   value="<?= $editData ? $editData['berlaku_hingga'] : '' ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-semibold">Kuota</label>
                            <input type="number" class="form-control" name="kuota"
                                   value="<?= $editData ? $editData['kuota'] : '' ?>"
                                   placeholder="Kosong = bebas" min="1">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-semibold">Status</label>
                            <select class="form-select" name="status">
                                <option value="aktif"    <?= ($editData && $editData['status']==='aktif')    || !$editData ? 'selected':'' ?>>Aktif</option>
                                <option value="nonaktif" <?= ($editData && $editData['status']==='nonaktif') ? 'selected':'' ?>>Non-aktif</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-save me-1"></i> <?= $editData ? 'Simpan Perubahan' : 'Buat Promo' ?>
                    </button>
                    <?php if ($editData): ?><a href="promo.php" class="btn btn-outline-secondary w-100 mt-2">Batal</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel Promo -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-ticket-perforated me-1"></i> Daftar Kode Promo
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Kode</th><th>Nama</th><th>Diskon</th><th>Min. Trx</th><th>Berlaku</th><th>Kuota</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php if ($promoList->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Belum ada promo</td></tr>
                    <?php else: while($p=$promoList->fetch_assoc()):
                        $expired   = $p['berlaku_hingga'] < $today;
                        $habis     = $p['kuota'] && $p['terpakai'] >= $p['kuota'];
                    ?>
                        <tr class="<?= $expired || $habis ? 'table-secondary' : '' ?>">
                            <td>
                                <span class="badge bg-dark fs-6 font-monospace"><?= clean($p['kode_promo']) ?></span>
                            </td>
                            <td>
                                <?= clean($p['nama_promo']) ?>
                                <?php if ($expired): ?><span class="badge bg-danger ms-1">Kadaluarsa</span><?php endif; ?>
                                <?php if ($habis): ?><span class="badge bg-warning text-dark ms-1">Habis</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['tipe_diskon']==='persen'): ?>
                                    <?= $p['nilai_diskon'] ?>%
                                    <?php if ($p['max_diskon']): ?>
                                    <div class="text-muted small">maks <?= formatRupiah($p['max_diskon']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= formatRupiah($p['nilai_diskon']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $p['min_transaksi'] > 0 ? formatRupiah($p['min_transaksi']) : '-' ?></td>
                            <td>
                                <small><?= date('d/m/Y',strtotime($p['berlaku_dari'])) ?></small><br>
                                <small class="text-muted">s/d <?= date('d/m/Y',strtotime($p['berlaku_hingga'])) ?></small>
                            </td>
                            <td>
                                <?php if ($p['kuota']): ?>
                                    <div class="progress" style="height:6px;width:60px">
                                        <div class="progress-bar bg-success" style="width:<?= min(100, ($p['terpakai']/$p['kuota'])*100) ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?= $p['terpakai'] ?>/<?= $p['kuota'] ?></small>
                                <?php else: ?>
                                    <span class="text-muted small">Bebas</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $p['status']==='aktif' ? 'bg-success':'bg-secondary' ?>"><?= ucfirst($p['status']) ?></span></td>
                            <td>
                                <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <a href="?hapus=<?= $p['id'] ?>" class="btn btn-sm btn-danger"
                                   onclick="return confirm('Hapus promo <?= clean($p['kode_promo']) ?>?')"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleMaxDiskon() {
    const tipe = document.getElementById('tipeDiskon').value;
    document.getElementById('wrapMaxDiskon').style.display = tipe === 'persen' ? '' : 'none';
}
toggleMaxDiskon();
</script>

<?php include '../includes/footer.php'; ?>
