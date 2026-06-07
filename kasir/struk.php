<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireKasir();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: " . BASE_URL . "/kasir/daftar_transaksi.php"); exit(); }

$conn = getConnection();
$stmt = $conn->prepare("
    SELECT t.*, p.nama AS nama_pelanggan, p.no_hp, p.alamat,
           l.nama_layanan, l.harga_per_kg, l.estimasi_hari,
           u.nama AS nama_kasir, o.nama_outlet, o.alamat AS alamat_outlet, o.no_telp AS telp_outlet,
           pr.kode_promo, pr.nama_promo
    FROM transaksi t
    JOIN pelanggan p ON t.pelanggan_id = p.id
    JOIN layanan l   ON t.layanan_id   = l.id
    JOIN users u     ON t.kasir_id     = u.id
    LEFT JOIN outlet o  ON t.outlet_id  = o.id
    LEFT JOIN promo pr  ON t.promo_id   = pr.id
    WHERE t.id = ?
");
$stmt->bind_param("i", $id); $stmt->execute();
$trx = $stmt->get_result()->fetch_assoc(); $stmt->close(); $conn->close();
if (!$trx) die("Transaksi tidak ditemukan.");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk - <?= clean($trx['kode_transaksi']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- JsBarcode untuk generate barcode -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        body { background:#f0f2f5; font-family:'Segoe UI',sans-serif; }
        .struk { max-width:380px; margin:30px auto; background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,.1); overflow:hidden; }
        .struk-header { background:#1a2d4e; color:#fff; text-align:center; padding:20px; }
        .struk-body { padding:20px; }
        .struk-row { display:flex; justify-content:space-between; margin-bottom:8px; font-size:.88rem; }
        .struk-divider { border-top:1px dashed #ccc; margin:12px 0; }
        .struk-total { font-size:1.1rem; font-weight:700; color:#1a2d4e; }
        .barcode-area { text-align:center; background:#f9f9f9; padding:14px 10px 8px; border-radius:8px; margin-top:14px; }
        @media print {
            body { background:#fff; }
            .no-print { display:none !important; }
            .struk { box-shadow:none; margin:0; border-radius:0; }
        }
    </style>
</head>
<body>

<div class="text-center mt-3 no-print d-flex justify-content-center gap-2">
    <button onclick="window.print()" class="btn btn-primary">
        <i class="bi bi-printer me-1"></i> Cetak Struk
    </button>
    <a href="<?= BASE_URL ?>/kasir/scan_barcode.php" class="btn btn-outline-success">
        <i class="bi bi-upc-scan me-1"></i> Scan Barcode
    </a>
    <a href="<?= BASE_URL ?>/kasir/dashboard.php" class="btn btn-outline-secondary">
        <i class="bi bi-house me-1"></i> Dashboard
    </a>
</div>

<div class="struk">
    <div class="struk-header">
        <div style="font-size:1.8rem"><i class="bi bi-basket2-fill"></i></div>
        <h5 class="mb-0 fw-bold"><?= clean($trx['nama_outlet'] ?? 'LaundryKu') ?></h5>
        <?php if ($trx['alamat_outlet']): ?>
        <small class="opacity-75"><?= clean($trx['alamat_outlet']) ?></small><br>
        <?php endif; ?>
        <small class="opacity-75">Sistem Kasir Laundry</small>
    </div>

    <div class="struk-body">
        <div class="text-center mb-3">
            <div class="fw-bold fs-5"><?= clean($trx['kode_transaksi']) ?></div>
            <div class="text-muted small"><?= date('d/m/Y H:i', strtotime($trx['tanggal_masuk'])) ?></div>
        </div>

        <div class="struk-divider"></div>

        <div class="struk-row"><span class="text-muted">Pelanggan</span><span class="fw-semibold"><?= clean($trx['nama_pelanggan']) ?></span></div>
        <div class="struk-row"><span class="text-muted">No. HP</span><span><?= clean($trx['no_hp']) ?></span></div>
        <?php if ($trx['alamat']): ?>
        <div class="struk-row"><span class="text-muted">Alamat</span><span style="max-width:60%;text-align:right"><?= clean($trx['alamat']) ?></span></div>
        <?php endif; ?>

        <div class="struk-divider"></div>

        <div class="struk-row"><span class="text-muted">Layanan</span><span><?= clean($trx['nama_layanan']) ?></span></div>
        <div class="struk-row"><span class="text-muted">Berat</span><span><?= $trx['berat_kg'] ?> kg</span></div>
        <div class="struk-row"><span class="text-muted">Harga/kg</span><span><?= formatRupiah($trx['harga_per_kg']) ?></span></div>
        <div class="struk-row"><span class="text-muted">Subtotal</span><span><?= formatRupiah($trx['subtotal'] ?? $trx['total_harga']) ?></span></div>

        <?php if ($trx['diskon'] > 0): ?>
        <div class="struk-row text-success">
            <span>Diskon (<?= clean($trx['kode_promo']) ?>)</span>
            <span>- <?= formatRupiah($trx['diskon']) ?></span>
        </div>
        <?php endif; ?>

        <div class="struk-row"><span class="text-muted">Est. Selesai</span><span><?= date('d/m/Y', strtotime($trx['tanggal_selesai'])) ?></span></div>
        <?php if ($trx['catatan']): ?><div class="struk-row"><span class="text-muted">Catatan</span><span style="max-width:60%;text-align:right"><?= clean($trx['catatan']) ?></span></div><?php endif; ?>

        <div class="struk-divider"></div>

        <div class="struk-row struk-total"><span>TOTAL</span><span><?= formatRupiah($trx['total_harga']) ?></span></div>
        <div class="struk-row"><span class="text-muted">Bayar</span><span><?= formatRupiah($trx['bayar']) ?></span></div>
        <div class="struk-row fw-bold text-success"><span>Kembalian</span><span><?= formatRupiah($trx['kembalian']) ?></span></div>

        <div class="struk-divider"></div>

        <div class="struk-row"><span class="text-muted">Kasir</span><span><?= clean($trx['nama_kasir']) ?></span></div>

        <!-- BARCODE AREA -->
        <div class="barcode-area">
            <svg id="barcodeEl"></svg>
            <div style="font-size:.7rem;color:#666;margin-top:4px"><?= clean($trx['kode_transaksi']) ?></div>
            <div style="font-size:.68rem;color:#999">Scan untuk konfirmasi pengambilan</div>
        </div>

        <div class="text-center mt-3 small text-muted">
            <i class="bi bi-heart-fill text-danger"></i>
            Terima kasih telah menggunakan layanan kami!<br>
            Tunjukkan struk ini saat pengambilan.
        </div>
    </div>
</div>

<script>
// Generate barcode dari kode transaksi
JsBarcode("#barcodeEl", "<?= clean($trx['kode_transaksi']) ?>", {
    format:      "CODE128",
    width:       1.8,
    height:      55,
    displayValue: false,
    margin:      4,
    background:  "#f9f9f9",
    lineColor:   "#1a2d4e"
});
</script>
</body>
</html>
