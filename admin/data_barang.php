<?php
session_start();
require_once '../api/auth_helper.php';

// Proteksi halaman: Hanya admin yang boleh masuk
restrictTo(['admin']);
include '../connection.php';

/**
 * Fungsi untuk menghasilkan warna hex unik berdasarkan string nama.
 * Digunakan untuk membedakan warna badge supplier.
 */
function getSupplierColor($name) {
    $hash = crc32(strtoupper($name));
    $r = ($hash & 0xFF0000) >> 16;
    $g = ($hash & 0x00FF00) >> 8;
    $b = $hash & 0x0000FF;
    $r = ($r + 80) % 256;
    $g = ($g + 80) % 256;
    $b = ($b + 80) % 256;
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// ==========================================
// PENGATURAN PAGINATION (PEMBATASAN DATA)
// ==========================================
$limit = 20; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// ==========================================
// HITUNG JUMLAH DATA PER STATUS (UNTUK BADGE FILTER)
// ==========================================
$counts = ['READY' => 0, 'NO READY' => 0, 'RETURN_SUPPLIER' => 0, 'TOTAL' => 0];
$countStatusQuery = "SELECT status, COUNT(*) as total FROM barang WHERE 1=1";
$countStatusParams = [];
$countStatusTypes = '';

// Terapkan filter pencarian juga agar angkanya sinkron dengan hasil search
if($search){
    $searchCond = " AND (nama LIKE ? OR tipe LIKE ? OR imei LIKE ? OR warna LIKE ? OR internal LIKE ? OR supplier LIKE ? OR keterangan LIKE ?)";
    $countStatusQuery .= $searchCond;
    $like = "%$search%";
    for ($i = 0; $i < 7; $i++) {
        $countStatusParams[] = $like;
        $countStatusTypes .= 's';
    }
}
$countStatusQuery .= " GROUP BY status";

$stmtStatusCount = mysqli_prepare($koneksi, $countStatusQuery);
if(!empty($countStatusParams)){
    mysqli_stmt_bind_param($stmtStatusCount, $countStatusTypes, ...$countStatusParams);
}
mysqli_stmt_execute($stmtStatusCount);
$resStatusCount = mysqli_stmt_get_result($stmtStatusCount);
while($rowStatus = mysqli_fetch_assoc($resStatusCount)) {
    if (array_key_exists($rowStatus['status'], $counts)) {
        $counts[$rowStatus['status']] = $rowStatus['total'];
    }
    $counts['TOTAL'] += $rowStatus['total'];
}

// Base query
$countQuery = "SELECT COUNT(*) as total FROM barang WHERE 1=1";
$query = "SELECT * FROM barang WHERE 1=1";

$params = [];
$types = '';

// Filter Status
if($status){
    $countQuery .= " AND status = ?";
    $query .= " AND status = ?";
    $params[] = $status;
    $types .= 's';
}

// Pencarian Menyeluruh
if($search){
    $searchCond = " AND (nama LIKE ? OR tipe LIKE ? OR imei LIKE ? OR warna LIKE ? OR internal LIKE ? OR supplier LIKE ? OR keterangan LIKE ?)";
    $countQuery .= $searchCond;
    $query .= $searchCond;
    
    $like = "%$search%";
    for ($i = 0; $i < 7; $i++) {
        $params[] = $like;
        $types .= 's';
    }
}

// 1. Hitung total data
$stmtCount = mysqli_prepare($koneksi, $countQuery);
if(!empty($params)){
    mysqli_stmt_bind_param($stmtCount, $types, ...$params);
}
mysqli_stmt_execute($stmtCount);
$resCount = mysqli_stmt_get_result($stmtCount);
$rowCount = mysqli_fetch_assoc($resCount);
$totalData = $rowCount['total'];
$totalPages = ceil($totalData / $limit);

// 2. Tambahkan Order & LIMIT
$query .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$totalTampil = mysqli_num_rows($result);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Product List · Happy Cellular</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    
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
            --border-color: #E2E8F0;
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

        .logo-area {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-logo {
            width: 32px;
            height: 32px;
            object-fit: contain;
            border-radius: 8px;
        }

        .logo-text {
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--primary-dongker);
            letter-spacing: -0.5px;
        }

        .logo-text small {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-muted);
            display: block;
            margin-top: -2px;
        }

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

        .main-wrapper {
            max-width: 540px;
            margin: 0 auto;
            padding: 1rem 1.2rem;
        }

        .screen-title-area {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0.5rem 0 1.2rem;
        }

        .screen-title {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--text-main);
            margin-bottom: 0;
        }

        .btn-add-action {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: var(--primary-dongker-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-dongker);
            text-decoration: none;
            font-size: 1rem;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-add-action:hover {
            background: var(--primary-dongker);
            color: #FFF;
        }

        .search-container {
            position: relative;
            margin-bottom: 14px;
        }

        .search-container i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .search-input {
            width: 100%;
            height: 46px;
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding-left: 44px;
            padding-right: 16px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.01);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-dongker);
            box-shadow: 0 0 0 3px rgba(30, 74, 122, 0.1);
        }

        .filter-scroll-wrapper {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 5px;
            margin-bottom: 16px;
            scrollbar-width: none;
        }

        .filter-scroll-wrapper::-webkit-scrollbar {
            display: none;
        }

        .filter-pill-label {
            white-space: nowrap;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            background: var(--surface-card);
            cursor: pointer;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .btn-check:checked + .filter-pill-label {
            background: var(--primary-dongker);
            color: #FFFFFF;
            border-color: var(--primary-dongker);
        }

        .product-list-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .product-item-row {
            display: flex;
            align-items: center;
            padding: 14px;
            background: var(--surface-card);
            border-radius: 18px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.01);
            border: 1px solid rgba(0, 0, 0, 0.01);
        }

        .product-item-row:hover {
            border-color: var(--border-color);
            box-shadow: 0 6px 18px rgba(0,0,0,0.04);
        }

        .product-img-box {
            width: 52px;
            height: 52px;
            background: var(--primary-dongker-soft);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6px;
            margin-right: 14px;
            flex-shrink: 0;
            color: var(--primary-dongker);
            overflow: hidden;
        }

        .product-img-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .product-details {
            flex-grow: 1;
            min-width: 0;
            padding-right: 50px; 
        }

        .product-name-text {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-main);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-info-inline {
            font-size: 0.75rem;
            line-height: 1.45;
            margin-bottom: 4px;
            color: var(--text-muted);
        }

        .imei-badge {
            font-family: monospace;
            background: #F1F5F9;
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 600;
            color: var(--text-main);
            display: inline-block;
        }

        .product-sub-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .action-edit-trigger {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--primary-dongker);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 8px;
            background: var(--primary-dongker-soft);
            transition: all 0.2s ease;
        }
        
        .action-edit-trigger:hover {
            background: var(--primary-dongker);
            color: #FFFFFF;
        }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
            padding: 14px;
            background: var(--surface-card);
            border-radius: 16px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.01);
        }

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

        .nav-item i {
            font-size: 1.25rem;
        }

        .nav-item.active {
            color: var(--primary-dongker);
        }

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

        .more-menu-popup a {
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

        .more-menu-popup a i {
            color: var(--primary-dongker);
            font-size: 1rem;
        }

        .more-menu-popup a:hover {
            background: var(--primary-dongker-soft);
        }

        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(2px);
            z-index: 1090;
            display: none;
        }

        .popup-overlay.show {
            display: block;
        }

        .custom-sheet-modal .modal-content {
            border-radius: 24px 24px 0 0 !important;
            border: none;
        }
        
        .modal-drag-indicator {
            width: 40px;
            height: 5px;
            background: #E2E8F0;
            border-radius: 10px;
            margin: 12px auto 0 auto;
        }

        .desktop-sidebar {
            display: none;
        }

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

            .navbar-top .logo-area {
                display: none;
            }

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

            .sidebar-menu {
                display: flex;
                flex-direction: column;
                gap: 5px;
                flex-grow: 1;
            }

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

            .sidebar-link i {
                font-size: 1.1rem;
                width: 20px;
                text-align: center;
            }

            .sidebar-link:hover, .sidebar-link.active {
                background: var(--primary-dongker-soft);
                color: var(--primary-dongker);
            }

            .sidebar-divider {
                height: 1px;
                background: #F1F5F9;
                margin: 10px 0;
            }

            .content-wrapper {
                flex-grow: 1;
                display: flex;
                flex-direction: column;
                min-width: 0;
            }

            .main-wrapper {
                max-width: 1200px;
                width: 100%;
                padding: 1.5rem 2rem;
                margin: 0;
            }

            #filterForm {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 20px;
                margin-top: 5px;
                margin-bottom: 15px;
            }

            .search-container {
                max-width: 380px;
                margin-bottom: 0;
                flex-grow: 1;
            }

            .filter-scroll-wrapper {
                margin-bottom: 0;
                padding-bottom: 0;
            }
            
            .product-list-container {
                display: grid;
                grid-template-columns: repeat(2, 1fr); 
                gap: 16px;
            }
            
            .custom-sheet-modal .modal-dialog {
                position: relative !important;
                margin: 1.75rem auto !important;
                top: 5% !important;
            }
            .custom-sheet-modal .modal-content {
                border-radius: 20px !important;
            }
            .modal-drag-indicator {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="desktop-sidebar">
    <div class="sidebar-brand">
        <img src="asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
        <div class="logo-text">
            Happy Cell
            <small>Product List</small>
        </div>
    </div>
    <div class="sidebar-menu">
        <a href="index.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="stock_in.php" class="sidebar-link"><i class="fas fa-arrow-circle-down" style="color: #10B981;"></i> Stock In</a>
        <a href="admin_stock_out.php" class="sidebar-link"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i> Stock Out</a>
        <a href="return.php" class="sidebar-link"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i> Retur</a>
        
        <div class="sidebar-divider"></div>
        
        <a href="data_master.php" class="sidebar-link"><i class="fas fa-database"></i> Data Master</a>
        <a href="data_barang.php" class="sidebar-link active"><i class="fas fa-box"></i> Data Produk</a>
        <a href="penjualan.php" class="sidebar-link"><i class="fas fa-shopping-cart"></i> Transaksi Jual</a>
        <a href="keuangan.php" class="sidebar-link"><i class="fas fa-chart-pie"></i> Analisis Laba</a>
        <a href="manajemen_label.php" class="sidebar-link"><i class="fas fa-print"></i> Cetak Label</a>
        <a href="tukar_tambah.php" class="sidebar-link"><i class="fas fa-exchange-alt"></i> Tukar Tambah</a>
        <a href="beli_customer.php" class="sidebar-link"><i class="fas fa-hand-holding-usd"></i> Buy Out</a>
        <a href="service_hp.php" class="sidebar-link"><i class="fas fa-tools"></i> Service HP</a>
    </div>
</div>

<div class="content-wrapper">

    <div class="navbar-top">
        <div class="logo-area">
            <img src="asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
            <div class="logo-text">
                Happy Cell
                <small>Product List</small>
            </div>
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
        <div class="screen-title-area">
            <h3 class="screen-title">Product List</h3>
            <a href="stock_in.php" class="btn-add-action">
                <i class="fa-solid fa-plus"></i>
            </a>
        </div>

        <form id="filterForm" method="GET" action="">
            <input type="hidden" name="page" value="1" id="filterPageInput">
            
            <div class="search-container">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search" id="searchInput" class="search-input" placeholder="Cari produk..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="filter-scroll-wrapper">
                <input type="radio" class="btn-check" name="status" value="" id="statusAll" <?= $status == '' ? 'checked' : '' ?>>
                <label class="filter-pill-label" for="statusAll">All Products (<?= $counts['TOTAL'] ?>)</label>

                <input type="radio" class="btn-check" name="status" value="READY" id="statusReady" <?= $status == 'READY' ? 'checked' : '' ?>>
                <label class="filter-pill-label" for="statusReady">Ready (<?= $counts['READY'] ?>)</label>

                <input type="radio" class="btn-check" name="status" value="NO READY" id="statusNoReady" <?= $status == 'NO READY' ? 'checked' : '' ?>>
                <label class="filter-pill-label" for="statusNoReady">Out of Stock (<?= $counts['NO READY'] ?>)</label>

                <input type="radio" class="btn-check" name="status" value="RETURN_SUPPLIER" id="statusReturn" <?= $status == 'RETURN_SUPPLIER' ? 'checked' : '' ?>>
                <label class="filter-pill-label" for="statusReturn">Returned (<?= $counts['RETURN_SUPPLIER'] ?>)</label>
            </div>
        </form>

        <main class="product-list-container">
            <?php if($totalTampil > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): 
                    $dataDetail = [
                        'id' => $row['id'],
                        'nama' => $row['nama'],
                        'tipe' => $row['tipe'],
                        'warna' => $row['warna'],
                        'internal' => $row['internal'],
                        'supplier' => $row['supplier'],
                        'keterangan' => $row['keterangan'] ?? '',
                        'modal' => number_format($row['modal'], 0, ',', '.'),
                        'harga_jual' => number_format($row['harga_jual'], 0, ',', '.'),
                        'tanggal' => date('d M Y', strtotime($row['tanggal'])),
                        'updated_at' => !empty($row['updated_at']) ? date('d M Y H:i', strtotime($row['updated_at'])) : '-',
                        'imei' => $row['imei'],
                        'status' => $row['status']
                    ];
                    $json = htmlspecialchars(json_encode($dataDetail), ENT_QUOTES, 'UTF-8');
                    $supplierColor = getSupplierColor($row['supplier']);
                ?>
                
                <div class="product-item-row" data-detail='<?= $json ?>' onclick="showDetail(this)">
                    <div class="product-img-box">
                        <?php if (!empty($row['gambar'])): ?>
                            <?php 
                                $imageData = base64_encode($row['gambar']);
                                echo '<img src="data:image/jpeg;base64,'.$imageData.'" alt="Product Image">';
                            ?>
                        <?php else: ?>
                            <i class="fas fa-mobile-screen fa-lg text-muted"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-details">
                        <div class="product-name-text"><?= htmlspecialchars($row['nama']) ?></div>
                        
                        <div class="product-info-inline">
                            <div class="fw-bold text-dark mb-1">
                                <?= htmlspecialchars($row['tipe']) ?> · <?= htmlspecialchars($row['warna']) ?> · <?= htmlspecialchars($row['internal']) ?>GB
                            </div>
                            <div class="mb-1">
                                IMEI: <span class="imei-badge"><?= htmlspecialchars($row['imei']) ?></span>
                            </div>
                            <div class="d-inline-flex align-items-center mb-1">
                                <span class="badge rounded-pill text-white px-2 py-1" style="background-color: <?= $supplierColor ?>; font-size:0.7rem;">
                                    <i class="fa-solid fa-truck-ramp-box me-1"></i> <?= htmlspecialchars($row['supplier']) ?>
                                </span>
                            </div>
                            <?php if(!empty($row['keterangan'])): ?>
                                <div class="text-truncate text-warning-emphasis bg-warning-subtle px-2 py-0.5 rounded-1 d-inline-block mt-1" style="max-width: 100%; font-size: 0.7rem; font-weight:600;">
                                    <i class="fa-solid fa-note-sticky me-1"></i> <?= htmlspecialchars($row['keterangan']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-sub-meta">
                            <span style="color: var(--primary-dongker)">Rp <?= number_format($row['harga_jual'], 0, ',', '.') ?></span>
                            <span class="text-muted">•</span>
                            <span class="stock-count-indicator">
                                <?php 
                                    if($row['status'] === 'READY') echo '<span class="text-success">Ready</span>';
                                    elseif($row['status'] === 'NO READY') echo '<span class="text-danger">Sold Out</span>';
                                    else echo '<span class="text-warning">Returned</span>';
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <span class="action-edit-trigger" 
                        onclick="event.stopPropagation(); openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['tipe'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['warna'], ENT_QUOTES) ?>', '<?= $row['internal'] ?>', '<?= htmlspecialchars($row['supplier'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['imei'], ENT_QUOTES) ?>', '<?= $row['modal'] ?>', '<?= $row['harga_jual'] ?>', '<?= $row['status'] ?>', '<?= htmlspecialchars($row['keterangan'] ?? '', ENT_QUOTES) ?>')">
                        Edit
                    </span>
                </div>

                <?php endwhile; ?>

            <?php else: ?>
                <div class="text-center py-5 w-100" style="grid-column: span 2;">
                    <i class="fa-solid fa-box-open fa-2x mb-2 text-light d-block"></i>
                    <h6 class="text-muted fw-bold">Tidak ada produk ditemukan</h6>
                    <p class="text-muted small mb-0">Coba ubah filter atau kata kunci pencarian Anda.</p>
                </div>
            <?php endif; ?>
        </main>

        <?php if($totalPages > 1): ?>
            <div class="pagination-container">
                <button class="btn btn-sm btn-outline-dark rounded-3 px-3" 
                        onclick="pindahHalaman(<?= $page - 1 ?>)" <?= ($page <= 1) ? 'disabled' : '' ?>>
                    <i class="fas fa-chevron-left me-1"></i> Prev
                </button>
                <span class="small fw-bold text-muted">Hal <?= $page ?> dari <?= $totalPages ?></span>
                <button class="btn btn-sm btn-outline-dark rounded-3 px-3" 
                        onclick="pindahHalaman(<?= $page + 1 ?>)" <?= ($page >= $totalPages) ? 'disabled' : '' ?>>
                    Next <i class="fas fa-chevron-right ms-1"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<nav class="bottom-nav">
    <a href="index.php" class="nav-item">
        <i class="fas fa-th-large"></i>
        <span>Dashboard</span>
    </a>
    <a href="stock_in.php" class="nav-item">
        <i class="fas fa-arrow-circle-down" style="color: #10B981;"></i>
        <span>Stock In</span>
    </a>
    <a href="admin_stock_out.php" class="nav-item">
        <i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i>
        <span>Stock Out</span>
    </a>
    <a href="return.php" class="nav-item">
        <i class="fas fa-rotate-left" style="color: #F59E0B;"></i>
        <span>Retur</span>
    </a>
    <div class="nav-item active" id="moreMenuBtn" role="button">
        <i class="fas fa-bars"></i>
        <span>Menu</span>
    </div>
</nav>

<div class="more-menu-popup" id="moreMenuPopup">
    <a href="data_master.php"><i class="fas fa-database"></i> Data Master</a>
    <a href="data_barang.php"><i class="fas fa-box"></i> Data Produk</a>
    <a href="penjualan.php"><i class="fas fa-shopping-cart"></i> Transaksi Jual</a>
    <a href="keuangan.php"><i class="fas fa-chart-pie"></i> Analisis Laba</a>
    <a href="manajemen_label.php"><i class="fas fa-print"></i> Cetak Label</a>
    <a href="tukar_tambah.php"><i class="fas fa-exchange-alt"></i> Tukar Tambah</a>
    <a href="service_hp.php"><i class="fas fa-tools"></i> Service HP</a>
    <a href="beli_customer.php"><i class="fas fa-tools"></i> Buy Out</a>
</div>

<div class="popup-overlay" id="popupOverlay"></div>

<div class="modal fade custom-sheet-modal" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable fixed-bottom m-0 max-width-none" style="max-width: 480px; margin: 0 auto !important;">
        <div class="modal-content shadow-lg">
            <div class="modal-drag-indicator"></div>
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold m-0" style="font-size:1.1rem;">Detail Inventaris</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div id="modalImage" class="mx-auto rounded-4 bg-light d-flex align-items-center justify-content-center p-3 mb-3" style="width:110px; height:110px;"></div>
                    <span id="modalStatusBadge" class="badge px-3 py-2 rounded-pill mb-2"></span>
                    <h4 id="modalNama" class="fw-extrabold text-dark mb-1" style="font-size:1.2rem; font-weight:800;"></h4>
                    <h5 id="modalHargaJual" class="fw-bold" style="color: var(--primary-dongker)"></h5>
                </div>

                <div class="card p-3 border-0 bg-light rounded-4 mb-3">
                    <div class="row g-3 style-info-row" style="font-size: 0.85rem;">
                        <div class="col-6"><label class="small text-muted d-block mb-1">Tipe</label><span id="modalTipe" class="fw-bold text-dark"></span></div>
                        <div class="col-6"><label class="small text-muted d-block mb-1">Warna</label><span id="modalWarna" class="fw-bold text-dark"></span></div>
                        <div class="col-6"><label class="small text-muted d-block mb-1">Penyimpanan</label><span id="modalInternal" class="fw-bold text-dark"></span></div>
                        <div class="col-6"><label class="small text-muted d-block mb-1">Tanggal Masuk</label><span id="modalTanggal" class="fw-bold text-dark"></span></div>
                        <div class="col-6"><label class="small text-muted d-block mb-1">Supplier</label><span id="modalSupplier" class="fw-bold text-dark"></span></div>
                        <div class="col-6"><label class="small text-muted d-block mb-1">Harga Modal</label><span id="modalHarga" class="fw-bold text-dark"></span></div>
                        <div class="col-12"><label class="small text-muted d-block mb-1">Terakhir Diperbarui</label><span id="modalUpdatedAt" class="fw-bold text-dark"></span></div>
                        <div class="col-12" id="keteranganContainer" style="display:none;"><label class="small text-muted d-block mb-1">Keterangan</label><span id="modalKeterangan" class="fw-bold text-dark"></span></div>
                    </div>
                </div>

                <div class="p-3 bg-white border border-dashed rounded-4 text-center mb-4">
                    <svg id="barcodeSvg" style="max-width: 100%; height: 50px;"></svg>
                    <div id="modalImei" class="mt-2 font-monospace fw-bold text-secondary small"></div>
                </div>

                <button class="btn btn-dark w-100 rounded-4 py-3 fw-bold shadow-sm mb-2" style="background: var(--primary-dongker); border:none;" onclick="cetakLabel()">
                    <i class="fas fa-print me-2"></i> Cetak Label Inventaris
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDIT BARANG -->
<div class="modal fade custom-sheet-modal" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable fixed-bottom m-0" style="max-width: 520px; margin: 0 auto !important;">
        <div class="modal-content shadow-lg">
            <div class="modal-drag-indicator"></div>
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold m-0" style="font-size:1.1rem;">
                    <i class="fas fa-edit me-2" style="color: var(--primary-dongker);"></i> Edit Produk
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="editForm" method="POST" action="update_barang.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="editId">
                    
                    <!-- Info IMEI (tidak bisa diedit) -->
                    <div class="text-center mb-3 p-3 bg-light rounded-4">
                        <div class="small text-muted mb-1">IMEI (Identifier)</div>
                        <div class="font-monospace fw-bold text-dark" id="editImeiDisplay" style="font-size:1.1rem;"></div>
                        <input type="hidden" name="imei" id="editImei">
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="small fw-bold text-muted mb-1">Nama Produk</label>
                            <input type="text" name="nama" id="editNama" class="form-control form-control-lg fw-bold" required>
                        </div>
                        
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Tipe</label>
                            <input type="text" name="tipe" id="editTipe" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Warna</label>
                            <input type="text" name="warna" id="editWarna" class="form-control" required>
                        </div>
                        
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Internal (GB)</label>
                            <input type="number" name="internal" id="editInternal" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Status</label>
                            <select name="status" id="editStatus" class="form-select" required>
                                <option value="READY">Ready</option>
                                <option value="NO READY">No Ready</option>
                                <option value="RETURN_SUPPLIER">Returned</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="small fw-bold text-muted mb-1">Supplier</label>
                            <input type="text" name="supplier" id="editSupplier" class="form-control" required>
                        </div>
                        
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Harga Modal (Rp)</label>
                            <input type="number" name="modal" id="editModal" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Harga Jual (Rp)</label>
                            <input type="number" name="harga_jual" id="editHargaJual" class="form-control" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="small fw-bold text-muted mb-1">Keterangan (Opsional)</label>
                            <textarea name="keterangan" id="editKeterangan" class="form-control" rows="2" placeholder="Catatan tambahan..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-3 px-4 fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn text-white rounded-3 px-4 fw-bold" style="background: var(--primary-dongker);">
                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentData = {};
const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));

