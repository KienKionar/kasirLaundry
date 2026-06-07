<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireKasir();

$pageTitle  = 'Data Pelanggan';
$activeMenu = 'pelanggan';
$conn       = getConnection();
$errors     = [];
$success    = '';

// Tambah / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama   = clean($_POST['nama']   ?? '');
    $no_hp  = clean($_POST['no_hp']  ?? '');
    $alamat = clean($_POST['alamat'] ?? '');
    $editId = (int)($_POST['edit_id'] ?? 0);

    if (empty($nama))  $errors[] = "Nama pelanggan wajib diisi.";
    elseif (strlen($nama)<3) $errors[] = "Nama minimal 3 karakter.";
    if (empty($no_hp)) $errors[] = "No. HP wajib diisi.";
    elseif (!preg_match('/^[0-9]{10,15}$/', $no_hp)) $errors[] = "No. HP tidak valid (10-15 digit angka).";

    if (empty($errors)) {
        $stmtCek = $conn->prepare("SELECT id FROM pelanggan WHERE no_hp=? AND id!=?");
        $stmtCek->bind_param("si",$no_hp,$editId); $stmtCek->execute();
        if ($stmtCek->get_result()->num_rows>0) $errors[]="No. HP sudah terdaftar pelanggan lain!";
        $stmtCek->close();
    }

    if (empty($errors)) {
        if ($editId>0) {
            $stmt=$conn->prepare("UPDATE pelanggan SET nama=?,no_hp=?,alamat=? WHERE id=?");
            $stmt->bind_param("sssi",$nama,$no_hp,$alamat,$editId);
            $stmt->execute(); $stmt->close(); $success="Data pelanggan berhasil diperbarui!";
        } else {
            $stmt=$conn->prepare("INSERT INTO pelanggan (nama,no_hp,alamat) VALUES (?,?,?)");
            $stmt->bind_param("sss",$nama,$no_hp,$alamat);
            $stmt->execute(); $stmt->close(); $success="Pelanggan baru berhasil ditambahkan!";
        }
    }
}

// Hapus
if (isset($_GET['hapus'])) {
    $hapusId=(int)$_GET['hapus'];
    $stmtCek=$conn->prepare("SELECT COUNT(*) AS t FROM transaksi WHERE pelanggan_id=?");
    $stmtCek->bind_param("i",$hapusId); $stmtCek->execute();
    $jml=$stmtCek->get_result()->fetch_assoc()['t']; $stmtCek->close();
    if ($jml>0) { $errors[]="Pelanggan tidak bisa dihapus karena memiliki $jml transaksi."; }
    else {
        $s=$conn->prepare("DELETE FROM pelanggan WHERE id=?");
        $s->bind_param("i",$hapusId); $s->execute(); $s->close();
        $success="Pelanggan berhasil dihapus.";
    }
}

// Edit data
$editData=null;
if (isset($_GET['edit'])) {
    $s=$conn->prepare("SELECT * FROM pelanggan WHERE id=?");
    $s->bind_param("i",(int)$_GET['edit']); $s->execute();
    $editData=$s->get_result()->fetch_assoc(); $s->close();
}

