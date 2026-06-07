<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireKasir();

$pageTitle  = 'Scan Barcode';
$activeMenu = 'scan-barcode';
$conn       = getConnection();
$result     = null;
$error      = '';
$success    = '';

// Proses update status dari scan / manual
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode      = clean($_POST['kode_transaksi'] ?? '');
    $newStatus = clean($_POST['new_status'] ?? 'diambil');

    if (empty($kode)) {
        $error = "Kode transaksi tidak boleh kosong.";
    } else {
        $stmt = $conn->prepare("
            SELECT t.*, p.nama AS nama_pelanggan, p.no_hp,
                   l.nama_layanan, u.nama AS nama_kasir
            FROM transaksi t
            JOIN pelanggan p ON t.pelanggan_id = p.id
            JOIN layanan l   ON t.layanan_id   = l.id
            JOIN users u     ON t.kasir_id     = u.id
            WHERE t.kode_transaksi = ?
        ");
        $stmt->bind_param("s", $kode); $stmt->execute();
        $trx = $stmt->get_result()->fetch_assoc(); $stmt->close();

        if (!$trx) {
            $error = "Transaksi dengan kode <strong>$kode</strong> tidak ditemukan.";
        } elseif ($trx['status'] === 'diambil') {
            $error = "Transaksi ini sudah berstatus <strong>Diambil</strong> sebelumnya.";
            $result = $trx;
        } else {
            // Update status ke 'diambil'
            $stmtU = $conn->prepare("UPDATE transaksi SET status='diambil' WHERE kode_transaksi=?");
            $stmtU->bind_param("s", $kode); $stmtU->execute(); $stmtU->close();
            $trx['status'] = 'diambil';
            $result  = $trx;
            $success = "Status berhasil diperbarui ke <strong>Diambil</strong>!";
        }
    }
}

$conn->close();
include '../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-lg-8">

<!-- Panel Scan Kamera -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold d-flex align-items-center justify-content-between">
        <span><i class="bi bi-upc-scan me-1 text-primary"></i> Scan Barcode Pengambilan</span>
        <button class="btn btn-sm btn-outline-primary" id="btnToggleKamera">
            <i class="bi bi-camera-video me-1"></i> Buka Kamera
        </button>
    </div>
    <div class="card-body">

        <!-- Area Kamera -->
        <div id="kameraArea" style="display:none" class="mb-3">
            <div class="position-relative bg-dark rounded overflow-hidden" style="max-width:480px;margin:0 auto">
                <video id="videoEl" autoplay playsinline style="width:100%;display:block;max-height:300px;object-fit:cover"></video>
                <canvas id="canvasEl" style="display:none"></canvas>
                <!-- Overlay garis scan -->
                <div style="position:absolute;top:50%;left:10%;right:10%;height:2px;background:rgba(52,152,219,.8);box-shadow:0 0 10px #3498db;transform:translateY(-50%)"></div>
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);border:2px solid rgba(52,152,219,.7);width:200px;height:80px;border-radius:4px"></div>
            </div>
            <div class="text-center mt-2">
                <small class="text-muted">Arahkan kamera ke barcode pada struk</small>
            </div>
            <div id="scanStatus" class="text-center mt-1 small text-info fw-semibold"></div>
        </div>

        <!-- Form Manual -->
        <form method="POST" id="formScan">
            <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i><?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-x-circle-fill me-1"></i><?= $error ?></div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Kode Transaksi <span class="text-danger">*</span></label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-primary text-white"><i class="bi bi-qr-code"></i></span>
                    <input type="text" class="form-control fw-bold font-monospace" name="kode_transaksi"
                           id="inputKode" autofocus
                           value="<?= isset($_POST['kode_transaksi']) ? clean($_POST['kode_transaksi']) : '' ?>"
                           placeholder="Scan atau ketik kode transaksi..."
                           style="letter-spacing:1px;font-size:1.1rem">
                </div>
                <div class="form-text">Barcode scanner otomatis mengisi input ini. Tekan Enter atau klik tombol di bawah.</div>
            </div>
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check2-circle me-1"></i> Konfirmasi Pengambilan
            </button>
        </form>
    </div>
</div>