let searchTimeout;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        document.getElementById('filterPageInput').value = 1;
        document.getElementById('filterForm').submit();
    }, 600);
});

document.querySelectorAll('input[name="status"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('filterPageInput').value = 1;
        document.getElementById('filterForm').submit();
    });
});

function pindahHalaman(angkaHalaman) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', angkaHalaman);
    window.location.search = urlParams.toString();
}

function showDetail(card) {
    const data = JSON.parse(card.getAttribute('data-detail'));
    currentData = data;

    const imgContainer = document.getElementById('modalImage');
    imgContainer.innerHTML = '';
    const imgEl = document.createElement('img');
    imgEl.src = '../api/gambar.php?id=' + data.id;
    imgEl.style.maxWidth = '100%';
    imgEl.style.maxHeight = '100%';
    imgEl.style.objectFit = 'contain';
    imgEl.onerror = function() {
        imgContainer.innerHTML = '<i class="fas fa-mobile-screen fa-2x text-muted"></i>';
    };
    imgContainer.appendChild(imgEl);

    document.getElementById('modalNama').textContent = data.nama;
    document.getElementById('modalTipe').textContent = data.tipe;
    document.getElementById('modalWarna').textContent = data.warna;
    document.getElementById('modalInternal').textContent = data.internal + ' GB';
    document.getElementById('modalSupplier').textContent = data.supplier;
    document.getElementById('modalHarga').textContent = 'Rp ' + data.modal;
    document.getElementById('modalHargaJual').textContent = 'Rp ' + data.harga_jual;
    document.getElementById('modalTanggal').textContent = data.tanggal;
    document.getElementById('modalUpdatedAt').textContent = data.updated_at;
    document.getElementById('modalImei').textContent = data.imei;

    if(data.keterangan) {
        document.getElementById('modalKeterangan').textContent = data.keterangan;
        document.getElementById('keteranganContainer').style.display = 'block';
    } else {
        document.getElementById('keteranganContainer').style.display = 'none';
    }

    const badge = document.getElementById('modalStatusBadge');
    badge.textContent = data.status;
    if(data.status === 'READY') {
        badge.className = 'badge bg-success-subtle text-success px-3 py-2';
    } else if(data.status === 'NO READY') {
        badge.className = 'badge bg-danger-subtle text-danger px-3 py-2';
    } else {
        badge.className = 'badge bg-warning-subtle text-warning px-3 py-2';
    }

    try {
        JsBarcode("#barcodeSvg", data.imei, { format: "CODE128", width: 2, height: 50, displayValue: false, lineColor: "#111827" });
    } catch(e) {}

    detailModal.show();
}

