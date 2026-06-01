<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Jika sudah login, redirect ke dashboard sesuai role
if (isLoggedIn()) {
    if (getRole() === 'admin') {
        header("Location: " . BASE_URL . "/admin/dashboard.php");
    } else {
        header("Location: " . BASE_URL . "/kasir/dashboard.php");
    }
    exit();
}

$error = '';

// Proses form login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validasi input tidak boleh kosong
    if (empty($username) || empty($password)) {
        $error = "Username dan password wajib diisi!";
    } else {
        $conn = getConnection();

        // Cari user berdasarkan username
        $stmt = $conn->prepare("SELECT id, nama, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Verifikasi password
            if (password_verify($password, $user['password'])) {
                // Login berhasil - simpan data ke session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama']    = $user['nama'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']    = $user['role'];

                // Redirect sesuai role
                if ($user['role'] === 'admin') {
                    header("Location: " . BASE_URL . "/admin/dashboard.php");
                } else {
                    header("Location: " . BASE_URL . "/kasir/dashboard.php");
                }
                exit();
            } else {
                $error = "Password salah!";
            }
        } else {
            $error = "Username tidak ditemukan!";
        }
        $stmt->close();
        $conn->close();
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
            background: linear-gradient(135deg, #1a2d4e 0%, #2980b9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            padding: 40px 36px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-logo {
            font-size: 2.5rem;
            color: #1a2d4e;
        }
        .btn-login {
            background: #1a2d4e;
            color: #fff;
            border: none;
            padding: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .btn-login:hover { background: #2980b9; color: #fff; }
        .form-control:focus { border-color: #2980b9; box-shadow: 0 0 0 0.2rem rgba(41,128,185,0.25); }
        .hint-box {
            background: #f0f7ff;
            border: 1px solid #b8d9f7;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.82rem;
            color: #2c5282;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <div class="login-logo"><i class="bi bi-basket2-fill"></i></div>
            <h4 class="fw-bold text-dark mt-2 mb-0">LaundryKu</h4>
            <p class="text-muted small">Sistem Kasir Laundry</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <small><?= $error ?></small>
            </div>
        <?php endif; ?>

        <form action="" method="POST" novalidate>
            <div class="mb-3">
                <label for="username" class="form-label fw-semibold small">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<?= isset($_POST['username']) ? clean($_POST['username']) : '' ?>"
                           placeholder="Masukkan username" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-semibold small">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Masukkan password" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-login w-100 rounded-pill">
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
            </button>
        </form>

        <div class="hint-box mt-4">
            <strong><i class="bi bi-info-circle"></i> Akun Default:</strong><br>
            👤 Admin: <code>admin</code> / <code>password</code><br>
            👤 Kasir: <code>kasir1</code> / <code>password</code>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle show/hide password
        document.getElementById('togglePassword').addEventListener('click', function () {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                pwd.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });
    </script>
</body>
</html>
