<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle  = 'Kelola User';
$activeMenu = 'users';
$conn       = getConnection();
$errors     = [];
$success    = '';

$outletList = $conn->query("SELECT * FROM outlet WHERE status='aktif' ORDER BY nama_outlet");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = clean($_POST['nama'] ?? '');
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = clean($_POST['role'] ?? 'kasir');
    $outletId = $_POST['outlet_id'] !== '' ? (int)$_POST['outlet_id'] : null;
    $editId   = (int)($_POST['edit_id'] ?? 0);

    if (empty($nama))     $errors[] = "Nama lengkap wajib diisi.";
    elseif (strlen($nama)<3) $errors[] = "Nama minimal 3 karakter.";
    if (empty($username)) $errors[] = "Username wajib diisi.";
    elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/',$username)) $errors[] = "Username hanya huruf, angka, underscore (3-20 karakter).";
    if ($editId===0 && empty($password)) $errors[] = "Password wajib diisi untuk user baru.";
    elseif (!empty($password) && strlen($password)<6) $errors[] = "Password minimal 6 karakter.";
    if (!in_array($role,['admin','kasir'])) $errors[] = "Role tidak valid.";
    if ($role==='kasir' && !$outletId) $errors[] = "Kasir harus ditetapkan ke outlet.";

    if (empty($errors)) {
        $stmtCek = $conn->prepare("SELECT id FROM users WHERE username=? AND id!=?");
        $stmtCek->bind_param("si",$username,$editId); $stmtCek->execute();
        if ($stmtCek->get_result()->num_rows>0) $errors[] = "Username '$username' sudah digunakan.";
        $stmtCek->close();
    }

    if (empty($errors)) {
        if ($editId > 0) {
            if (!empty($password)) {
                $hp  = password_hash($password,PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET nama=?,username=?,password=?,role=?,outlet_id=? WHERE id=?");
                $stmt->bind_param("sssiii",$nama,$username,$hp,$role,$outletId,$editId);
            } else {
                $stmt = $conn->prepare("UPDATE users SET nama=?,username=?,role=?,outlet_id=? WHERE id=?");
                $stmt->bind_param("sssii",$nama,$username,$role,$outletId,$editId);
            }
            $stmt->execute(); $stmt->close();
            $success = "Data user berhasil diperbarui!";
        } else {
            $hp   = password_hash($password,PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (nama,username,password,role,outlet_id) VALUES (?,?,?,?,?)");
            $stmt->bind_param("ssssi",$nama,$username,$hp,$role,$outletId);
            $stmt->execute(); $stmt->close();
            $success = "User baru berhasil ditambahkan!";
        }
    }
}

// Hapus
if (isset($_GET['hapus'])) {
    $hapusId = (int)$_GET['hapus'];
    if ($hapusId===(int)$_SESSION['user_id']) { $errors[]="Tidak bisa menghapus akun sendiri!"; }
    else {
        $stmtCek = $conn->prepare("SELECT COUNT(*) AS t FROM transaksi WHERE kasir_id=?");
        $stmtCek->bind_param("i",$hapusId); $stmtCek->execute();
        if ($stmtCek->get_result()->fetch_assoc()['t']>0) {
            $errors[]="User tidak bisa dihapus, masih memiliki data transaksi.";
        } else {
            $stmtD = $conn->prepare("DELETE FROM users WHERE id=?");
            $stmtD->bind_param("i",$hapusId); $stmtD->execute(); $stmtD->close();
            $success="User berhasil dihapus.";
        }
        $stmtCek->close();
    }
}

$editData = null;
if (isset($_GET['edit'])) {
    $stmtE=$conn->prepare("SELECT * FROM users WHERE id=?");
    $stmtE->bind_param("i",(int)$_GET['edit']); $stmtE->execute();
    $editData=$stmtE->get_result()->fetch_assoc(); $stmtE->close();
}

$userList = $conn->query("
    SELECT u.*,o.nama_outlet
    FROM users u LEFT JOIN outlet o ON u.outlet_id=o.id
    ORDER BY u.role, u.nama
");
$conn->close();
include '../includes/header.php';
?>

<?php if($success): ?><div class="alert alert-success alert-auto-hide"><i class="bi bi-check-circle-fill me-1"></i><?=$success?></div><?php endif; ?>
<?php if(!empty($errors)): ?><div class="alert alert-danger"><?php foreach($errors as $e): ?><div><i class="bi bi-x-circle-fill me-1"></i><?=$e?></div><?php endforeach; ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person-plus me-1"></i><?=$editData?'Edit User':'Tambah User'?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if($editData): ?><input type="hidden" name="edit_id" value="<?=$editData['id']?>"><?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama" value="<?=$editData?clean($editData['nama']):''?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" value="<?=$editData?clean($editData['username']):''?>"
                               placeholder="3-20 karakter" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password <?=$editData?'(kosong = tidak diubah)':'<span class="text-danger">*</span>'?></label>
                        <input type="password" class="form-control" name="password"
                               placeholder="<?=$editData?'Isi jika ingin ganti':'Minimal 6 karakter'?>" <?=!$editData?'required':''?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role" id="roleSelect" onchange="toggleOutlet()">
                            <option value="kasir" <?=($editData&&$editData['role']==='kasir')||!$editData?'selected':''?>>Kasir</option>
                            <option value="admin" <?=($editData&&$editData['role']==='admin')?'selected':''?>>Admin</option>
                        </select>
                    </div>
                    <div class="mb-3" id="outletField">
                        <label class="form-label small fw-semibold">Outlet <span class="text-danger">*</span></label>
                        <select class="form-select" name="outlet_id">
                            <option value="">-- Pilih Outlet --</option>
                            <?php $outletList->data_seek(0); while($o=$outletList->fetch_assoc()): ?>
                            <option value="<?=$o['id']?>" <?=($editData&&$editData['outlet_id']==$o['id'])?'selected':''?>>
                                <?=clean($o['nama_outlet'])?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-text">Kasir harus terdaftar di satu outlet</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save me-1"></i><?=$editData?'Simpan Perubahan':'Tambah User'?>
                    </button>
                    <?php if($editData): ?><a href="users.php" class="btn btn-outline-secondary w-100 mt-2">Batal</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-1"></i>Daftar User</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>#</th><th>Nama</th><th>Username</th><th>Role</th><th>Outlet</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php $no=1; while($u=$userList->fetch_assoc()): ?>
                    <tr <?=$u['id']==$_SESSION['user_id']?'class="table-warning"':''?>>
                        <td><?=$no++?></td>
                        <td>
                            <?=clean($u['nama'])?>
                            <?php if($u['id']==$_SESSION['user_id']): ?><span class="badge bg-warning text-dark ms-1">Anda</span><?php endif; ?>
                        </td>
                        <td><code><?=clean($u['username'])?></code></td>
                        <td><span class="badge <?=$u['role']==='admin'?'bg-danger':'bg-primary'?>"><?=strtoupper($u['role'])?></span></td>
                        <td><?=$u['nama_outlet']?'<span class="badge bg-light text-dark border">'.clean($u['nama_outlet']).'</span>':'<span class="text-muted small">-</span>'?></td>
                        <td>
                            <a href="?edit=<?=$u['id']?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                            <?php if($u['id']!=$_SESSION['user_id']): ?>
                            <a href="?hapus=<?=$u['id']?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Hapus user <?=clean($u['nama'])?>?')"><i class="bi bi-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleOutlet() {
    const role = document.getElementById('roleSelect').value;
    document.getElementById('outletField').style.display = role === 'kasir' ? '' : 'none';
}
toggleOutlet();
</script>

<?php include '../includes/footer.php'; ?>
