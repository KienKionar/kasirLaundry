<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle  = 'Laporan & Statistik';
$activeMenu = 'admin-dashboard';
$conn       = getConnection();

// Filter
$bulan        = (int)($_GET['bulan'] ?? date('m'));
$tahun        = (int)($_GET['tahun'] ?? date('Y'));
$filterOutlet = (int)($_GET['outlet_id'] ?? 0);
if ($bulan < 1 || $bulan > 12) $bulan = date('m');
if ($tahun < 2020 || $tahun > 2030) $tahun = date('Y');
$periode = sprintf('%04d-%02d', $tahun, $bulan);

// Kondisi outlet
$outletWhere = $filterOutlet > 0 ? "AND t.outlet_id = $filterOutlet" : "";

// ---- Statistik utama ----
$q1 = $conn->query("SELECT COUNT(*) AS v FROM transaksi t WHERE DATE_FORMAT(t.tanggal_masuk,'%Y-%m')='$periode' $outletWhere");
$totalTrx = $q1->fetch_assoc()['v'];

$q2 = $conn->query("SELECT IFNULL(SUM(t.total_harga),0) AS v FROM transaksi t WHERE DATE_FORMAT(t.tanggal_masuk,'%Y-%m')='$periode' $outletWhere");
$totalPendapatan = $q2->fetch_assoc()['v'];

$q3 = $conn->query("SELECT IFNULL(SUM(t.diskon),0) AS v FROM transaksi t WHERE DATE_FORMAT(t.tanggal_masuk,'%Y-%m')='$periode' $outletWhere");
$totalDiskon = $q3->fetch_assoc()['v'];

$q4 = $conn->query("SELECT IFNULL(SUM(t.berat_kg),0) AS v FROM transaksi t WHERE DATE_FORMAT(t.tanggal_masuk,'%Y-%m')='$periode' $outletWhere");
$totalKg = $q4->fetch_assoc()['v'];

$q5 = $conn->query("SELECT COUNT(DISTINCT t.pelanggan_id) AS v FROM transaksi t WHERE DATE_FORMAT(t.tanggal_masuk,'%Y-%m')='$periode' $outletWhere");
$totalPelangganAktif = $q5->fetch_assoc()['v'];

$q6 = $conn->query("SELECT COUNT(*) AS v FROM transaksi t WHERE t.status IN ('belum_diproses','diproses','selesai') $outletWhere");
$trxBelumSelesai = $q6->fetch_assoc()['v'];

