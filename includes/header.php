<?php
// File ini dipanggil di setiap halaman sebagai header
// Variabel $pageTitle harus didefinisikan sebelum include file ini
$pageTitle = $pageTitle ?? 'Laundry App';
$activeMenu = $activeMenu ?? '';
$role = getRole();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($pageTitle) ?> - LaundryKu</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 240px;
            --sidebar-bg: #1a2d4e;
            --sidebar-text: #c8d6e5;
            --sidebar-active: #3498db;
            --topbar-height: 60px;
        }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }

        /* Sidebar */
        #sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
            transition: all 0.3s;
        }
        #sidebar .sidebar-brand {
            padding: 18px 20px;
            background: #12203a;
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
            text-decoration: none;
            display: block;
        }
        #sidebar .sidebar-brand span { color: var(--sidebar-active); }
        #sidebar .nav-link {
            color: var(--sidebar-text);
            padding: 10px 20px;
            border-radius: 0;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        #sidebar .nav-link:hover,
        #sidebar .nav-link.active {
            background: var(--sidebar-active);
            color: #fff;
        }
        #sidebar .nav-link i { margin-right: 8px; width: 18px; }
        #sidebar .sidebar-label {
            color: #6c8aa0;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 15px 20px 5px;
        }

        /* Main content */
        #main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        /* Topbar */
        #topbar {
            height: var(--topbar-height);
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 99;
        }

        /* Cards */
        .stat-card { border: none; border-radius: 12px; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }

        /* Tables */
        .table th { background: #f8f9fa; font-size: 0.85rem; font-weight: 600; }

        /* Badge status */
        .badge-belum { background-color: #ffc107; color: #000; }
        .badge-diproses { background-color: #0d6efd; }
        .badge-selesai { background-color: #198754; }
        .badge-diambil { background-color: #6c757d; }

        @media (max-width: 768px) {
            #sidebar { margin-left: calc(-1 * var(--sidebar-width)); }
            #sidebar.show { margin-left: 0; }
            #main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<nav id="sidebar">
    <a href="<?= BASE_URL ?>/<?= $role === 'admin' ? 'admin' : 'kasir' ?>/dashboard.php" class="sidebar-brand">
        <i class="bi bi-basket2-fill"></i> Laundry<span>Ku</span>
    </a>

    <div class="py-2">
        <!-- Menu Kasir (semua role bisa lihat) -->
        <div class="sidebar-label">Kasir</div>
        <a href="<?= BASE_URL ?>/kasir/dashboard.php" class="nav-link <?= $activeMenu === 'kasir-dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="<?= BASE_URL ?>/kasir/transaksi_baru.php" class="nav-link <?= $activeMenu === 'transaksi-baru' ? 'active' : '' ?>">
            <i class="bi bi-plus-circle"></i> Transaksi Baru
        </a>
        <a href="<?= BASE_URL ?>/kasir/daftar_transaksi.php" class="nav-link <?= $activeMenu === 'daftar-transaksi' ? 'active' : '' ?>">
            <i class="bi bi-list-ul"></i> Daftar Transaksi
        </a>
        <a href="<?= BASE_URL ?>/kasir/pelanggan.php" class="nav-link <?= $activeMenu === 'pelanggan' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Data Pelanggan
        </a>

        <?php if ($role === 'admin'): ?>
        <!-- Menu Admin -->
        <div class="sidebar-label mt-2">Admin</div>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link <?= $activeMenu === 'admin-dashboard' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i> Laporan
        </a>
        <a href="<?= BASE_URL ?>/admin/layanan.php" class="nav-link <?= $activeMenu === 'layanan' ? 'active' : '' ?>">
            <i class="bi bi-tags"></i> Data Layanan
        </a>
        <a href="<?= BASE_URL ?>/admin/users.php" class="nav-link <?= $activeMenu === 'users' ? 'active' : '' ?>">
            <i class="bi bi-person-gear"></i> Kelola User
        </a>
        <?php endif; ?>

        <div class="sidebar-label mt-2">Akun</div>
        <a href="<?= BASE_URL ?>/logout.php" class="nav-link text-danger">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </div>
</nav>

<!-- MAIN CONTENT -->
<div id="main-content">
    <!-- TOPBAR -->
    <div id="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-light d-md-none" id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            <h6 class="mb-0 fw-semibold text-muted"><?= clean($pageTitle) ?></h6>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-<?= $role === 'admin' ? 'danger' : 'primary' ?> rounded-pill">
                <?= strtoupper($role) ?>
            </span>
            <span class="fw-semibold text-dark small"><?= clean($_SESSION['nama'] ?? '') ?></span>
        </div>
    </div>

    <!-- CONTENT AREA -->
    <div class="p-4">