function cetakLabel() {
    if(!currentData.imei) return;
    const w = window.open('', '_blank', 'width=450,height=600');
    w.document.write('<html><head><title>Label - ' + currentData.nama + '</title>');
    w.document.write('<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\/script>');
    w.document.write('<style>body { font-family: "Plus Jakarta Sans", sans-serif; padding: 20px; } .label-box { width: 320px; border: 1px solid #000; padding: 15px; border-radius: 12px; text-align: center; background: white; } h2 { margin: 0 0 10px 0; font-size: 18px; } .imei { font-family: monospace; font-weight: bold; font-size: 16px; margin: 10px 0; } .specs { font-size: 12px; margin-bottom: 10px; border-top: 1px solid #eee; padding-top: 10px; }</style>');
    w.document.write('</head><body>');
    w.document.write('<div class="label-box"><div style="font-size: 10px; text-transform: uppercase; color: #4E6AF3;">Happy Cellular Inventory</div>');
    w.document.write('<h2>' + currentData.nama + '</h2>');
    w.document.write('<div class="specs">' + currentData.tipe + ' | ' + currentData.warna + ' | ' + currentData.internal + 'GB</div>');
    w.document.write('<svg id="pBarcode"></svg>');
    w.document.write('<div class="imei">' + currentData.imei + '</div>');
    w.document.write('<div style="font-size: 10px;">' + currentData.tanggal + '</div></div>');
    w.document.write('<script>JsBarcode("#pBarcode", "' + currentData.imei + '", { format: "CODE128", width: 1.5, height: 40, displayValue: false }); setTimeout(() => { window.print(); window.close(); }, 500);<\/script>');
    w.document.write('</body></html>');
    w.document.close();
}

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

const editModal = new bootstrap.Modal(document.getElementById('editModal'));

function openEditModal(id, nama, tipe, warna, internal, supplier, imei, modal, hargaJual, status, keterangan) {
    document.getElementById('editId').value = id;
    document.getElementById('editNama').value = nama;
    document.getElementById('editTipe').value = tipe;
    document.getElementById('editWarna').value = warna;
    document.getElementById('editInternal').value = internal;
    document.getElementById('editSupplier').value = supplier;
    document.getElementById('editImei').value = imei;
    document.getElementById('editImeiDisplay').textContent = imei;
    document.getElementById('editModal').value = modal;
    document.getElementById('editHargaJual').value = hargaJual;
    document.getElementById('editStatus').value = status;
    document.getElementById('editKeterangan').value = keterangan;
    
    editModal.show();
}
</script>
</body>
</html>