// ---- Transaksi harian untuk grafik ----
$qHarian = $conn->query("
    SELECT DATE(t.tanggal_masuk) AS tgl,
           COUNT(*) AS jumlah,
           SUM(t.total_harga) AS pendapatan
    FROM transaksi t
    WHERE DATE_FORMAT(t.tanggal_masuk,'%Y-%m')='$periode' $outletWhere
    GROUP BY DATE(t.tanggal_masuk) ORDER BY tgl
");
$labelHari = []; $dataJumlah = []; $dataPendapatan = [];
while ($r = $qHarian->fetch_assoc()) {
    $labelHari[]      = date('d/m', strtotime($r['tgl']));
    $dataJumlah[]     = (int)$r['jumlah'];
    $dataPendapatan[] = (float)$r['pendapatan'];
}

// ---- Performa per outlet ----
$qOutlet = $conn->query("
    SELECT o.nama_outlet, o.kode_outlet,
           COUNT(t.id) AS jumlah,
           IFNULL(SUM(t.total_harga),0) AS pendapatan
    FROM outlet o
    LEFT JOIN transaksi t ON t.outlet_id=o.id
        AND DATE_FORMAT(t.tanggal_masuk,'%Y-%m')='$periode'
    GROUP BY o.id ORDER BY pendapatan DESC
");

// ---- Performa per kasir ----
$qKasir = $conn->query("
    SELECT u.nama, o.nama_outlet,
           COUNT(t.id) AS jumlah,
           IFNULL(SUM(t.total_harga),0) AS pendapatan
    FROM transaksi t
    JOIN users u ON t.kasir_id=u.id
    LEFT JOIN outlet o ON t.outlet_id=o.id
    WHERE DATE_FORMAT(t.tanggal_masuk,'%Y-%m')='$periode' $outletWhere
    GROUP BY t.kasir_id ORDER BY pendapatan DESC
    LIMIT 10
");

// ---- Layanan terlaris ----
$qLayanan = $conn->query("
    SELECT l.nama_layanan,
           COUNT(t.id) AS jumlah,
           IFNULL(SUM(t.total_harga),0) AS pendapatan
    FROM transaksi t
    JOIN layanan l ON t.layanan_id=l.id
    WHERE DATE_FORMAT(t.tanggal_masuk,'%Y-%m')='$periode' $outletWhere
    GROUP BY t.layanan_id ORDER BY jumlah DESC
");

// ---- Promo terpakai ----
$qPromo = $conn->query("
    SELECT pr.kode_promo, pr.nama_promo,
           COUNT(t.id) AS dipakai,
           IFNULL(SUM(t.diskon),0) AS total_diskon
    FROM transaksi t
    JOIN promo pr ON t.promo_id=pr.id
    WHERE DATE_FORMAT(t.tanggal_masuk,'%Y-%m')='$periode' $outletWhere
    GROUP BY t.promo_id ORDER BY dipakai DESC
    LIMIT 5
");

// ---- Transaksi terbaru ----
$qTerbaru = $conn->query("
    SELECT t.*, p.nama AS nama_pelanggan,
           l.nama_layanan, u.nama AS nama_kasir, o.nama_outlet
    FROM transaksi t
    JOIN pelanggan p ON t.pelanggan_id=p.id
    JOIN layanan l   ON t.layanan_id=l.id
    JOIN users u     ON t.kasir_id=u.id
    LEFT JOIN outlet o ON t.outlet_id=o.id
    WHERE DATE_FORMAT(t.tanggal_masuk,'%Y-%m')='$periode' $outletWhere
    ORDER BY t.tanggal_masuk DESC LIMIT 15
");

// Daftar outlet untuk filter
$allOutlet = $conn->query("SELECT * FROM outlet ORDER BY nama_outlet");
$conn->close();

$namaBulan = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
              7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

include '../includes/header.php';
?>

<!-- Filter Periode + Outlet -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto"><label class="col-form-label small fw-semibold">Periode:</label></div>
            <div class="col-auto">
                <select class="form-select form-select-sm" name="bulan">
                    <?php for($i=1;$i<=12;$i++): ?>
                    <option value="<?=$i?>" <?=$i==$bulan?'selected':''?>><?=$namaBulan[$i]?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" name="tahun">
                    <?php for($y=date('Y');$y>=2023;$y--): ?>
                    <option value="<?=$y?>" <?=$y==$tahun?'selected':''?>><?=$y?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto"><label class="col-form-label small fw-semibold">Outlet:</label></div>
            <div class="col-auto">
                <select class="form-select form-select-sm" name="outlet_id">
                    <option value="0">Semua Outlet</option>
                    <?php $allOutlet->data_seek(0); while($o=$allOutlet->fetch_assoc()): ?>
                    <option value="<?=$o['id']?>" <?=$filterOutlet==$o['id']?'selected':''?>><?=clean($o['nama_outlet'])?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Tampilkan</button></div>
            <div class="col-auto ms-auto">
                <span class="badge bg-secondary"><?=$namaBulan[$bulan]?> <?=$tahun?></span>
                <?php if($filterOutlet): ?>
                <span class="badge bg-primary ms-1">
                    <?php $allOutlet->data_seek(0); while($o=$allOutlet->fetch_assoc()): if($o['id']==$filterOutlet): echo clean($o['nama_outlet']); break; endif; endwhile; ?>
                </span>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-2">
        <div class="card stat-card bg-primary text-white h-100">
            <div class="card-body text-center py-3">
                <div class="fs-2"><i class="bi bi-receipt-cutoff"></i></div>
                <div class="fs-4 fw-bold"><?=$totalTrx?></div>
                <div class="small opacity-75">Transaksi</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card stat-card bg-success text-white h-100">
            <div class="card-body text-center py-3">
                <div class="fs-2"><i class="bi bi-cash-stack"></i></div>
                <div class="fw-bold" style="font-size:.95rem"><?=formatRupiah($totalPendapatan)?></div>
                <div class="small opacity-75">Pendapatan</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card stat-card bg-warning text-dark h-100">
            <div class="card-body text-center py-3">
                <div class="fs-2"><i class="bi bi-basket2"></i></div>
                <div class="fs-4 fw-bold"><?=number_format($totalKg,1)?> kg</div>
                <div class="small opacity-75">Total Cucian</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card stat-card bg-info text-white h-100">
            <div class="card-body text-center py-3">
                <div class="fs-2"><i class="bi bi-people"></i></div>
                <div class="fs-4 fw-bold"><?=$totalPelangganAktif?></div>
                <div class="small opacity-75">Pelanggan Aktif</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card stat-card bg-danger text-white h-100">
            <div class="card-body text-center py-3">
                <div class="fs-2"><i class="bi bi-ticket-perforated"></i></div>
                <div class="fw-bold" style="font-size:.95rem"><?=formatRupiah($totalDiskon)?></div>
                <div class="small opacity-75">Total Diskon</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card stat-card bg-dark text-white h-100">
            <div class="card-body text-center py-3">
                <div class="fs-2"><i class="bi bi-hourglass-split"></i></div>
                <div class="fs-4 fw-bold"><?=$trxBelumSelesai?></div>
                <div class="small opacity-75">Belum Selesai</div>
            </div>
        </div>
    </div>
</div>

<!-- Grafik Harian -->
<?php if (!empty($labelHari)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-graph-up me-1 text-primary"></i> Grafik Transaksi Harian — <?=$namaBulan[$bulan]?> <?=$tahun?>
    </div>
    <div class="card-body">
        <canvas id="grafikHarian" style="max-height:250px"></canvas>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <!-- Performa Outlet -->
    <div class="col-md-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-shop me-1"></i> Performa Per Outlet</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>Outlet</th><th>Trx</th><th>Pendapatan</th></tr></thead>
                    <tbody>
                    <?php $allOutlet->data_seek(0); $qOutlet->data_seek(0);
                    if($qOutlet->num_rows===0): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">Belum ada data</td></tr>
                    <?php else: while($o=$qOutlet->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="badge bg-dark me-1"><?=clean($o['kode_outlet'])?></span>
                                <?=clean($o['nama_outlet'])?>
                            </td>
                            <td><span class="badge bg-primary"><?=$o['jumlah']?></span></td>
                            <td class="small"><?=formatRupiah($o['pendapatan'])?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Layanan Terlaris -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-star me-1"></i> Layanan Terlaris</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>Layanan</th><th>Trx</th><th>Omzet</th></tr></thead>
                    <tbody>
                    <?php if($qLayanan->num_rows===0): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">Belum ada data</td></tr>
                    <?php else: while($l=$qLayanan->fetch_assoc()): ?>
                        <tr>
                            <td><?=clean($l['nama_layanan'])?></td>
                            <td><span class="badge bg-success"><?=$l['jumlah']?></span></td>
                            <td class="small"><?=formatRupiah($l['pendapatan'])?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Promo Terpakai -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-ticket-perforated me-1 text-success"></i> Promo Aktif</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>Kode</th><th>Dipakai</th></tr></thead>
                    <tbody>
                    <?php if($qPromo->num_rows===0): ?>
                        <tr><td colspan="2" class="text-center text-muted py-3">-</td></tr>
                    <?php else: while($p=$qPromo->fetch_assoc()): ?>
                        <tr>
                            <td><code class="small"><?=clean($p['kode_promo'])?></code></td>
                            <td><span class="badge bg-warning text-dark"><?=$p['dipakai']?>×</span></td>
                        </tr>
                    <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Performa Kasir -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-1"></i> Performa Kasir</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Nama Kasir</th><th>Outlet</th><th>Transaksi</th><th>Pendapatan</th><th>Kontribusi</th></tr></thead>
            <tbody>
            <?php $no=1;
            if($qKasir->num_rows===0): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada data</td></tr>
            <?php else: while($k=$qKasir->fetch_assoc()):
                $pct = $totalPendapatan > 0 ? round(($k['pendapatan']/$totalPendapatan)*100) : 0;
            ?>
                <tr>
                    <td><?=$no++?></td>
                    <td><?=clean($k['nama'])?></td>
                    <td><?=$k['nama_outlet']?'<span class="badge bg-light text-dark border">'.clean($k['nama_outlet']).'</span>':'<span class="text-muted">-</span>'?></td>
                    <td><span class="badge bg-primary"><?=$k['jumlah']?></span></td>
                    <td><?=formatRupiah($k['pendapatan'])?></td>
                    <td style="min-width:120px">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:6px">
                                <div class="progress-bar bg-success" style="width:<?=$pct?>%"></div>
                            </div>
                            <small class="text-muted"><?=$pct?>%</small>
                        </div>
                    </td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Transaksi Terbaru -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-table me-1"></i> Transaksi — <?=$namaBulan[$bulan]?> <?=$tahun?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Kode</th><th>Pelanggan</th><th>Outlet</th><th>Layanan</th><th>Total</th><th>Diskon</th><th>Kasir</th><th>Status</th><th>Tgl</th></tr>
                </thead>
                <tbody>
                <?php
                $badges=['belum_diproses'=>['badge-belum','Belum Diproses'],'diproses'=>['bg-primary text-white','Diproses'],'selesai'=>['bg-success text-white','Selesai'],'diambil'=>['bg-secondary text-white','Diambil']];
                if($qTerbaru->num_rows===0): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada transaksi di periode ini</td></tr>
                <?php else: while($r=$qTerbaru->fetch_assoc()):
                    $b=$badges[$r['status']]??['bg-secondary text-white',$r['status']];
                ?>
                    <tr>
                        <td><code><?=clean($r['kode_transaksi'])?></code></td>
                        <td><?=clean($r['nama_pelanggan'])?></td>
                        <td><span class="badge bg-light text-dark border"><?=clean($r['nama_outlet']??'-')?></span></td>
                        <td><?=clean($r['nama_layanan'])?></td>
                        <td><?=formatRupiah($r['total_harga'])?></td>
                        <td><?=$r['diskon']>0?'<span class="text-success small">-'.formatRupiah($r['diskon']).'</span>':'<span class="text-muted">-</span>'?></td>
                        <td><?=clean($r['nama_kasir'])?></td>
                        <td><span class="badge <?=$b[0]?>"><?=$b[1]?></span></td>
                        <td><?=date('d/m/Y',strtotime($r['tanggal_masuk']))?></td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($labelHari)): ?>
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('grafikHarian').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?=json_encode($labelHari)?>,
        datasets: [
            {
                label: 'Transaksi',
                data: <?=json_encode($dataJumlah)?>,
                backgroundColor: 'rgba(52,152,219,0.7)',
                borderColor: '#2980b9',
                borderWidth: 1,
                yAxisID: 'y'
            },
            {
                label: 'Pendapatan (Rp)',
                data: <?=json_encode($dataPendapatan)?>,
                type: 'line',
                borderColor: '#27ae60',
                backgroundColor: 'rgba(39,174,96,.1)',
                borderWidth: 2,
                pointRadius: 3,
                fill: true,
                yAxisID: 'y1',
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            y:  { type:'linear', position:'left',  beginAtZero:true, title:{display:true,text:'Jumlah Transaksi'} },
            y1: { type:'linear', position:'right', beginAtZero:true, grid:{drawOnChartArea:false},
                  ticks:{ callback: v => 'Rp '+v.toLocaleString('id-ID') } }
        }
    }
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
