<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireKasir();

$pageTitle  = 'Daftar Transaksi';
$activeMenu = 'daftar-transaksi';
$conn       = getConnection();

// Filter
$search       = clean($_GET['search']    ?? '');
$filterStatus = clean($_GET['status']    ?? '');
$filterTgl    = clean($_GET['tgl']       ?? '');
$filterTglEnd = clean($_GET['tgl_end']   ?? '');
$outletId     = getOutletId();

// Kasir hanya lihat outletnya, admin bisa lihat semua
$outletWhere = '';
if (getRole() === 'kasir' && $outletId) {
    $outletWhere = "AND t.outlet_id = $outletId";
}

$where  = "WHERE 1=1 $outletWhere";
$params = [];
$types  = "";

if (!empty($search)) {
    $like = "%$search%";
    $where .= " AND (t.kode_transaksi LIKE ? OR p.nama LIKE ? OR p.no_hp LIKE ?)";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= "sss";
}
if (!empty($filterStatus)) {
    $where .= " AND t.status=?"; $params[]=$filterStatus; $types.="s";
}
if (!empty($filterTgl)) {
    $where .= " AND DATE(t.tanggal_masuk) >= ?"; $params[]=$filterTgl; $types.="s";
}
if (!empty($filterTglEnd)) {
    $where .= " AND DATE(t.tanggal_masuk) <= ?"; $params[]=$filterTglEnd; $types.="s";
}

$sql = "
    SELECT t.*, p.nama AS nama_pelanggan, p.no_hp,
           l.nama_layanan, u.nama AS nama_kasir,
           o.nama_outlet, pr.kode_promo
    FROM transaksi t
    JOIN pelanggan p ON t.pelanggan_id=p.id
    JOIN layanan l   ON t.layanan_id=l.id
    JOIN users u     ON t.kasir_id=u.id
    LEFT JOIN outlet o ON t.outlet_id=o.id
    LEFT JOIN promo pr ON t.promo_id=pr.id
    $where
    ORDER BY t.tanggal_masuk DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Hitung total untuk periode filter
$totalTrx = 0; $totalNominal = 0;
$allRows = [];
while ($r = $result->fetch_assoc()) {
    $allRows[] = $r;
    $totalTrx++;
    $totalNominal += $r['total_harga'];
}

$conn->close();
include '../includes/header.php';
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <!-- Filter bar -->
        <form method="GET" class="row g-2 align-items-end mb-2">
            <div class="col-sm-3">
                <label class="form-label small mb-1">Cari</label>
                <input type="text" class="form-control form-control-sm" name="search"
                       value="<?=clean($search)?>" placeholder="Kode / nama / HP">
            </div>
            <div class="col-sm-2">
                <label class="form-label small mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">Semua</option>
                    <option value="belum_diproses" <?=$filterStatus==='belum_diproses'?'selected':''?>>Belum Diproses</option>
                    <option value="diproses"       <?=$filterStatus==='diproses'?'selected':''?>>Diproses</option>
                    <option value="selesai"        <?=$filterStatus==='selesai'?'selected':''?>>Selesai</option>
                    <option value="diambil"        <?=$filterStatus==='diambil'?'selected':''?>>Diambil</option>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label small mb-1">Dari Tgl</label>
                <input type="date" class="form-control form-control-sm" name="tgl" value="<?=$filterTgl?>">
            </div>
            <div class="col-sm-2">
                <label class="form-label small mb-1">Sampai Tgl</label>
                <input type="date" class="form-control form-control-sm" name="tgl_end" value="<?=$filterTglEnd?>">
            </div>
            <div class="col-sm-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-fill">Filter</button>
                <a href="daftar_transaksi.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
        <!-- Ringkasan hasil filter -->
        <div class="d-flex gap-3 small text-muted border-top pt-2">
            <span><i class="bi bi-receipt me-1"></i><strong><?=$totalTrx?></strong> transaksi</span>
            <span><i class="bi bi-cash me-1"></i>Total: <strong><?=formatRupiah($totalNominal)?></strong></span>
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
                        <th>Subtotal</th>
                        <th>Diskon</th>
                        <th>Total</th>
                        <th>Promo</th>
                        <?php if(getRole()==='admin'): ?><th>Outlet</th><?php endif; ?>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $badges=['belum_diproses'=>['badge-belum','Belum Diproses'],'diproses'=>['bg-primary text-white','Diproses'],'selesai'=>['bg-success text-white','Selesai'],'diambil'=>['bg-secondary text-white','Diambil']];
                if(empty($allRows)): ?>
                    <tr><td colspan="14" class="text-center text-muted py-4">Tidak ada transaksi ditemukan.</td></tr>
                <?php else: $no=1; foreach($allRows as $r):
                    $b=$badges[$r['status']]??['bg-secondary text-white',$r['status']];
                ?>
                    <tr>
                        <td><?=$no++?></td>
                        <td><code><?=clean($r['kode_transaksi'])?></code></td>
                        <td>
                            <div><?=clean($r['nama_pelanggan'])?></div>
                            <div class="text-muted small"><?=clean($r['no_hp'])?></div>
                        </td>
                        <td><?=clean($r['nama_layanan'])?></td>
                        <td><?=$r['berat_kg']?> kg</td>
                        <td><?=formatRupiah($r['subtotal'] ?? $r['total_harga'])?></td>
                        <td><?=$r['diskon']>0?'<span class="text-success small">-'.formatRupiah($r['diskon']).'</span>':'<span class="text-muted">-</span>'?></td>
                        <td class="fw-semibold"><?=formatRupiah($r['total_harga'])?></td>
                        <td><?=$r['kode_promo']?'<code class="small text-success">'.clean($r['kode_promo']).'</code>':'<span class="text-muted">-</span>'?></td>
                        <?php if(getRole()==='admin'): ?>
                        <td><span class="badge bg-light text-dark border"><?=clean($r['nama_outlet']??'-')?></span></td>
                        <?php endif; ?>
                        <td><span class="badge <?=$b[0]?>"><?=$b[1]?></span></td>
                        <td class="small"><?=date('d/m/Y',strtotime($r['tanggal_masuk']))?></td>
                        <td>
                            <a href="<?=BASE_URL?>/kasir/detail_transaksi.php?id=<?=$r['id']?>" class="btn btn-sm btn-outline-primary" title="Detail"><i class="bi bi-eye"></i></a>
                            <a href="<?=BASE_URL?>/kasir/struk.php?id=<?=$r['id']?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Struk"><i class="bi bi-printer"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
