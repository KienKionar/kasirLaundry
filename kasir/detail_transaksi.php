<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireKasir();

$pageTitle  = 'Detail Transaksi';
$activeMenu = 'daftar-transaksi';
$conn       = getConnection();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: " . BASE_URL . "/kasir/daftar_transaksi.php"); exit(); }

$success = '';
$error   = '';

// Proses update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = clean($_POST['status'] ?? '');
    $allowed   = ['belum_diproses','diproses','selesai','diambil'];

    if (!in_array($newStatus, $allowed)) {
        $error = "Status tidak valid.";
    } else {
        $stmtU = $conn->prepare("UPDATE transaksi SET status=? WHERE id=?");
        $stmtU->bind_param("si", $newStatus, $id);
        $stmtU->execute(); $stmtU->close();
        $success = "Status berhasil diperbarui!";
    }
}

// Ambil data transaksi lengkap
$stmt = $conn->prepare("
    SELECT t.*,
           p.nama AS nama_pelanggan, p.no_hp, p.alamat,
           l.nama_layanan, l.harga_per_kg,
           u.nama AS nama_kasir,
           o.nama_outlet, o.alamat AS alamat_outlet,
           pr.kode_promo, pr.nama_promo, pr.tipe_diskon, pr.nilai_diskon
    FROM transaksi t
    JOIN pelanggan p ON t.pelanggan_id=p.id
    JOIN layanan l   ON t.layanan_id=l.id
    JOIN users u     ON t.kasir_id=u.id
    LEFT JOIN outlet o ON t.outlet_id=o.id
    LEFT JOIN promo pr ON t.promo_id=pr.id
    WHERE t.id=?
");
$stmt->bind_param("i", $id); $stmt->execute();
$trx = $stmt->get_result()->fetch_assoc(); $stmt->close();
$conn->close();

if (!$trx) die("Transaksi tidak ditemukan.");

include '../includes/header.php';
?>

<?php if($success): ?>
<div class="alert alert-success alert-auto-hide d-flex align-items-center gap-2">
    <i class="bi bi-check-circle-fill"></i><?=$success?>
</div>
<?php endif; ?>
<?php if($error): ?>
<div class="alert alert-danger d-flex align-items-center gap-2">
    <i class="bi bi-x-circle-fill"></i><?=$error?>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Kolom kiri: Detail -->
    <div class="col-md-7">
        <!-- Info Transaksi -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-receipt me-1"></i> Detail Transaksi
                <code class="ms-2"><?=clean($trx['kode_transaksi'])?></code>
                <?php
                $badges=['belum_diproses'=>['badge-belum','Belum Diproses'],'diproses'=>['bg-primary','Diproses'],'selesai'=>['bg-success','Selesai'],'diambil'=>['bg-secondary','Diambil']];
                $b=$badges[$trx['status']]??['bg-secondary',$trx['status']];
                ?>
                <span class="badge <?=$b[0]?> ms-2"><?=$b[1]?></span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="text-muted small fw-semibold border-bottom pb-1 mb-2">INFO PELANGGAN</h6>
                        <table class="table table-borderless table-sm mb-0">
                            <tr><td class="text-muted pe-3">Nama</td><td class="fw-semibold"><?=clean($trx['nama_pelanggan'])?></td></tr>
                            <tr><td class="text-muted">No. HP</td><td><?=clean($trx['no_hp'])?></td></tr>
                            <tr><td class="text-muted">Alamat</td><td><?=clean($trx['alamat']) ?: '-'?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted small fw-semibold border-bottom pb-1 mb-2">INFO OUTLET</h6>
                        <table class="table table-borderless table-sm mb-0">
                            <tr><td class="text-muted pe-3">Outlet</td><td><?=clean($trx['nama_outlet'] ?? '-')?></td></tr>
                            <tr><td class="text-muted">Kasir</td><td><?=clean($trx['nama_kasir'])?></td></tr>
                            <tr><td class="text-muted">Tgl Masuk</td><td><?=date('d/m/Y H:i',strtotime($trx['tanggal_masuk']))?></td></tr>
                            <tr><td class="text-muted">Est. Selesai</td><td><?=date('d/m/Y',strtotime($trx['tanggal_selesai']))?></td></tr>
                        </table>
                    </div>
                </div>

                <hr>

                <h6 class="text-muted small fw-semibold border-bottom pb-1 mb-2">DETAIL CUCIAN & PEMBAYARAN</h6>
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted pe-3">Layanan</td><td><?=clean($trx['nama_layanan'])?></td></tr>
                    <tr><td class="text-muted">Berat</td><td><?=$trx['berat_kg']?> kg × <?=formatRupiah($trx['harga_per_kg'])?>/kg</td></tr>
                    <tr><td class="text-muted">Subtotal</td><td><?=formatRupiah($trx['subtotal'] ?? $trx['total_harga'])?></td></tr>
                    <?php if ($trx['diskon'] > 0): ?>
                    <tr class="text-success">
                        <td>Diskon
                            <?php if ($trx['kode_promo']): ?>
                            <code class="small">(<?=clean($trx['kode_promo'])?>)</code>
                            <?php endif; ?>
                        </td>
                        <td>- <?=formatRupiah($trx['diskon'])?></td>
                    </tr>
                    <?php endif; ?>
                    <tr><td class="text-muted fw-semibold">Total Bayar</td><td class="fw-bold text-primary fs-5"><?=formatRupiah($trx['total_harga'])?></td></tr>
                    <tr><td class="text-muted">Bayar</td><td><?=formatRupiah($trx['bayar'])?></td></tr>
                    <tr><td class="text-muted">Kembalian</td><td class="fw-bold text-success"><?=formatRupiah($trx['kembalian'])?></td></tr>
                    <?php if ($trx['catatan']): ?>
                    <tr><td class="text-muted">Catatan</td><td><?=clean($trx['catatan'])?></td></tr>
                    <?php endif; ?>
                </table>

                <?php if ($trx['kode_promo']): ?>
                <div class="alert alert-success py-2 mt-2 mb-0 small">
                    <i class="bi bi-ticket-perforated-fill me-1"></i>
                    Promo <strong><?=clean($trx['kode_promo'])?></strong> — <?=clean($trx['nama_promo'])?>
                    &nbsp;·&nbsp; Hemat <?=formatRupiah($trx['diskon'])?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kolom kanan: Aksi -->
    <div class="col-md-5">
        <!-- Update Status -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-arrow-repeat me-1"></i> Update Status
            </div>
            <div class="card-body">
                <?php
                $statusInfo = [
                    'belum_diproses' => ['bg-warning text-dark','Belum Diproses','bi-hourglass'],
                    'diproses'       => ['bg-primary text-white','Diproses','bi-gear-wide-connected'],
                    'selesai'        => ['bg-success text-white','Selesai','bi-check-circle'],
                    'diambil'        => ['bg-secondary text-white','Diambil','bi-bag-check'],
                ];
                $si = $statusInfo[$trx['status']] ?? ['bg-secondary text-white',$trx['status'],'bi-question'];
                ?>
                <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded" style="background:#f8f9fa">
                    <i class="bi <?=$si[2]?> fs-5"></i>
                    <span class="badge <?=$si[0]?> fs-6"><?=$si[1]?></span>
                    <span class="text-muted small">Status saat ini</span>
                </div>

                <!-- Alur Status Visual -->
                <div class="d-flex align-items-center gap-1 mb-3 small overflow-auto pb-1">
                    <?php $steps = ['belum_diproses'=>'Belum Diproses','diproses'=>'Diproses','selesai'=>'Selesai','diambil'=>'Diambil'];
                    $statusOrder = array_keys($steps);
                    $currentIdx = array_search($trx['status'], $statusOrder);
                    foreach($steps as $key=>$label):
                        $idx = array_search($key, $statusOrder);
                        $isCurrent = ($key === $trx['status']);
                        $isDone    = ($idx < $currentIdx);
                    ?>
                    <div class="text-center" style="min-width:70px">
                        <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center fw-bold"
                             style="width:28px;height:28px;font-size:.75rem;
                             background:<?=$isDone?'#198754':($isCurrent?'#0d6efd':'#dee2e6')?>;
                             color:<?=$isDone||$isCurrent?'#fff':'#6c757d'?>">
                            <?=$isDone?'✓':($idx+1)?>
                        </div>
                        <div class="mt-1" style="font-size:.68rem;color:<?=$isCurrent?'#0d6efd':($isDone?'#198754':'#6c757d')?>;font-weight:<?=$isCurrent?'700':'400'?>">
                            <?=$label?>
                        </div>
                    </div>
                    <?php if($idx < count($steps)-1): ?>
                    <div style="flex:1;height:2px;background:<?=$isDone?'#198754':'#dee2e6'?>;min-width:10px;margin-bottom:16px"></div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Ubah Status ke</label>
                        <select class="form-select" name="status">
                            <?php foreach($steps as $key=>$label): ?>
                            <option value="<?=$key?>" <?=$trx['status']===$key?'selected':''?>><?=$label?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="update_status" class="btn btn-primary w-100">
                        <i class="bi bi-check-circle me-1"></i> Simpan Status
                    </button>
                </form>

                <!-- Shortcut: tandai diambil + scan -->
                <?php if ($trx['status'] !== 'diambil'): ?>
                <div class="alert alert-info small mt-2 mb-0 py-2">
                    <i class="bi bi-upc-scan me-1"></i>
                    Gunakan <a href="<?=BASE_URL?>/kasir/scan_barcode.php" class="fw-semibold">Scan Barcode</a>
                    untuk konfirmasi pengambilan lebih cepat.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tombol Aksi -->
        <div class="d-grid gap-2">
            <a href="<?=BASE_URL?>/kasir/struk.php?id=<?=$trx['id']?>" target="_blank" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Cetak Struk & Barcode
            </a>
            <a href="daftar_transaksi.php" class="btn btn-outline-dark">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
            </a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