<!-- Hasil Scan -->
<?php if ($result): ?>
<div class="card border-0 shadow-sm border-start border-4 <?= $result['status']==='diambil' ? 'border-success' : 'border-warning' ?>">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-receipt me-1"></i> Detail Transaksi
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted w-40">Kode</td><td><code class="fw-bold"><?= clean($result['kode_transaksi']) ?></code></td></tr>
                    <tr><td class="text-muted">Pelanggan</td><td><?= clean($result['nama_pelanggan']) ?></td></tr>
                    <tr><td class="text-muted">No. HP</td><td><?= clean($result['no_hp']) ?></td></tr>
                    <tr><td class="text-muted">Layanan</td><td><?= clean($result['nama_layanan']) ?></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted w-40">Berat</td><td><?= $result['berat_kg'] ?> kg</td></tr>
                    <tr><td class="text-muted">Total</td><td class="fw-bold text-primary"><?= formatRupiah($result['total_harga']) ?></td></tr>
                    <tr><td class="text-muted">Masuk</td><td><?= date('d/m/Y H:i', strtotime($result['tanggal_masuk'])) ?></td></tr>
                    <tr>
                        <td class="text-muted">Status</td>
                        <td>
                            <?php
                            $badges = ['belum_diproses'=>['badge-belum','Belum Diproses'],'diproses'=>['bg-primary','Diproses'],'selesai'=>['bg-success','Selesai'],'diambil'=>['bg-secondary','Diambil']];
                            $b = $badges[$result['status']] ?? ['bg-secondary',$result['status']];
                            ?>
                            <span class="badge <?= $b[0] ?> fs-6"><?= $b[1] ?></span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <a href="<?= BASE_URL ?>/kasir/struk.php?id=<?= $result['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-printer me-1"></i> Cetak Struk
            </a>
            <a href="scan_barcode.php" class="btn btn-primary btn-sm">
                <i class="bi bi-upc-scan me-1"></i> Scan Berikutnya
            </a>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Panduan -->
<div class="card border-0 shadow-sm bg-light">
    <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-info-circle me-1 text-primary"></i>Cara Penggunaan</h6>
        <div class="row g-3">
            <div class="col-md-4 text-center">
                <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:52px;height:52px">
                    <i class="bi bi-upc-scan fs-3 text-primary"></i>
                </div>
                <div class="small fw-semibold">1. Scan Barcode</div>
                <div class="small text-muted">Gunakan kamera atau scanner fisik untuk scan barcode pada struk</div>
            </div>
            <div class="col-md-4 text-center">
                <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:52px;height:52px">
                    <i class="bi bi-search fs-3 text-warning"></i>
                </div>
                <div class="small fw-semibold">2. Verifikasi</div>
                <div class="small text-muted">Sistem akan menampilkan detail transaksi untuk diverifikasi</div>
            </div>
            <div class="col-md-4 text-center">
                <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:52px;height:52px">
                    <i class="bi bi-check-circle fs-3 text-success"></i>
                </div>
                <div class="small fw-semibold">3. Konfirmasi</div>
                <div class="small text-muted">Klik konfirmasi, status otomatis berubah ke "Diambil"</div>
            </div>
        </div>
        <div class="alert alert-info mt-3 mb-0 small">
            <i class="bi bi-lightbulb me-1"></i>
            <strong>Tips:</strong> Jika menggunakan barcode scanner fisik (USB), cukup tempel ke input dan scan — otomatis submit karena scanner mengirim Enter setelah scan.
        </div>
    </div>
</div>
<?php endif; ?>

</div>
</div>

<script>
// Auto-submit jika barcode scanner fisik (kirim Enter)
document.getElementById('inputKode').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('formScan').submit();
    }
});

// =============================================
// SCAN KAMERA dengan ZXing (library barcode)
// =============================================
let stream = null;
let scanning = false;

document.getElementById('btnToggleKamera').addEventListener('click', async function() {
    const area = document.getElementById('kameraArea');
    const video = document.getElementById('videoEl');

    if (stream) {
        // Matikan kamera
        stream.getTracks().forEach(t => t.stop());
        stream = null; scanning = false;
        area.style.display = 'none';
        this.innerHTML = '<i class="bi bi-camera-video me-1"></i> Buka Kamera';
        return;
    }

    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        video.srcObject = stream;
        area.style.display = 'block';
        this.innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Tutup Kamera';
        scanning = true;
        scanFrame();
    } catch(e) {
        alert('Kamera tidak dapat diakses. Pastikan izin kamera diberikan.\n\nGunakan input manual sebagai alternatif.');
    }
});

async function scanFrame() {
    if (!scanning || !stream) return;
    const video  = document.getElementById('videoEl');
    const canvas = document.getElementById('canvasEl');
    const status = document.getElementById('scanStatus');

    if (video.readyState === video.HAVE_ENOUGH_DATA) {
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Gunakan BarcodeDetector API jika tersedia (Chrome 83+)
        if ('BarcodeDetector' in window) {
            try {
                const detector = new BarcodeDetector({ formats: ['code_128','code_39','ean_13','qr_code'] });
                const barcodes  = await detector.detect(canvas);
                if (barcodes.length > 0) {
                    const kode = barcodes[0].rawValue;
                    status.textContent = '✅ Barcode terdeteksi: ' + kode;
                    document.getElementById('inputKode').value = kode;
                    scanning = false;
                    // Auto submit setelah 800ms
                    setTimeout(() => document.getElementById('formScan').submit(), 800);
                    return;
                }
            } catch(e) {}
        } else {
            status.textContent = '📷 Kamera aktif. BarcodeDetector tidak tersedia di browser ini — gunakan scanner fisik atau input manual.';
        }
    }
    if (scanning) requestAnimationFrame(scanFrame);
}
</script>

<?php include '../includes/footer.php'; ?>
