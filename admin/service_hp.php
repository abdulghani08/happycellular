<?php
session_start();
require_once '../api/auth_helper.php';

// Proteksi halaman: Hanya admin yang boleh masuk
restrictTo(['admin']);

include '../connection.php';

// ==================== PROSES CRUD SERVICE ====================

// Tambah Service Baru
if(isset($_POST['action']) && $_POST['action'] == 'tambah') {
    $nama_pelanggan = mysqli_real_escape_string($koneksi, $_POST['nama_pelanggan']);
    $no_telp = mysqli_real_escape_string($koneksi, $_POST['no_telp']);
    $imei = mysqli_real_escape_string($koneksi, $_POST['imei']);
    $merk = mysqli_real_escape_string($koneksi, $_POST['merk']);
    $model = mysqli_real_escape_string($koneksi, $_POST['model']);
    $kerusakan = mysqli_real_escape_string($koneksi, $_POST['kerusakan']);
    $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan']);
    $biaya = mysqli_real_escape_string($koneksi, $_POST['biaya']);
    $status = 'antrian';
    
    $query = "INSERT INTO service_hp (nama_pelanggan, no_telp, imei, merk, model, kerusakan, catatan, biaya, status, tanggal_masuk) 
              VALUES ('$nama_pelanggan', '$no_telp', '$imei', '$merk', '$model', '$kerusakan', '$catatan', '$biaya', '$status', NOW())";
    
    if(mysqli_query($koneksi, $query)) {
        $_SESSION['success'] = "Service baru berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan service: " . mysqli_error($koneksi);
    }
    header("Location: service_hp.php");
    exit();
}

// Update Status Service
if(isset($_GET['action']) && $_GET['action'] == 'update_status' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $status = mysqli_real_escape_string($koneksi, $_GET['status']);
    
    $update = "UPDATE service_hp SET status = '$status'";
    if($status == 'selesai') {
        $update .= ", tanggal_selesai = NOW()";
    }
    $update .= " WHERE id = $id";
    
    if(mysqli_query($koneksi, $update)) {
        
        // ========== INSERT KE TABEL PENJUALAN JIKA STATUS MENJADI 'selesai' ==========
        if($status == 'selesai') {
            // Ambil data service yang baru diupdate
            $data = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM service_hp WHERE id = $id"));
            $nama_customer = mysqli_real_escape_string($koneksi, $data['nama_pelanggan']);
            $no_hp         = mysqli_real_escape_string($koneksi, $data['no_telp']);
            $biaya         = (int)$data['biaya'];
            
            // Gunakan referensi unik untuk kolom imei di penjualan
            $imei_ref = 'SVC-' . $id;
            $tanggal  = date('Y-m-d H:i:s');
            
            $insert = "INSERT INTO penjualan 
                        (imei, harga_jual, dp, nama_customer, no_hp, metode_pembayaran, 
                         status_pencairan, modal, tanggal, jenis_transaksi) 
                       VALUES 
                        ('$imei_ref', $biaya, 0, '$nama_customer', '$no_hp', 'Cash', 
                         'Cair', 0, '$tanggal', 'SERVICE')";
            
            mysqli_query($koneksi, $insert);
        }
        // ===================================================================
        
        $_SESSION['success'] = "Status service berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal update status!";
    }
    header("Location: service_hp.php");
    exit();
}

// Hapus Service
if(isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $query = "DELETE FROM service_hp WHERE id = $id";
    if(mysqli_query($koneksi, $query)) {
        $_SESSION['success'] = "Data service berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus data!";
    }
    header("Location: service_hp.php");
    exit();
}

// Filter & Pencarian
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$where = "WHERE 1=1";
if($filter_status) {
    $where .= " AND status = '$filter_status'";
}
if($search) {
    $where .= " AND (nama_pelanggan LIKE '%$search%' OR no_telp LIKE '%$search%' OR imei LIKE '%$search%' OR merk LIKE '%$search%')";
}

$query = "SELECT * FROM service_hp $where ORDER BY 
          FIELD(status, 'antrian', 'proses', 'selesai', 'diambil'),
          tanggal_masuk DESC";
