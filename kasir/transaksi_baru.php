<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireKasir();

$pageTitle  = 'Transaksi Baru';
$activeMenu = 'transaksi-baru';
$conn       = getConnection();
$errors     = [];
$success    = '';

// Ambil data layanan aktif
$layananList = $conn->query("SELECT * FROM layanan WHERE status = 'aktif' ORDER BY nama_layanan");

// Proses form tambah transaksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil & sanitasi input
    $pelangganNama  = clean($_POST['pelanggan_nama'] ?? '');
    $pelangganHp    = clean($_POST['pelanggan_hp'] ?? '');
    $pelangganAlamat = clean($_POST['pelanggan_alamat'] ?? '');
    $layananId      = (int)($_POST['layanan_id'] ?? 0);
    $beratKg        = (float)($_POST['berat_kg'] ?? 0);
    $bayar          = (float)($_POST['bayar'] ?? 0);
    $catatan        = clean($_POST['catatan'] ?? '');

    // ---- VALIDASI ----
    if (empty($pelangganNama)) {
        $errors[] = "Nama pelanggan wajib diisi.";
    } elseif (strlen($pelangganNama) < 3) {
        $errors[] = "Nama pelanggan minimal 3 karakter.";
    }

    if (empty($pelangganHp)) {
        $errors[] = "No. HP pelanggan wajib diisi.";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $pelangganHp)) {
        $errors[] = "No. HP tidak valid (10-15 digit angka).";
    }

    if ($layananId <= 0) {
        $errors[] = "Pilih layanan terlebih dahulu.";
    }

    if ($beratKg <= 0) {
        $errors[] = "Berat cucian harus lebih dari 0 kg.";
    } elseif ($beratKg > 100) {
        $errors[] = "Berat cucian maksimal 100 kg.";
    }

    if ($bayar <= 0) {
        $errors[] = "Jumlah bayar wajib diisi.";
    }

    // Proses jika tidak ada error
    if (empty($errors)) {
        // Ambil harga layanan
        $stmtL = $conn->prepare("SELECT harga_per_kg, estimasi_hari FROM layanan WHERE id = ?");
        $stmtL->bind_param("i", $layananId);
        $stmtL->execute();
        $layanan = $stmtL->get_result()->fetch_assoc();
        $stmtL->close();

        if (!$layanan) {
            $errors[] = "Layanan tidak ditemukan.";
        } else {
            $totalHarga = $beratKg * $layanan['harga_per_kg'];

            // Validasi bayar harus cukup
            if ($bayar < $totalHarga) {
                $errors[] = "Jumlah bayar kurang. Total: " . formatRupiah($totalHarga);
            } else {
                $kembalian      = $bayar - $totalHarga;
                $tanggalSelesai = date('Y-m-d', strtotime("+{$layanan['estimasi_hari']} days"));
                $kodeTransaksi  = generateKodeTransaksi();

                // Cek apakah pelanggan sudah ada (berdasarkan no HP)
                $stmtP = $conn->prepare("SELECT id FROM pelanggan WHERE no_hp = ?");
                $stmtP->bind_param("s", $pelangganHp);
                $stmtP->execute();
                $existingPelanggan = $stmtP->get_result()->fetch_assoc();
                $stmtP->close();

                if ($existingPelanggan) {
                    $pelangganId = $existingPelanggan['id'];
                } else {
                    // Insert pelanggan baru
                    $stmtInsP = $conn->prepare("INSERT INTO pelanggan (nama, no_hp, alamat) VALUES (?, ?, ?)");
                    $stmtInsP->bind_param("sss", $pelangganNama, $pelangganHp, $pelangganAlamat);
                    $stmtInsP->execute();
                    $pelangganId = $conn->insert_id;
                    $stmtInsP->close();
                }

                // Insert transaksi
                $kasirId = $_SESSION['user_id'];
                $stmtT = $conn->prepare("
                    INSERT INTO transaksi
                    (kode_transaksi, pelanggan_id, layanan_id, kasir_id, berat_kg, total_harga, bayar, kembalian, tanggal_selesai, catatan)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtT->bind_param("siiiddddss",
                    $kodeTransaksi, $pelangganId, $layananId, $kasirId,
                    $beratKg, $totalHarga, $bayar, $kembalian, $tanggalSelesai, $catatan
                );

                if ($stmtT->execute()) {
                    $newId = $conn->insert_id;
                    $stmtT->close();
                    $conn->close();
                    // Redirect ke halaman struk
                    header("Location: " . BASE_URL . "/kasir/struk.php?id=" . $newId);
                    exit();
                } else {
                    $errors[] = "Gagal menyimpan transaksi. Silakan coba lagi.";
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-plus-circle me-1 text-primary"></i> Form Transaksi Baru
            </div>
            <div class="card-body">

                <!-- Tampilkan error jika ada -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Terdapat kesalahan:</strong>
                        <ul class="mb-0 mt-1">
                            <?php foreach ($errors as $e): ?>
                                <li><?= $e ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" id="formTransaksi">
                    <!-- Data Pelanggan -->
                    <h6 class="text-muted fw-semibold border-bottom pb-2 mb-3">
                        <i class="bi bi-person me-1"></i>Data Pelanggan
                    </h6>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Pelanggan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="pelanggan_nama"
                               value="<?= isset($_POST['pelanggan_nama']) ? clean($_POST['pelanggan_nama']) : '' ?>"
                               placeholder="Masukkan nama pelanggan" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold small">No. HP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="pelanggan_hp"
                                   value="<?= isset($_POST['pelanggan_hp']) ? clean($_POST['pelanggan_hp']) : '' ?>"
                                   placeholder="Contoh: 08123456789" required maxlength="15">
                            <div class="form-text">10-15 digit angka</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold small">Alamat</label>
                            <input type="text" class="form-control" name="pelanggan_alamat"
                                   value="<?= isset($_POST['pelanggan_alamat']) ? clean($_POST['pelanggan_alamat']) : '' ?>"
                                   placeholder="Alamat (opsional)">
                        </div>
                    </div>

                    <!-- Detail Laundry -->
                    <h6 class="text-muted fw-semibold border-bottom pb-2 mb-3 mt-2">
                        <i class="bi bi-basket me-1"></i>Detail Laundry
                    </h6>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold small">Layanan <span class="text-danger">*</span></label>
                            <select class="form-select" name="layanan_id" id="layananSelect" required>
                                <option value="">-- Pilih Layanan --</option>
                                <?php
                                $layananList->data_seek(0);
                                while ($l = $layananList->fetch_assoc()):
                                    $selected = (isset($_POST['layanan_id']) && $_POST['layanan_id'] == $l['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $l['id'] ?>"
                                            data-harga="<?= $l['harga_per_kg'] ?>"
                                            <?= $selected ?>>
                                        <?= clean($l['nama_layanan']) ?> - <?= formatRupiah($l['harga_per_kg']) ?>/kg
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold small">Berat (kg) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="berat_kg" id="beratKg"
                                   value="<?= isset($_POST['berat_kg']) ? (float)$_POST['berat_kg'] : '' ?>"
                                   placeholder="Contoh: 2.5" step="0.1" min="0.1" max="100" required>
                        </div>
                    </div>

                    <!-- Kalkulasi Harga -->
                    <div class="bg-light rounded p-3 mb-3">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="small text-muted">Harga/kg</div>
                                <div class="fw-bold text-primary" id="infoHarga">Rp 0</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Berat</div>
                                <div class="fw-bold" id="infoBerat">0 kg</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Total</div>
                                <div class="fw-bold text-success fs-5" id="infoTotal">Rp 0</div>
                            </div>
                        </div>
                    </div>
                    <!-- Hidden field untuk kalkulasi JS -->
                    <input type="hidden" id="totalHargaHidden" value="0">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold small">Jumlah Bayar <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="bayar" id="inputBayar"
                                       value="<?= isset($_POST['bayar']) ? (float)$_POST['bayar'] : '' ?>"
                                       placeholder="0" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold small">Kembalian</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" class="form-control bg-light fw-bold" id="infoKembalian"
                                       value="0" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold small">Catatan</label>
                        <textarea class="form-control" name="catatan" rows="2"
                                  placeholder="Catatan tambahan (opsional)"><?= isset($_POST['catatan']) ? clean($_POST['catatan']) : '' ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan & Cetak Struk
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
// Kalkulasi otomatis total harga dan kembalian
function hitungTotal() {
    const select  = document.getElementById('layananSelect');
    const berat   = parseFloat(document.getElementById('beratKg').value) || 0;
    const bayar   = parseFloat(document.getElementById('inputBayar').value) || 0;
    const harga   = parseFloat(select.options[select.selectedIndex]?.dataset.harga) || 0;
    const total   = harga * berat;
    const kembalian = bayar - total;

    document.getElementById('infoHarga').textContent  = 'Rp ' + harga.toLocaleString('id-ID');
    document.getElementById('infoBerat').textContent  = berat + ' kg';
    document.getElementById('infoTotal').textContent  = 'Rp ' + total.toLocaleString('id-ID');
    document.getElementById('totalHargaHidden').value = total;

    const kembalianEl = document.getElementById('infoKembalian');
    kembalianEl.value = kembalian >= 0 ? kembalian.toLocaleString('id-ID') : '0';
    kembalianEl.classList.toggle('text-danger', kembalian < 0);
}

document.getElementById('layananSelect').addEventListener('change', hitungTotal);
document.getElementById('beratKg').addEventListener('input', hitungTotal);
document.getElementById('inputBayar').addEventListener('input', hitungTotal);

// Jalankan sekali saat halaman dimuat (untuk kasus validasi gagal)
hitungTotal();
</script>

<?php include '../includes/footer.php'; ?>
