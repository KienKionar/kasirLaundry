<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireKasir();

$pageTitle  = 'Transaksi Baru';
$activeMenu = 'transaksi-baru';
$conn       = getConnection();
$errors     = [];

// Ambil layanan aktif
$layananList = $conn->query("SELECT * FROM layanan WHERE status='aktif' ORDER BY nama_layanan");

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pelangganId    = (int)($_POST['pelanggan_id'] ?? 0); // jika pilih existing
    $pelangganNama  = clean($_POST['pelanggan_nama'] ?? '');
    $pelangganHp    = clean($_POST['pelanggan_hp'] ?? '');
    $pelangganAlamat= clean($_POST['pelanggan_alamat'] ?? '');
    $layananId      = (int)($_POST['layanan_id'] ?? 0);
    $beratKg        = (float)($_POST['berat_kg'] ?? 0);
    $bayar          = (float)($_POST['bayar'] ?? 0);
    $catatan        = clean($_POST['catatan'] ?? '');
    $kodePromo      = strtoupper(clean($_POST['kode_promo'] ?? ''));

    // Validasi
    if (empty($pelangganNama)) $errors[] = "Nama pelanggan wajib diisi.";
    elseif (strlen($pelangganNama) < 3) $errors[] = "Nama minimal 3 karakter.";
    if (empty($pelangganHp)) $errors[] = "No. HP wajib diisi.";
    elseif (!preg_match('/^[0-9]{10,15}$/', $pelangganHp)) $errors[] = "No. HP tidak valid (10-15 digit angka).";
    if ($layananId <= 0) $errors[] = "Pilih layanan.";
    if ($beratKg <= 0)  $errors[] = "Berat cucian harus lebih dari 0 kg.";
    elseif ($beratKg > 100) $errors[] = "Berat cucian maksimal 100 kg.";
    if ($bayar <= 0)    $errors[] = "Jumlah bayar wajib diisi.";

    if (empty($errors)) {
        // Ambil harga layanan
        $stmtL = $conn->prepare("SELECT harga_per_kg, estimasi_hari FROM layanan WHERE id=?");
        $stmtL->bind_param("i", $layananId); $stmtL->execute();
        $layanan = $stmtL->get_result()->fetch_assoc(); $stmtL->close();

        if (!$layanan) {
            $errors[] = "Layanan tidak ditemukan.";
        } else {
            $subtotal = $beratKg * $layanan['harga_per_kg'];

            // Cek promo
            $diskon  = 0;
            $promoId = null;
            if (!empty($kodePromo)) {
                $promoResult = hitungDiskon($conn, $kodePromo, $subtotal);
                if ($promoResult['pesan'] !== 'OK' && !empty($promoResult['pesan'])) {
                    $errors[] = $promoResult['pesan'];
                } else {
                    $diskon  = $promoResult['diskon'];
                    $promoId = $promoResult['promo_id'];
                }
            }

            if (empty($errors)) {
                $totalHarga = $subtotal - $diskon;
                if ($bayar < $totalHarga) {
                    $errors[] = "Jumlah bayar kurang. Total: " . formatRupiah($totalHarga);
                } else {
                    $kembalian      = $bayar - $totalHarga;
                    $tanggalSelesai = date('Y-m-d', strtotime("+{$layanan['estimasi_hari']} days"));

                    // Ambil/buat pelanggan
                    if ($pelangganId > 0) {
                        // Pelanggan existing terpilih, update data jika berubah
                        $stmtPU = $conn->prepare("UPDATE pelanggan SET nama=?,no_hp=?,alamat=? WHERE id=?");
                        $stmtPU->bind_param("sssi",$pelangganNama,$pelangganHp,$pelangganAlamat,$pelangganId);
                        $stmtPU->execute(); $stmtPU->close();
                    } else {
                        // Cek duplikat HP
                        $stmtP = $conn->prepare("SELECT id FROM pelanggan WHERE no_hp=?");
                        $stmtP->bind_param("s", $pelangganHp); $stmtP->execute();
                        $existing = $stmtP->get_result()->fetch_assoc(); $stmtP->close();
                        if ($existing) {
                            $pelangganId = $existing['id'];
                        } else {
                            $stmtI = $conn->prepare("INSERT INTO pelanggan (nama,no_hp,alamat) VALUES (?,?,?)");
                            $stmtI->bind_param("sss",$pelangganNama,$pelangganHp,$pelangganAlamat);
                            $stmtI->execute(); $pelangganId = $conn->insert_id; $stmtI->close();
                        }
                    }

                    // Generate kode transaksi
                    $kodeOutlet    = $_SESSION['kode_outlet'] ?? 'LDR';
                    $kodeTransaksi = generateKodeTransaksi($kodeOutlet);
                    $outletId      = $_SESSION['outlet_id'] ?: null;
                    $kasirId       = $_SESSION['user_id'];

                    $stmtT = $conn->prepare("
                        INSERT INTO transaksi
                        (kode_transaksi,outlet_id,pelanggan_id,layanan_id,kasir_id,promo_id,
                         berat_kg,subtotal,diskon,total_harga,bayar,kembalian,tanggal_selesai,catatan)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ");
                    $stmtT->bind_param("siiiidddddddss",
                        $kodeTransaksi,$outletId,$pelangganId,$layananId,$kasirId,$promoId,
                        $beratKg,$subtotal,$diskon,$totalHarga,$bayar,$kembalian,$tanggalSelesai,$catatan
                    );

                    if ($stmtT->execute()) {
                        $newId = $conn->insert_id; $stmtT->close();
                        // Tambah hitungan terpakai promo
                        if ($promoId) {
                            $conn->query("UPDATE promo SET terpakai=terpakai+1 WHERE id=$promoId");
                        }
                        $conn->close();
                        header("Location: " . BASE_URL . "/kasir/struk.php?id=$newId");
                        exit();
                    } else {
                        $errors[] = "Gagal menyimpan transaksi. Coba lagi.";
                    }
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-lg-9">
<div class="card border-0 shadow-sm">
<div class="card-header bg-white fw-semibold">
    <i class="bi bi-plus-circle me-1 text-primary"></i> Form Transaksi Baru
</div>
<div class="card-body">

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Terdapat kesalahan:</strong>
    <ul class="mb-0 mt-1"><?php foreach($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" id="formTransaksi">
<!-- Hidden: pelanggan_id jika pilih existing -->
<input type="hidden" name="pelanggan_id" id="pelangganIdHidden" value="0">

<!-- === SECTION: DATA PELANGGAN === -->
<h6 class="text-muted fw-semibold border-bottom pb-2 mb-3">
    <i class="bi bi-person me-1"></i>Data Pelanggan
</h6>

<!-- Recent Customer + Search -->
<div class="mb-3">
    <label class="form-label fw-semibold small">Cari / Pilih Pelanggan</label>
    <div class="position-relative">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" id="searchPelanggan"
                   placeholder="Ketik nama atau no. HP pelanggan... (atau pilih di bawah)">
            <button type="button" class="btn btn-outline-secondary" id="btnClearPelanggan" style="display:none" title="Clear pilihan">
                <i class="bi bi-x"></i>
            </button>
        </div>
        <div id="dropdownPelanggan" class="dropdown-menu w-100 show shadow" style="display:none;max-height:260px;overflow-y:auto;position:absolute;z-index:1050"></div>
    </div>
    <div class="form-text">Ketik untuk mencari, atau pilih dari pelanggan terkini di bawah</div>
</div>

<!-- Recent Customers Panel -->
<div id="recentPanel" class="mb-3">
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="small text-muted fw-semibold"><i class="bi bi-clock-history me-1"></i>Pelanggan Terkini:</span>
    </div>
    <div id="recentCards" class="d-flex flex-wrap gap-2">
        <div class="text-muted small fst-italic">Memuat...</div>
    </div>
</div>

<!-- Form Data Pelanggan -->
<div id="formPelanggan">
    <div class="mb-3">
        <label class="form-label fw-semibold small">Nama Pelanggan <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="pelanggan_nama" id="inputNama"
               value="<?= isset($_POST['pelanggan_nama']) ? clean($_POST['pelanggan_nama']) : '' ?>"
               placeholder="Nama lengkap" required>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label fw-semibold small">No. HP <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="pelanggan_hp" id="inputHp"
                   value="<?= isset($_POST['pelanggan_hp']) ? clean($_POST['pelanggan_hp']) : '' ?>"
                   placeholder="08xxxxxxxxx" maxlength="15" required>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label fw-semibold small">Alamat</label>
            <input type="text" class="form-control" name="pelanggan_alamat" id="inputAlamat"
                   value="<?= isset($_POST['pelanggan_alamat']) ? clean($_POST['pelanggan_alamat']) : '' ?>"
                   placeholder="Opsional">
        </div>
    </div>
</div>

<!-- === SECTION: DETAIL LAUNDRY === -->
<h6 class="text-muted fw-semibold border-bottom pb-2 mb-3 mt-2">
    <i class="bi bi-basket me-1"></i>Detail Laundry
</h6>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label fw-semibold small">Layanan <span class="text-danger">*</span></label>
        <select class="form-select" name="layanan_id" id="layananSelect" required>
            <option value="">-- Pilih Layanan --</option>
            <?php while($l=$layananList->fetch_assoc()): $sel=(isset($_POST['layanan_id'])&&$_POST['layanan_id']==$l['id'])?'selected':''; ?>
            <option value="<?= $l['id'] ?>" data-harga="<?= $l['harga_per_kg'] ?>" <?= $sel ?>>
                <?= clean($l['nama_layanan']) ?> - <?= formatRupiah($l['harga_per_kg']) ?>/kg
            </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label fw-semibold small">Berat (kg) <span class="text-danger">*</span></label>
        <input type="number" class="form-control" name="berat_kg" id="beratKg"
               value="<?= isset($_POST['berat_kg']) ? (float)$_POST['berat_kg'] : '' ?>"
               placeholder="2.5" step="0.1" min="0.1" max="100" required>
    </div>
</div>

<!-- Kalkulasi -->
<div class="bg-light rounded p-3 mb-3" id="kalkulasiBox">
    <div class="row text-center g-2">
        <div class="col-4">
            <div class="small text-muted">Harga/kg</div>
            <div class="fw-bold text-primary" id="infoHarga">Rp 0</div>
        </div>
        <div class="col-4">
            <div class="small text-muted">Subtotal</div>
            <div class="fw-bold" id="infoSubtotal">Rp 0</div>
        </div>
        <div class="col-4">
            <div class="small text-muted">Total Bayar</div>
            <div class="fw-bold text-success fs-5" id="infoTotal">Rp 0</div>
        </div>
    </div>
    <!-- Baris diskon (muncul hanya jika ada promo) -->
    <div id="infoDiskonRow" class="mt-2 pt-2 border-top text-center" style="display:none">
        <span class="text-success small fw-semibold">
            <i class="bi bi-tag-fill me-1"></i>Diskon promo: <span id="infoDiskonNominal">Rp 0</span>
        </span>
    </div>
</div>

<!-- === SECTION: PROMO === -->
<div class="mb-3">
    <label class="form-label fw-semibold small"><i class="bi bi-ticket-perforated me-1 text-success"></i>Kode Promo</label>
    <div class="input-group">
        <input type="text" class="form-control text-uppercase" name="kode_promo" id="inputKodePromo"
               value="<?= isset($_POST['kode_promo']) ? clean($_POST['kode_promo']) : '' ?>"
               placeholder="Kosong jika tidak ada promo" maxlength="20"
               oninput="this.value=this.value.toUpperCase()">
        <button type="button" class="btn btn-outline-success" id="btnCekPromo">
            <i class="bi bi-check-circle me-1"></i>Cek Promo
        </button>
    </div>
    <div id="promoFeedback" class="mt-1 small"></div>
</div>
<input type="hidden" id="diskonHidden" name="diskon_value" value="0">

<!-- Pembayaran -->
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label fw-semibold small">Jumlah Bayar <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="number" class="form-control" name="bayar" id="inputBayar"
                   value="<?= isset($_POST['bayar']) ? (float)$_POST['bayar'] : '' ?>"
                   placeholder="0" min="0" required>
        </div>
        <!-- Tombol nominal cepat -->
        <div class="d-flex gap-1 mt-1 flex-wrap" id="nominalCepat"></div>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label fw-semibold small">Kembalian</label>
        <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="text" class="form-control bg-light fw-bold" id="infoKembalian" value="0" readonly>
        </div>
    </div>
</div>

<div class="mb-4">
    <label class="form-label fw-semibold small">Catatan</label>
    <textarea class="form-control" name="catatan" rows="2" placeholder="Catatan tambahan (opsional)"><?= isset($_POST['catatan']) ? clean($_POST['catatan']) : '' ?></textarea>
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
// =============================================
// KALKULASI HARGA
// =============================================
let diskonAktif = 0;

function hitungTotal() {
    const harga    = parseFloat(document.getElementById('layananSelect').options[document.getElementById('layananSelect').selectedIndex]?.dataset.harga) || 0;
    const berat    = parseFloat(document.getElementById('beratKg').value) || 0;
    const bayar    = parseFloat(document.getElementById('inputBayar').value) || 0;
    const subtotal = harga * berat;
    const total    = subtotal - diskonAktif;
    const kembalian= bayar - total;

    document.getElementById('infoHarga').textContent    = 'Rp ' + harga.toLocaleString('id-ID');
    document.getElementById('infoSubtotal').textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
    document.getElementById('infoTotal').textContent    = 'Rp ' + (total>0?total:0).toLocaleString('id-ID');
    document.getElementById('infoKembalian').value      = kembalian >= 0 ? kembalian.toLocaleString('id-ID') : '0';
    document.getElementById('infoKembalian').classList.toggle('text-danger', kembalian < 0);

    // Nominal cepat (uang pas & lebih)
    if (total > 0) {
        const nominal = document.getElementById('nominalCepat');
        nominal.innerHTML = '';
        [0, 5000, 10000, 20000].forEach(plus => {
            const rounded = Math.ceil((total + plus) / 5000) * 5000;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary';
            btn.textContent = 'Rp ' + rounded.toLocaleString('id-ID');
            btn.onclick = () => { document.getElementById('inputBayar').value = rounded; hitungTotal(); };
            nominal.appendChild(btn);
        });
    }

    return subtotal;
}

document.getElementById('layananSelect').addEventListener('change', hitungTotal);
document.getElementById('beratKg').addEventListener('input', hitungTotal);
document.getElementById('inputBayar').addEventListener('input', hitungTotal);
hitungTotal();

// =============================================
// CEK PROMO
// =============================================
document.getElementById('btnCekPromo').addEventListener('click', function() {
    const kode   = document.getElementById('inputKodePromo').value.trim();
    const subtotal = hitungTotal();
    const fb     = document.getElementById('promoFeedback');

    if (!kode) { fb.innerHTML = '<span class="text-muted">Masukkan kode promo terlebih dahulu.</span>'; return; }
    if (subtotal <= 0) { fb.innerHTML = '<span class="text-danger">Pilih layanan dan isi berat dulu.</span>'; return; }

    fb.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Mengecek...</span>';

    fetch(`<?= BASE_URL ?>/api/cek_promo.php?kode=${kode}&total=${subtotal}`)
        .then(r => r.json())
        .then(data => {
            if (data.diskon > 0) {
                diskonAktif = data.diskon;
                document.getElementById('diskonHidden').value = diskonAktif;
                document.getElementById('infoDiskonRow').style.display = '';
                document.getElementById('infoDiskonNominal').textContent = 'Rp ' + diskonAktif.toLocaleString('id-ID');
                fb.innerHTML = `<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>Promo berhasil! Hemat Rp ${diskonAktif.toLocaleString('id-ID')}</span>`;
                hitungTotal();
            } else {
                diskonAktif = 0;
                document.getElementById('diskonHidden').value = 0;
                document.getElementById('infoDiskonRow').style.display = 'none';
                fb.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>${data.pesan || 'Kode promo tidak valid.'}</span>`;
                hitungTotal();
            }
        })
        .catch(() => { fb.innerHTML = '<span class="text-danger">Gagal mengecek promo.</span>'; });
});

// Reset diskon jika kode promo dikosongkan
document.getElementById('inputKodePromo').addEventListener('input', function() {
    if (!this.value.trim()) {
        diskonAktif = 0;
        document.getElementById('diskonHidden').value = 0;
        document.getElementById('infoDiskonRow').style.display = 'none';
        document.getElementById('promoFeedback').innerHTML = '';
        hitungTotal();
    }
});

// =============================================
// RECENT CUSTOMER & AUTOCOMPLETE
// =============================================
function pilihPelanggan(data) {
    document.getElementById('pelangganIdHidden').value = data.id;
    document.getElementById('inputNama').value         = data.nama;
    document.getElementById('inputHp').value           = data.no_hp;
    document.getElementById('inputAlamat').value       = data.alamat || '';
    document.getElementById('searchPelanggan').value   = data.nama + ' - ' + data.no_hp;
    document.getElementById('dropdownPelanggan').style.display = 'none';
    document.getElementById('btnClearPelanggan').style.display = '';

    // Highlight kartu recent yang terpilih
    document.querySelectorAll('.recent-card').forEach(c => c.classList.remove('border-primary','bg-primary','text-white'));
    const card = document.querySelector(`.recent-card[data-id="${data.id}"]`);
    if (card) { card.classList.add('border-primary','bg-primary','text-white'); }
}

function clearPilihPelanggan() {
    document.getElementById('pelangganIdHidden').value = '0';
    document.getElementById('inputNama').value         = '';
    document.getElementById('inputHp').value           = '';
    document.getElementById('inputAlamat').value       = '';
    document.getElementById('searchPelanggan').value   = '';
    document.getElementById('btnClearPelanggan').style.display = 'none';
    document.querySelectorAll('.recent-card').forEach(c => c.classList.remove('border-primary','bg-primary','text-white'));
}

// Muat recent customers
fetch('<?= BASE_URL ?>/api/cari_pelanggan.php?mode=recent')
    .then(r => r.json())
    .then(data => {
        const container = document.getElementById('recentCards');
        if (!data.length) { container.innerHTML = '<span class="text-muted small fst-italic">Belum ada pelanggan terdaftar</span>'; return; }
        container.innerHTML = '';
        data.forEach(p => {
            const card = document.createElement('div');
            card.className = 'recent-card border rounded-3 px-3 py-2 cursor-pointer small';
            card.dataset.id = p.id;
            card.style.cssText = 'cursor:pointer;transition:.15s;max-width:180px';
            card.innerHTML = `<div class="fw-semibold text-truncate" style="max-width:160px">${p.nama}</div>
                              <div class="text-muted" style="font-size:.78rem">${p.no_hp}</div>
                              <div class="text-muted" style="font-size:.75rem">${p.total_transaksi} transaksi</div>`;
            card.addEventListener('mouseenter', () => { if (!card.classList.contains('bg-primary')) card.classList.add('bg-light'); });
            card.addEventListener('mouseleave', () => card.classList.remove('bg-light'));
            card.addEventListener('click', () => pilihPelanggan(p));
            container.appendChild(card);
        });
    });

// Autocomplete search
let searchTimeout;
const ddEl = document.getElementById('dropdownPelanggan');
document.getElementById('searchPelanggan').addEventListener('input', function() {
    const q = this.value.trim();
    clearTimeout(searchTimeout);
    if (q.length < 2) { ddEl.style.display = 'none'; return; }
    searchTimeout = setTimeout(() => {
        fetch(`<?= BASE_URL ?>/api/cari_pelanggan.php?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(data => {
                if (!data.length) { ddEl.style.display = 'none'; return; }
                ddEl.innerHTML = '';
                data.forEach(p => {
                    const item = document.createElement('a');
                    item.className = 'dropdown-item py-2';
                    item.href = '#';
                    item.innerHTML = `<div class="fw-semibold">${p.nama}</div>
                                      <div class="text-muted small">${p.no_hp} · ${p.total_transaksi} transaksi</div>`;
                    item.addEventListener('click', e => { e.preventDefault(); pilihPelanggan(p); });
                    ddEl.appendChild(item);
                });
                ddEl.style.display = 'block';
            });
    }, 300);
});

document.getElementById('btnClearPelanggan').addEventListener('click', clearPilihPelanggan);

// Tutup dropdown saat klik di luar
document.addEventListener('click', e => {
    if (!document.getElementById('searchPelanggan').contains(e.target)) {
        ddEl.style.display = 'none';
    }
});
</script>

<?php include '../includes/footer.php'; ?>
