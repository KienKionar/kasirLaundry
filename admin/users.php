<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle  = 'Kelola User';
$activeMenu = 'users';
$conn       = getConnection();
$errors     = [];
$success    = '';

// Proses tambah/edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = clean($_POST['nama'] ?? '');
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = clean($_POST['role'] ?? 'kasir');
    $editId   = (int)($_POST['edit_id'] ?? 0);

    // Validasi
    if (empty($nama)) {
        $errors[] = "Nama lengkap wajib diisi.";
    } elseif (strlen($nama) < 3) {
        $errors[] = "Nama minimal 3 karakter.";
    }

    if (empty($username)) {
        $errors[] = "Username wajib diisi.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors[] = "Username hanya boleh huruf, angka, underscore. Panjang 3-20 karakter.";
    }

    if ($editId === 0 && empty($password)) {
        $errors[] = "Password wajib diisi untuk user baru.";
    } elseif (!empty($password) && strlen($password) < 6) {
        $errors[] = "Password minimal 6 karakter.";
    }

    if (!in_array($role, ['admin', 'kasir'])) {
        $errors[] = "Role tidak valid.";
    }

    if (empty($errors)) {
        // Cek username duplikat (kecuali user yang sedang diedit)
        $stmtCek = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmtCek->bind_param("si", $username, $editId);
        $stmtCek->execute();
        if ($stmtCek->get_result()->num_rows > 0) {
            $errors[] = "Username '$username' sudah digunakan.";
        }
        $stmtCek->close();
    }

    if (empty($errors)) {
        if ($editId > 0) {
            if (!empty($password)) {
                // Update dengan password baru
                $hashedPwd = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET nama=?, username=?, password=?, role=? WHERE id=?");
                $stmt->bind_param("ssssi", $nama, $username, $hashedPwd, $role, $editId);
            } else {
                // Update tanpa mengubah password
                $stmt = $conn->prepare("UPDATE users SET nama=?, username=?, role=? WHERE id=?");
                $stmt->bind_param("sssi", $nama, $username, $role, $editId);
            }
            $stmt->execute();
            $stmt->close();
            $success = "Data user berhasil diperbarui!";
        } else {
            $hashedPwd = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nama, $username, $hashedPwd, $role);
            $stmt->execute();
            $stmt->close();
            $success = "User baru berhasil ditambahkan!";
        }
    }
}

// Hapus user
if (isset($_GET['hapus'])) {
    $hapusId = (int)$_GET['hapus'];
    // Tidak boleh hapus diri sendiri
    if ($hapusId === (int)$_SESSION['user_id']) {
        $errors[] = "Tidak bisa menghapus akun Anda sendiri!";
    } else {
        // Cek apakah user punya transaksi
        $stmtCek = $conn->prepare("SELECT COUNT(*) as total FROM transaksi WHERE kasir_id = ?");
        $stmtCek->bind_param("i", $hapusId);
        $stmtCek->execute();
        $jumlah = $stmtCek->get_result()->fetch_assoc()['total'];
        $stmtCek->close();

        if ($jumlah > 0) {
            $errors[] = "User tidak bisa dihapus karena memiliki $jumlah data transaksi.";
        } else {
            $stmtDel = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmtDel->bind_param("i", $hapusId);
            $stmtDel->execute();
            $stmtDel->close();
            $success = "User berhasil dihapus.";
        }
    }
}

// Data untuk edit
$editData = null;
if (isset($_GET['edit'])) {
    $editId  = (int)$_GET['edit'];
    $stmtE   = $conn->prepare("SELECT id, nama, username, role FROM users WHERE id = ?");
    $stmtE->bind_param("i", $editId);
    $stmtE->execute();
    $editData = $stmtE->get_result()->fetch_assoc();
    $stmtE->close();
}

$userList = $conn->query("SELECT id, nama, username, role, created_at FROM users ORDER BY role, nama");
$conn->close();
include '../includes/header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success alert-auto-hide"><i class="bi bi-check-circle-fill me-1"></i><?= $success ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><div><i class="bi bi-x-circle-fill me-1"></i><?= $e ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <!-- Form -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person-plus me-1"></i> <?= $editData ? 'Edit User' : 'Tambah User' ?>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <?php if ($editData): ?>
                        <input type="hidden" name="edit_id" value="<?= $editData['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama"
                               value="<?= $editData ? clean($editData['nama']) : (isset($_POST['nama']) ? clean($_POST['nama']) : '') ?>"
                               required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username"
                               value="<?= $editData ? clean($editData['username']) : (isset($_POST['username']) ? clean($_POST['username']) : '') ?>"
                               placeholder="Huruf, angka, underscore" required>
                        <div class="form-text">3-20 karakter, tanpa spasi</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">
                            Password <?= $editData ? '(kosongkan jika tidak diubah)' : '<span class="text-danger">*</span>' ?>
                        </label>
                        <input type="password" class="form-control" name="password"
                               placeholder="<?= $editData ? 'Isi jika ingin mengubah password' : 'Minimal 6 karakter' ?>"
                               <?= !$editData ? 'required' : '' ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role">
                            <option value="kasir" <?= ($editData && $editData['role'] === 'kasir') || !$editData ? 'selected' : '' ?>>Kasir</option>
                            <option value="admin" <?= ($editData && $editData['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save me-1"></i> <?= $editData ? 'Simpan Perubahan' : 'Tambah User' ?>
                    </button>
                    <?php if ($editData): ?>
                        <a href="users.php" class="btn btn-outline-secondary w-100 mt-2">Batal</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel User -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-people me-1"></i> Daftar User
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Tgl Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        if ($userList->num_rows === 0):
                        ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada user</td></tr>
                        <?php else: while ($u = $userList->fetch_assoc()): ?>
                            <tr <?= $u['id'] == $_SESSION['user_id'] ? 'class="table-warning"' : '' ?>>
                                <td><?= $no++ ?></td>
                                <td>
                                    <?= clean($u['nama']) ?>
                                    <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                        <span class="badge bg-warning text-dark ms-1">Anda</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= clean($u['username']) ?></code></td>
                                <td>
                                    <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-primary' ?>">
                                        <?= strtoupper($u['role']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <a href="?hapus=<?= $u['id'] ?>" class="btn btn-sm btn-danger"
                                           onclick="return confirm('Yakin hapus user <?= clean($u['nama']) ?>?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