// Lihat riwayat transaksi pelanggan
$viewHistory = (int)($_GET['history'] ?? 0);
$historyData = null; $historyPelanggan = null;
if ($viewHistory > 0) {
    $sP=$conn->prepare("SELECT * FROM pelanggan WHERE id=?");
    $sP->bind_param("i",$viewHistory); $sP->execute();
    $historyPelanggan=$sP->get_result()->fetch_assoc(); $sP->close();

    if ($historyPelanggan) {
        $sH=$conn->prepare("
            SELECT t.*, l.nama_layanan, u.nama AS nama_kasir, o.nama_outlet, pr.kode_promo
            FROM transaksi t
            JOIN layanan l ON t.layanan_id=l.id
            JOIN users u   ON t.kasir_id=u.id
            LEFT JOIN outlet o ON t.outlet_id=o.id
            LEFT JOIN promo pr ON t.promo_id=pr.id
            WHERE t.pelanggan_id=?
            ORDER BY t.tanggal_masuk DESC
        ");
        $sH->bind_param("i",$viewHistory); $sH->execute();
        $historyData=$sH->get_result(); $sH->close();
    }
}

// Daftar pelanggan + statistik
$search = clean($_GET['search'] ?? '');
if (!empty($search)) {
    $like="%$search%";
    $sL=$conn->prepare("
        SELECT p.*,
               COUNT(t.id) AS total_trx,
               IFNULL(SUM(t.total_harga),0) AS total_nilai,
               MAX(t.tanggal_masuk) AS last_visit
        FROM pelanggan p
        LEFT JOIN transaksi t ON t.pelanggan_id=p.id
        WHERE p.nama LIKE ? OR p.no_hp LIKE ?
        GROUP BY p.id ORDER BY p.nama
    ");
    $sL->bind_param("ss",$like,$like);
} else {
    $sL=$conn->prepare("
        SELECT p.*,
               COUNT(t.id) AS total_trx,
               IFNULL(SUM(t.total_harga),0) AS total_nilai,
               MAX(t.tanggal_masuk) AS last_visit
        FROM pelanggan p
        LEFT JOIN transaksi t ON t.pelanggan_id=p.id
        GROUP BY p.id ORDER BY last_visit DESC, p.nama
    ");
}
$sL->execute();
$pelangganList=$sL->get_result(); $sL->close();
$conn->close();

include '../includes/header.php';
?>

<?php if($success): ?><div class="alert alert-success alert-auto-hide"><i class="bi bi-check-circle-fill me-1"></i><?=$success?></div><?php endif; ?>
<?php if(!empty($errors)): ?><div class="alert alert-danger"><?php foreach($errors as $e): ?><div><i class="bi bi-x-circle-fill me-1"></i><?=$e?></div><?php endforeach; ?></div><?php endif; ?>

<!-- Modal Riwayat Transaksi -->
<?php if ($historyPelanggan && $historyData): ?>
<div class="card border-0 shadow-sm mb-3 border-start border-4 border-primary">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            <i class="bi bi-clock-history me-1 text-primary"></i>
            Riwayat Transaksi: <strong><?=clean($historyPelanggan['nama'])?></strong>
            <span class="text-muted small">(<?=clean($historyPelanggan['no_hp'])?>)</span>
        </span>
        <a href="pelanggan.php" class="btn btn-sm btn-outline-secondary">Tutup</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead><tr><th>Kode</th><th>Layanan</th><th>Berat</th><th>Total</th><th>Promo</th><th>Outlet</th><th>Status</th><th>Tgl</th><th></th></tr></thead>
                <tbody>
                <?php $badges=['belum_diproses'=>['badge-belum','Belum'],'diproses'=>['bg-primary text-white','Proses'],'selesai'=>['bg-success text-white','Selesai'],'diambil'=>['bg-secondary text-white','Diambil']];
                $totalAll=0; $cntAll=0;
                while($h=$historyData->fetch_assoc()):
                    $bh=$badges[$h['status']]??['bg-secondary text-white',$h['status']];
                    $totalAll+=$h['total_harga']; $cntAll++;
                ?>
                <tr>
                    <td><code class="small"><?=clean($h['kode_transaksi'])?></code></td>
                    <td><?=clean($h['nama_layanan'])?></td>
                    <td><?=$h['berat_kg']?>kg</td>
                    <td><?=formatRupiah($h['total_harga'])?></td>
                    <td><?=$h['kode_promo']?'<code class="small text-success">'.clean($h['kode_promo']).'</code>':'-'?></td>
                    <td class="small"><?=clean($h['nama_outlet']??'-')?></td>
                    <td><span class="badge <?=$bh[0]?>"><?=$bh[1]?></span></td>
                    <td class="small"><?=date('d/m/Y',strtotime($h['tanggal_masuk']))?></td>
                    <td>
                        <a href="<?=BASE_URL?>/kasir/detail_transaksi.php?id=<?=$h['id']?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <tr class="table-light fw-semibold">
                    <td colspan="3" class="text-end">Total (<?=$cntAll?> transaksi)</td>
                    <td colspan="5"><?=formatRupiah($totalAll)?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Form -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person-plus me-1"></i><?=$editData?'Edit Pelanggan':'Tambah Pelanggan'?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if($editData): ?><input type="hidden" name="edit_id" value="<?=$editData['id']?>"><?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama"
                               value="<?=$editData?clean($editData['nama']):(isset($_POST['nama'])?clean($_POST['nama']):'')?>"
                               placeholder="Nama lengkap" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">No. HP <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="no_hp"
                               value="<?=$editData?clean($editData['no_hp']):(isset($_POST['no_hp'])?clean($_POST['no_hp']):'')?>"
                               placeholder="08xxx" maxlength="15" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alamat</label>
                        <textarea class="form-control" name="alamat" rows="2"
                                  placeholder="Opsional"><?=$editData?clean($editData['alamat']):(isset($_POST['alamat'])?clean($_POST['alamat']):'')?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save me-1"></i><?=$editData?'Simpan Perubahan':'Tambah Pelanggan'?>
                    </button>
                    <?php if($editData): ?><a href="pelanggan.php" class="btn btn-outline-secondary w-100 mt-2">Batal</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel Pelanggan -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold"><i class="bi bi-people me-1"></i> Daftar Pelanggan</span>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" name="search"
                           value="<?=clean($search)?>" placeholder="Cari nama / HP..." style="width:180px">
                    <button type="submit" class="btn btn-sm btn-primary">Cari</button>
                    <?php if($search): ?><a href="pelanggan.php" class="btn btn-sm btn-outline-secondary">Reset</a><?php endif; ?>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>#</th><th>Nama</th><th>No. HP</th><th>Trx</th><th>Total Belanja</th><th>Terakhir</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php $no=1;
                        if($pelangganList->num_rows===0): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pelanggan</td></tr>
                        <?php else: while($p=$pelangganList->fetch_assoc()): ?>
                        <tr class="<?=$viewHistory==$p['id']?'table-primary':''?>">
                            <td><?=$no++?></td>
                            <td>
                                <div class="fw-semibold"><?=clean($p['nama'])?></div>
                                <?php if($p['alamat']): ?><div class="text-muted small"><?=clean($p['alamat'])?></div><?php endif; ?>
                            </td>
                            <td><?=clean($p['no_hp'])?></td>
                            <td><span class="badge bg-primary"><?=$p['total_trx']?></span></td>
                            <td class="small"><?=$p['total_nilai']>0?formatRupiah($p['total_nilai']):'<span class="text-muted">-</span>'?></td>
                            <td class="small text-muted"><?=$p['last_visit']?date('d/m/Y',strtotime($p['last_visit'])):'-'?></td>
                            <td>
                                <?php if($p['total_trx']>0): ?>
                                <a href="?history=<?=$p['id']?>" class="btn btn-sm btn-outline-info" title="Riwayat">
                                    <i class="bi bi-clock-history"></i>
                                </a>
                                <?php endif; ?>
                                <a href="?edit=<?=$p['id']?>" class="btn btn-sm btn-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="?hapus=<?=$p['id']?>" class="btn btn-sm btn-danger" title="Hapus"
                                   onclick="return confirm('Hapus pelanggan <?=clean($p['nama'])?>?')"><i class="bi bi-trash"></i></a>
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
