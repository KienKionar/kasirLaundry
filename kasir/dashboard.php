<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireKasir();

$pageTitle  = 'Dashboard Kasir';
$activeMenu = 'kasir-dashboard';
$conn       = getConnection();
$today      = date('Y-m-d');

// Filter per outlet jika kasir (admin bisa lihat semua)
$outletId   = getOutletId();
$outletWhere = ($outletId && getRole() === 'kasir') ? "AND t.outlet_id = $outletId" : "";

// Statistik hari ini
$q1 = $conn->query("SELECT COUNT(*) AS v FROM transaksi t WHERE DATE(t.tanggal_masuk)='$today' $outletWhere");
$totalHariIni = $q1->fetch_assoc()['v'];

$q2 = $conn->query("SELECT IFNULL(SUM(t.total_harga),0) AS v FROM transaksi t WHERE DATE(t.tanggal_masuk)='$today' $outletWhere");
$pendapatanHariIni = $q2->fetch_assoc()['v'];

$q3 = $conn->query("SELECT COUNT(*) AS v FROM transaksi t WHERE t.status='belum_diproses' $outletWhere");
$pending = $q3->fetch_assoc()['v'];

$q4 = $conn->query("SELECT COUNT(*) AS v FROM transaksi t WHERE t.status='selesai' $outletWhere");
$siapDiambil = $q4->fetch_assoc()['v'];

// Shift kasir hari ini
$shiftHariIni = null;
if ($outletId) {
    $stmtShift = $conn->prepare("
        SELECT s.*, u.nama AS nama_kasir
        FROM shift s JOIN users u ON s.user_id=u.id
        WHERE s.outlet_id=? AND s.tanggal=? AND s.user_id=?
        ORDER BY s.jam_mulai LIMIT 1
    ");
    $stmtShift->bind_param("isi", $outletId, $today, $_SESSION['user_id']);
    $stmtShift->execute();
    $shiftHariIni = $stmtShift->get_result()->fetch_assoc();
    $stmtShift->close();
}

// Transaksi terbaru (10)
$sqlRecent = "
    SELECT t.*, p.nama AS nama_pelanggan, l.nama_layanan
    FROM transaksi t
    JOIN pelanggan p ON t.pelanggan_id=p.id
    JOIN layanan l   ON t.layanan_id=l.id
    WHERE 1=1 $outletWhere
    ORDER BY t.tanggal_masuk DESC LIMIT 10
";
$qRecent = $conn->query($sqlRecent);
$conn->close();

include '../includes/header.php';
?>

<!-- Info Shift (jika ada) -->
<?php if ($shiftHariIni): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-calendar-check fs-5"></i>
    <div>
        <strong>Shift Anda Hari Ini:</strong> <?=clean($shiftHariIni['nama_shift'])?>
        &nbsp;·&nbsp; <?=substr($shiftHariIni['jam_mulai'],0,5)?> – <?=substr($shiftHariIni['jam_selesai'],0,5)?>
        <?php if($shiftHariIni['keterangan']): ?>&nbsp;·&nbsp; <em><?=clean($shiftHariIni['keterangan'])?></em><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="fs-1"><i class="bi bi-receipt"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?=$totalHariIni?></div>
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
                    <div class="fw-bold" style="font-size:1.1rem"><?=formatRupiah($pendapatanHariIni)?></div>
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
                    <div class="fs-4 fw-bold"><?=$pending?></div>
                    <div class="small opacity-75">Perlu Diproses</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="fs-1"><i class="bi bi-bag-check"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?=$siapDiambil?></div>
                    <div class="small opacity-75">Siap Diambil</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tombol Cepat -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <a href="<?=BASE_URL?>/kasir/transaksi_baru.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Transaksi Baru
    </a>
    <a href="<?=BASE_URL?>/kasir/scan_barcode.php" class="btn btn-success">
        <i class="bi bi-upc-scan me-1"></i> Scan Barcode
    </a>
    <a href="<?=BASE_URL?>/kasir/daftar_transaksi.php" class="btn btn-outline-secondary">
        <i class="bi bi-list-ul me-1"></i> Semua Transaksi
    </a>
    <?php if($siapDiambil > 0): ?>
    <a href="<?=BASE_URL?>/kasir/daftar_transaksi.php?status=selesai" class="btn btn-outline-info">
        <i class="bi bi-bag-check me-1"></i> Lihat Siap Diambil
        <span class="badge bg-info ms-1"><?=$siapDiambil?></span>
    </a>
    <?php endif; ?>
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
                    <tr><th>Kode</th><th>Pelanggan</th><th>Layanan</th><th>Berat</th><th>Total</th><th>Status</th><th>Waktu</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php
                $badges=['belum_diproses'=>['badge-belum','Belum Diproses'],'diproses'=>['bg-primary','Diproses'],'selesai'=>['bg-success','Selesai'],'diambil'=>['bg-secondary','Diambil']];
                if($qRecent->num_rows===0): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada transaksi</td></tr>
                <?php else: while($r=$qRecent->fetch_assoc()):
                    $b=$badges[$r['status']]??['bg-secondary',$r['status']];
                ?>
                    <tr>
                        <td><code><?=clean($r['kode_transaksi'])?></code></td>
                        <td><?=clean($r['nama_pelanggan'])?></td>
                        <td><?=clean($r['nama_layanan'])?></td>
                        <td><?=$r['berat_kg']?> kg</td>
                        <td><?=formatRupiah($r['total_harga'])?></td>
                        <td><span class="badge <?=$b[0]?>"><?=$b[1]?></span></td>
                        <td class="small text-muted"><?=date('d/m H:i',strtotime($r['tanggal_masuk']))?></td>
                        <td>
                            <a href="<?=BASE_URL?>/kasir/detail_transaksi.php?id=<?=$r['id']?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            <a href="<?=BASE_URL?>/kasir/struk.php?id=<?=$r['id']?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></a>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