$result = mysqli_query($koneksi, $query);

// Hitung statistik
$stat_antrian = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM service_hp WHERE status='antrian'"))['total'];
$stat_proses = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM service_hp WHERE status='proses'"))['total'];
$stat_selesai = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM service_hp WHERE status='selesai'"))['total'];
$stat_diambil = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM service_hp WHERE status='diambil'"))['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Service HP · Happy Cellular</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-body: #F4F7FA;
            --surface-card: #FFFFFF;
            --primary-dongker: #1E4A7A;
            --primary-dongker-soft: #EEF4FA;
            --accent-blue: #3B82F6;
            --text-main: #1E293B;
            --text-muted: #64748B;
            --success-green: #10B981;
            --bottom-nav-height: 72px;
            --sidebar-width: 260px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            min-height: 100vh;
            color: var(--text-main);
            padding-bottom: calc(var(--bottom-nav-height) + 2rem);
        }

        /* NAVBAR ATAS */
        .navbar-top {
            background: var(--surface-card);
            padding: 1.2rem 1.5rem 0.8rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        .logo-area { display: flex; align-items: center; gap: 10px; }
        .navbar-logo { width: 32px; height: 32px; object-fit: contain; border-radius: 8px; }
        .logo-text { font-weight: 800; font-size: 1.3rem; color: var(--primary-dongker); letter-spacing: -0.5px; }
        .logo-text small { font-size: 0.75rem; font-weight: 500; color: var(--text-muted); display: block; margin-top: -2px; }

        .user-badge {
            background: var(--primary-dongker-soft);
            padding: 8px 16px;
            border-radius: 20px;
            color: var(--primary-dongker);
            font-weight: 700;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .user-badge:after {
            display: inline-block;
            margin-left: 0.255em;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
        }

        .dropdown-menu-custom {
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(30, 41, 59, 0.1);
            border: 1px solid rgba(0,0,0,0.05);
            padding: 8px;
        }

        .dropdown-menu-custom .dropdown-item {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
            padding: 10px 14px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dropdown-menu-custom .dropdown-item i {
            color: var(--primary-dongker);
            font-size: 0.95rem;
            width: 16px;
            text-align: center;
        }

        .dropdown-menu-custom .dropdown-item:hover {
            background: var(--primary-dongker-soft);
            color: var(--primary-dongker);
        }

        .dropdown-menu-custom .dropdown-item.text-danger {
            color: #EF4444;
        }
        .dropdown-menu-custom .dropdown-item.text-danger i {
            color: #EF4444;
        }
        .dropdown-menu-custom .dropdown-item.text-danger:hover {
            background: rgba(239, 68, 68, 0.05);
        }

        /* MAIN WRAPPER */
        .main-wrapper {
            max-width: 540px;
            margin: 0 auto;
            padding: 1rem 1.2rem;
        }

        /* SECTION TITLE */
        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1.5rem 0 1rem;
        }

        .section-heading h3 {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-main);
            margin-bottom: 0;
        }

        .section-heading span {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--accent-blue);
            background: var(--primary-dongker-soft);
            padding: 4px 12px;
            border-radius: 20px;
        }

        /* GRID STATISTIK */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--surface-card);
            border-radius: 20px;
            padding: 1.2rem;
            position: relative;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.01);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .stat-icon-box {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: var(--primary-dongker-soft);
            color: var(--primary-dongker);
        }

        .trend-badge {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--success-green);
            background: rgba(16, 185, 129, 0.1);
            padding: 2px 8px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 2px;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
            margin-bottom: 2px;
        }

        .stat-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        /* CARD UMUM */
        .card-service {
            background: var(--surface-card);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 18px rgba(0,0,0,0.02);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0,0,0,0.01);
        }

        .card-header-service {
            font-weight: 700;
            font-size: 1rem;
            color: var(--primary-dongker);
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* FORM */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .form-control-service {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            font-size: 0.85rem;
            background: #FFFFFF;
            transition: 0.2s;
        }

        .form-control-service:focus {
            border-color: var(--primary-dongker);
            box-shadow: 0 0 0 3px rgba(30,74,122,0.1);
            outline: none;
        }

        textarea.form-control-service {
            resize: vertical;
            min-height: 80px;
        }

        .btn-primary-service {
            background: var(--primary-dongker);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 50px;
            transition: 0.2s;
            font-size: 0.85rem;
        }

        .btn-primary-service:hover {
            background: #0F3A5C;
            transform: translateY(-1px);
        }

        .btn-outline-service {
            border: 1.5px solid #E2E8F0;
            background: white;
            color: var(--text-muted);
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 50px;
            transition: 0.2s;
            font-size: 0.85rem;
        }

        .btn-outline-service:hover {
            border-color: var(--primary-dongker);
            color: var(--primary-dongker);
        }

        /* FILTER SECTION */
        .filter-section {
            background: #F8FAFC;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.2rem;
        }

        /* TABEL */
        .table-responsive-stack {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 0;
        }

        .table-responsive-stack table {
            min-width: 800px; /* Memastikan tabel bisa discroll */
            width: max-content;
        }

        .table-service {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-service thead th {
            background: var(--primary-dongker);
            color: white;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.8rem 0.8rem;
            border: none;
        }

        .table-service thead th:first-child {
            border-radius: 12px 0 0 0;
        }

        .table-service thead th:last-child {
            border-radius: 0 12px 0 0;
        }

        .table-service tbody td {
            padding: 0.8rem;
            vertical-align: middle;
            font-size: 0.8rem;
            border-bottom: 1px solid #F1F5F9;
            background: white;
        }

        .table-service tbody tr:last-child td:first-child {
            border-radius: 0 0 0 12px;
        }

        .table-service tbody tr:last-child td:last-child {
            border-radius: 0 0 12px 0;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .status-antrian { background: #FEF3C7; color: #D97706; }
        .status-proses { background: #DBEAFE; color: #2563EB; }
        .status-selesai { background: #DCFCE7; color: #166534; }
        .status-diambil { background: #E9D5FF; color: #6B21A8; }

        .btn-action {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            margin: 1px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        /* BOTTOM NAVIGATION */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: var(--bottom-nav-height);
            background: var(--surface-card);
            display: flex;
            justify-content: space-around;
            align-items: center;
            box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.04);
            border-top: 1px solid #EDF2F7;
            z-index: 1050;
            padding-bottom: env(safe-area-inset-bottom, 0);
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            text-decoration: none;
            color: #A0AEC0;
            font-size: 0.7rem;
            font-weight: 600;
            flex: 1;
            height: 100%;
            transition: all 0.2s ease;
        }

        .nav-item i { font-size: 1.25rem; }
        .nav-item.active { color: var(--primary-dongker); }

        /* Popup menu "Lainnya" */
        .more-menu-popup {
            position: fixed;
            bottom: calc(var(--bottom-nav-height) + 12px);
            left: 50%;
            transform: translateX(-50%) scale(0.95);
            width: calc(100% - 2.4rem);
            max-width: 500px;
            background: var(--surface-card);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(30, 41, 59, 0.15);
            padding: 10px;
            display: none;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            z-index: 1100;
            opacity: 0;
            transition: all 0.2s ease;
        }

        .more-menu-popup.show {
            display: grid;
            transform: translateX(-50%) scale(1);
            opacity: 1;
        }

        .more-menu-popup a, .more-menu-popup div {
            padding: 12px;
            text-decoration: none;
            color: var(--text-main);
            font-weight: 600;
            font-size: 0.8rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #F8FAFC;
            cursor: pointer;
        }

        .more-menu-popup a i, .more-menu-popup div i {
            color: var(--primary-dongker);
            font-size: 1rem;
        }

        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(2px);
            z-index: 1090;
            display: none;
        }

        .popup-overlay.show { display: block; }

        /* DESKTOP SIDEBAR */
        .desktop-sidebar { display: none; }

        @media (min-width: 992px) {
            body {
                padding-bottom: 0;
                display: flex;
                background: #F8FAFC;
            }

            .bottom-nav, .popup-overlay, .more-menu-popup {
                display: none !important;
            }

            .navbar-top {
                position: static;
                box-shadow: none;
                background: transparent;
                padding: 1.5rem 2rem 0;
                width: 100%;
            }
            .navbar-top .logo-area { display: none; }

            .desktop-sidebar {
                display: flex;
                flex-direction: column;
                width: var(--sidebar-width);
                background: var(--surface-card);
                height: 100vh;
                position: sticky;
                top: 0;
                border-right: 1px solid #E2E8F0;
                padding: 1.5rem;
                z-index: 1010;
            }

            .sidebar-brand {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 2rem;
                padding-bottom: 1rem;
                border-bottom: 1px solid #F1F5F9;
            }

            .sidebar-menu { display: flex; flex-direction: column; gap: 5px; flex-grow: 1; }

            .sidebar-link {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 16px;
                color: var(--text-muted);
                text-decoration: none;
                font-weight: 600;
                font-size: 0.85rem;
                border-radius: 12px;
                transition: all 0.2s;
            }

            .sidebar-link i { font-size: 1.1rem; width: 20px; text-align: center; }
            .sidebar-link:hover, .sidebar-link.active { background: var(--primary-dongker-soft); color: var(--primary-dongker); }

            .sidebar-divider { height: 1px; background: #F1F5F9; margin: 10px 0; }

            .content-wrapper { flex-grow: 1; display: flex; flex-direction: column; min-width: 0; }

            .main-wrapper {
                max-width: 1200px;
                width: 100%;
                padding: 1.5rem 2rem;
                margin: 0;
            }

            .stats-grid { grid-template-columns: repeat(4, 1fr); gap: 20px; }
            .form-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<!-- SIDEBAR KHUSUS DESKTOP -->
<div class="desktop-sidebar">
    <div class="sidebar-brand">
        <img src="asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
        <div class="logo-text">Happy Cell<small>Admin Panel</small></div>
    </div>
    <div class="sidebar-menu">
        <a href="home_admin.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="stock_in.php" class="sidebar-link"><i class="fas fa-arrow-circle-down" style="color: #10B981;"></i> Stock In</a>
        <a href="admin_stock_out.php" class="sidebar-link"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i> Stock Out</a>
        <a href="return.php" class="sidebar-link"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i> Retur</a>
        <div class="sidebar-divider"></div>
        <a href="data_master.php" class="sidebar-link"><i class="fas fa-database"></i> Data Master</a>
        <a href="data_barang.php" class="sidebar-link"><i class="fas fa-box"></i> Data Produk</a>
        <a href="penjualan.php" class="sidebar-link"><i class="fas fa-shopping-cart"></i> Transaksi Jual</a>
        <a href="keuangan.php" class="sidebar-link"><i class="fas fa-chart-pie"></i> Analisis Laba</a>
        <a href="manajemen_label.php" class="sidebar-link"><i class="fas fa-print"></i> Cetak Label</a>
        <a href="tukar_tambah.php" class="sidebar-link"><i class="fas fa-exchange-alt"></i> Tukar Tambah</a>
        <a href="service_hp.php" class="sidebar-link active"><i class="fas fa-tools"></i> Service HP</a>
    </div>
</div>

<!-- CONTAINER UTAMA KANAN -->
<div class="content-wrapper">

    <div class="navbar-top">
        <div class="logo-area">
            <img src="asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
            <div class="logo-text">Happy Cell<small>Admin Panel</small></div>
        </div>
        
        <div class="dropdown">
            <button class="user-badge" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom mt-2">
                <li><a class="dropdown-item" href="kelola_user.php"><i class="fas fa-users-cog"></i> Kelola User</a></li>
                <li><a class="dropdown-item" href="ganti_password.php"><i class="fas fa-key"></i> Ganti Password</a></li>
                <li><hr class="dropdown-divider" style="border-color: #EDF2F7;"></li>
                <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
            </ul>
        </div>
    </div>

    <div class="main-wrapper">

        <!-- Alert Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 16px;">
                <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius: 16px;">
                <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Heading & Statistik -->
        <div class="section-heading">
            <h3><i class="fas fa-tools me-2" style="color: var(--primary-dongker);"></i>Manajemen Service HP</h3>
            <a href="riwayat_service_hp.php" class="btn-primary-service" style="padding: 6px 16px; font-size: 0.8rem;"><i class="fas fa-history me-1"></i> Riwayat</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon-box" style="background: #FEF3C7; color: #D97706;"><i class="fas fa-clock"></i></div>
                    <div class="trend-badge" style="background: #FEF3C7; color: #D97706;">Antrian</div>
                </div>
                <div class="stat-value"><?= $stat_antrian ?></div>
                <div class="stat-label">Menunggu</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon-box" style="background: #DBEAFE; color: #2563EB;"><i class="fas fa-gear"></i></div>
                    <div class="trend-badge" style="background: #DBEAFE; color: #2563EB;">Proses</div>
                </div>
                <div class="stat-value"><?= $stat_proses ?></div>
                <div class="stat-label">Dikerjakan</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon-box" style="background: #DCFCE7; color: #166534;"><i class="fas fa-check-circle"></i></div>
                    <div class="trend-badge" style="background: #DCFCE7; color: #166534;">Selesai</div>
                </div>
                <div class="stat-value"><?= $stat_selesai ?></div>
                <div class="stat-label">Siap Diambil</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon-box" style="background: #E9D5FF; color: #6B21A8;"><i class="fas fa-hand-peace"></i></div>
                    <div class="trend-badge" style="background: #E9D5FF; color: #6B21A8;">Diambil</div>
                </div>
                <div class="stat-value"><?= $stat_diambil ?></div>
                <div class="stat-label">Selesai</div>
            </div>
        </div>

        <!-- Form Tambah Service -->
        <div class="card-service">
            <div class="card-header-service">
                <i class="fas fa-plus-circle"></i> Tambah Service Baru
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="tambah">
                <div class="form-grid">
                    <input type="text" name="nama_pelanggan" class="form-control-service" placeholder="Nama Pelanggan *" required>
                    <input type="text" name="no_telp" class="form-control-service" placeholder="No. Telepon">
                    <input type="text" name="imei" class="form-control-service" placeholder="IMEI (jika ada)">
                    <input type="text" name="merk" class="form-control-service" placeholder="Merk HP *" required>
                    <input type="text" name="model" class="form-control-service" placeholder="Model HP">
                    <input type="number" name="biaya" class="form-control-service" placeholder="Biaya Service">
                    <div style="grid-column: span 2;">
                        <textarea name="kerusakan" class="form-control-service" placeholder="Jenis Kerusakan / Keluhan *" required></textarea>
                    </div>
                    <div style="grid-column: span 2;">
                        <textarea name="catatan" class="form-control-service" placeholder="Catatan Tambahan (opsional)"></textarea>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn-primary-service">
                        <i class="fas fa-save me-2"></i> Simpan Service
                    </button>
                </div>
            </form>
        </div>

        <!-- Daftar Service -->
        <div class="card-service">
            <div class="card-header-service">
                <i class="fas fa-list"></i> Daftar Service HP
            </div>

            <!-- Filter -->
            <div class="filter-section">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted mb-1">Cari</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Nama, IMEI, atau No HP..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Filter Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Semua</option>
                            <option value="antrian" <?= $filter_status == 'antrian' ? 'selected' : '' ?>>Antrian</option>
                            <option value="proses" <?= $filter_status == 'proses' ? 'selected' : '' ?>>Proses</option>
                            <option value="selesai" <?= $filter_status == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                            <option value="diambil" <?= $filter_status == 'diambil' ? 'selected' : '' ?>>Diambil</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn-primary-service px-4 py-2"><i class="fas fa-search me-1"></i> Filter</button>
                            <a href="service_hp.php" class="btn-outline-service px-4 py-2"><i class="fas fa-undo-alt me-1"></i> Reset</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="table-responsive-stack">
                <table class="table-service">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tgl Masuk</th>
                            <th>Pelanggan</th>
                            <th>HP</th>
                            <th>Kerusakan</th>
                            <th>Biaya</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): 
                            while($row = mysqli_fetch_assoc($result)):
                                $status_class = '';
                                switch($row['status']) {
                                    case 'antrian': $status_class = 'status-antrian'; break;
                                    case 'proses': $status_class = 'status-proses'; break;
                                    case 'selesai': $status_class = 'status-selesai'; break;
                                    case 'diambil': $status_class = 'status-diambil'; break;
                                }
                        ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td class="small"><?= date('d/m/Y H:i', strtotime($row['tanggal_masuk'])) ?></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($row['nama_pelanggan']) ?></div>
                                <?php if($row['no_telp']): ?>
                                    <small class="text-muted"><?= htmlspecialchars($row['no_telp']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($row['merk']) ?> <?= htmlspecialchars($row['model']) ?></div>
                                <?php if($row['imei']): ?>
                                    <small class="text-muted">IMEI: <?= htmlspecialchars($row['imei']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(substr($row['kerusakan'], 0, 40)) . (strlen($row['kerusakan']) > 40 ? '...' : '') ?></td>
                            <td class="fw-semibold" style="color:var(--primary-dongker);">Rp <?= number_format($row['biaya'], 0, ',', '.') ?></td>
                            <td><span class="status-badge <?= $status_class ?>"><i class="fas <?= $row['status'] == 'antrian' ? 'fa-clock' : ($row['status'] == 'proses' ? 'fa-gear' : ($row['status'] == 'selesai' ? 'fa-check-circle' : 'fa-hand-peace')) ?>"></i> <?= ucfirst($row['status']) ?></span></td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php if($row['status'] == 'antrian'): ?>
                                        <a href="?action=update_status&id=<?= $row['id'] ?>&status=proses" class="btn-action btn-warning text-dark" onclick="return confirm('Mulai proses service ini?')"><i class="fas fa-play"></i> Proses</a>
                                    <?php elseif($row['status'] == 'proses'): ?>
                                        <a href="?action=update_status&id=<?= $row['id'] ?>&status=selesai" class="btn-action btn-success" onclick="return confirm('Tandai selesai?')"><i class="fas fa-check"></i> Selesai</a>
                                    <?php elseif($row['status'] == 'selesai'): ?>
                                        <a href="?action=update_status&id=<?= $row['id'] ?>&status=diambil" class="btn-action btn-info text-white" onclick="return confirm('Konfirmasi sudah diambil?')"><i class="fas fa-hand-peace"></i> Diambil</a>
                                    <?php endif; ?>
                                    <a href="javascript:void(0)" onclick="showDetail(<?= htmlspecialchars(json_encode($row)) ?>)" class="btn-action btn-secondary text-white"><i class="fas fa-eye"></i></a>
                                    <a href="?action=hapus&id=<?= $row['id'] ?>" class="btn-action btn-danger" onclick="return confirm('Hapus data?')"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-tools fa-2x mb-2 d-block" style="color: #CBD5E1;"></i>
                                Belum ada data service
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- NAVIGASI BAWAH KHUSUS MOBILE -->
<nav class="bottom-nav">
    <a href="home_admin.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a href="stock_in.php" class="nav-item"><i class="fas fa-arrow-circle-down" style="color: #10B981;"></i><span>Stock In</span></a>
    <a href="admin_stock_out.php" class="nav-item"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i><span>Stock Out</span></a>
    <a href="return.php" class="nav-item"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i><span>Retur</span></a>
    <div class="nav-item active" id="moreMenuBtn" role="button"><i class="fas fa-bars"></i><span>Menu</span></div>
</nav>

<div class="more-menu-popup" id="moreMenuPopup">
    <a href="data_master.php"><i class="fas fa-database"></i> Data Master</a>
    <a href="data_barang.php"><i class="fas fa-box"></i> Data Produk</a>
    <a href="penjualan.php"><i class="fas fa-shopping-cart"></i> Transaksi Jual</a>
    <a href="keuangan.php"><i class="fas fa-chart-pie"></i> Analisis Laba</a>
    <a href="manajemen_label.php"><i class="fas fa-print"></i> Cetak Label</a>
    <a href="tukar_tambah.php"><i class="fas fa-exchange-alt"></i> Tukar Tambah</a>
    <a href="service_hp.php"><i class="fas fa-tools"></i> Service HP</a>
</div>

<div class="popup-overlay" id="popupOverlay"></div>

<!-- Modal Detail Service -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 bg-light rounded-top-4">
                <h5 class="modal-title fw-bold" style="color: var(--primary-dongker);"><i class="fas fa-info-circle me-2"></i>Detail Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detailContent"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Popup Menu Mobile
    const moreBtn = document.getElementById('moreMenuBtn');
    const popup = document.getElementById('moreMenuPopup');
    const overlay = document.getElementById('popupOverlay');

    if(moreBtn) {
        moreBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            popup.classList.toggle('show');
            overlay.classList.toggle('show');
        });
    }

    if(overlay) {
        overlay.addEventListener('click', function() {
            popup.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    document.addEventListener('click', function(e) {
        if (popup && !popup.contains(e.target) && e.target !== moreBtn && !moreBtn.contains(e.target)) {
            popup.classList.remove('show');
            overlay.classList.remove('show');
        }
    });

    // Detail Service Modal
    function showDetail(data) {
        const modal = new bootstrap.Modal(document.getElementById('detailModal'));
        const content = document.getElementById('detailContent');
        const statusIcon = {
            'antrian': '<i class="fas fa-clock"></i>',
            'proses': '<i class="fas fa-gear"></i>',
            'selesai': '<i class="fas fa-check-circle"></i>',
            'diambil': '<i class="fas fa-hand-peace"></i>'
        };
        content.innerHTML = `
            <div class="row g-3">
                <div class="col-6"><div class="text-muted small">ID Service</div><div class="fw-bold">#${data.id}</div></div>
                <div class="col-6"><div class="text-muted small">Tanggal Masuk</div><div class="fw-bold">${new Date(data.tanggal_masuk).toLocaleDateString('id-ID')} ${new Date(data.tanggal_masuk).toLocaleTimeString('id-ID')}</div></div>
                <div class="col-12"><hr class="my-1"></div>
                <div class="col-6"><div class="text-muted small">Nama</div><div class="fw-semibold">${escapeHtml(data.nama_pelanggan)}</div></div>
                <div class="col-6"><div class="text-muted small">No. Telepon</div><div class="fw-semibold">${data.no_telp || '-'}</div></div>
                <div class="col-6"><div class="text-muted small">Merk & Model</div><div class="fw-semibold">${escapeHtml(data.merk)} ${escapeHtml(data.model)}</div></div>
                <div class="col-6"><div class="text-muted small">IMEI</div><div class="fw-semibold font-monospace">${data.imei || '-'}</div></div>
                <div class="col-12"><div class="text-muted small">Kerusakan</div><div class="bg-light p-2 rounded-3">${escapeHtml(data.kerusakan)}</div></div>
                ${data.catatan ? `<div class="col-12"><div class="text-muted small">Catatan</div><div class="bg-light p-2 rounded-3">${escapeHtml(data.catatan)}</div></div>` : ''}
                <div class="col-6"><div class="text-muted small">Biaya</div><div class="fw-bold text-success">Rp ${new Intl.NumberFormat('id-ID').format(data.biaya)}</div></div>
                <div class="col-6"><div class="text-muted small">Status</div><span class="status-badge ${getStatusClass(data.status)}">${statusIcon[data.status]} ${data.status.charAt(0).toUpperCase() + data.status.slice(1)}</span></div>
                ${data.tanggal_selesai ? `<div class="col-12"><div class="text-muted small">Tgl Selesai</div><div class="fw-semibold">${new Date(data.tanggal_selesai).toLocaleDateString('id-ID')} ${new Date(data.tanggal_selesai).toLocaleTimeString('id-ID')}</div></div>` : ''}
            </div>
        `;
        modal.show();
    }

    function escapeHtml(str) {
        if(!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if(m === '&') return '&amp;';
            if(m === '<') return '&lt;';
            if(m === '>') return '&gt;';
            return m;
        });
    }

    function getStatusClass(status) {
        const classes = {
            'antrian': 'status-antrian',
            'proses': 'status-proses',
            'selesai': 'status-selesai',
            'diambil': 'status-diambil'
        };
        return classes[status] || '';
    }
</script>
</body>
</html>