<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header("Location: " . BASE_URL . (getRole() === 'admin' ? "/admin/dashboard.php" : "/kasir/dashboard.php"));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Username dan password wajib diisi!";
    } else {
        $conn = getConnection();
        $stmt = $conn->prepare("
            SELECT u.id, u.nama, u.username, u.password, u.role,
                   o.id AS outlet_id, o.nama_outlet, o.kode_outlet
            FROM users u
            LEFT JOIN outlet o ON u.outlet_id = o.id
            WHERE u.username = ?
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['nama']        = $user['nama'];
            $_SESSION['username']    = $user['username'];
            $_SESSION['role']        = $user['role'];
            $_SESSION['outlet_id']   = $user['outlet_id'];
            $_SESSION['nama_outlet'] = $user['nama_outlet'] ?? '';
            $_SESSION['kode_outlet'] = $user['kode_outlet'] ?? 'LDR';

            header("Location: " . BASE_URL . ($user['role'] === 'admin' ? "/admin/dashboard.php" : "/kasir/dashboard.php"));
            exit();
        } else {
            $error = "Username atau password salah!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LaundryKu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg,#1a2d4e 0%,#2980b9 100%);
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            font-family:'Segoe UI',sans-serif;
        }
        .login-card {
            background:#fff; border-radius:16px; padding:40px 36px;
            width:100%; max-width:400px; box-shadow:0 20px 60px rgba(0,0,0,.3);
        }
        .btn-login { background:#1a2d4e; color:#fff; border:none; padding:10px; font-weight:600; }
        .btn-login:hover { background:#2980b9; color:#fff; }
        .hint-box { background:#f0f7ff; border:1px solid #b8d9f7; border-radius:8px; padding:10px 14px; font-size:.82rem; color:#2c5282; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center mb-4">
        <div style="font-size:2.5rem;color:#1a2d4e"><i class="bi bi-basket2-fill"></i></div>
        <h4 class="fw-bold mt-2 mb-0">LaundryKu</h4>
        <p class="text-muted small">Sistem Kasir Laundry</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
            <i class="bi bi-exclamation-triangle-fill"></i><small><?= $error ?></small>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="mb-3">
            <label class="form-label fw-semibold small">Username</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control" name="username"
                       value="<?= isset($_POST['username']) ? clean($_POST['username']) : '' ?>"
                       placeholder="Masukkan username" required autofocus>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label fw-semibold small">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control" name="password" id="pwd" placeholder="Password" required>
                <button class="btn btn-outline-secondary" type="button" onclick="
                    var p=document.getElementById('pwd');
                    p.type=p.type==='password'?'text':'password';
                "><i class="bi bi-eye"></i></button>
            </div>
        </div>
        <button type="submit" class="btn btn-login w-100 rounded-pill">
            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
        </button>
    </form>

    <div class="hint-box mt-4">
        <strong><i class="bi bi-info-circle"></i> Akun Default:</strong><br>
        👤 Admin &nbsp;: <code>admin</code> / <code>password</code><br>
        👤 Kasir 1 : <code>kasir1</code> / <code>password</code><br>
        👤 Kasir 2 : <code>kasir2</code> / <code>password</code>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
