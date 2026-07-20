<?php
session_start();
require_once '../api/auth_helper.php';
restrictTo(['admin']);
include '../connection.php';

// =========================================================================
// FILTER PARAMETER
// =========================================================================
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$search     = trim($_GET['search'] ?? '');
$metode     = $_GET['metode'] ?? '';

// =========================================================================
// BUILD WHERE CLAUSE
// =========================================================================
$where = "WHERE DATE(tanggal) BETWEEN '" . mysqli_real_escape_string($koneksi, $start_date) . "' 
                             AND '" . mysqli_real_escape_string($koneksi, $end_date) . "'";

if (!empty($search)) {
    $s = mysqli_real_escape_string($koneksi, $search);
    $where .= " AND (imei LIKE '%$s%' OR nama_barang LIKE '%$s%' OR tipe LIKE '%$s%' OR nama_customer LIKE '%$s%' OR no_hp LIKE '%$s%')";
}

if (!empty($metode)) {
    $m = mysqli_real_escape_string($koneksi, $metode);
    $where .= " AND metode_bayar_ke_customer = '$m'";
}

// =========================================================================
// QUERY SUMMARY
// =========================================================================
$q_summary = "SELECT 
                COUNT(*) as total_trx,
                COALESCE(SUM(harga_beli), 0) as total_dana_bayar,
                COALESCE(SUM(harga_jual_estimasi), 0) as total_estimasi_jual,
                COALESCE(SUM(harga_jual_estimasi - harga_beli), 0) as total_estimasi_margin
              FROM pembelian_customer
              $where";

$res_summary = mysqli_query($koneksi, $q_summary);
$summary = mysqli_fetch_assoc($res_summary);

$total_trx           = (int)($summary['total_trx'] ?? 0);
$total_dana_bayar    = (int)($summary['total_dana_bayar'] ?? 0);
$total_estimasi_jual = (int)($summary['total_estimasi_jual'] ?? 0);
$total_estimasi_margin = (int)($summary['total_estimasi_margin'] ?? 0);

// =========================================================================
// QUERY LIST RIWAYAT
// =========================================================================
$q_list = "SELECT * FROM pembelian_customer $where ORDER BY tanggal DESC";
$res_list = mysqli_query($koneksi, $q_list);

$grouped_data = [];
if ($res_list && mysqli_num_rows($res_list) > 0) {
    while ($row = mysqli_fetch_assoc($res_list)) {
        $tgl = date('Y-m-d', strtotime($row['tanggal']));
        $grouped_data[$tgl][] = $row;
    }
}

// =========================================================================
// AMBIL LIST METODE BAYAR (untuk filter)
// =========================================================================
$metodeList = [];
$q_metode = mysqli_query($koneksi, "SELECT DISTINCT metode_bayar_ke_customer FROM pembelian_customer WHERE metode_bayar_ke_customer IS NOT NULL ORDER BY metode_bayar_ke_customer");
while ($m = mysqli_fetch_assoc($q_metode)) {
    if (!empty(trim($m['metode_bayar_ke_customer']))) {
        $metodeList[] = trim($m['metode_bayar_ke_customer']);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Riwayat Beli dari Customer · Happy Cellular</title>
    <link rel="shortcut icon" href="../asset/Happy.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-body: #F4F7FA;
            --surface-card: #FFFFFF;
            --primary-dongker: #1E4A7A;
            --primary-dongker-soft: #EEF4FA;
            --text-main: #1E293B;
            --text-muted: #64748B;
            --success-green: #10B981;
            --warning-orange: #F59E0B;
            --purple-asset: #8B5CF6;
            --danger-red: #EF4444;
            --accent-blue: #3B82F6;
            --bottom-nav-height: 72px;
            --sidebar-width: 260px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
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
        }
        .user-badge:after {
            display: inline-block; margin-left: 0.255em; vertical-align: 0.255em;
            content: ""; border-top: 0.3em solid; border-right: 0.3em solid transparent;
            border-bottom: 0; border-left: 0.3em solid transparent;
        }
        .dropdown-menu-custom {
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(30,41,59,0.1);
            border: 1px solid rgba(0,0,0,0.05);
            padding: 8px;
            z-index: 1020 !important;
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
        .dropdown-menu-custom .dropdown-item i { color: var(--primary-dongker); font-size: 0.95rem; width: 16px; text-align: center; }
        .dropdown-menu-custom .dropdown-item:hover { background: var(--primary-dongker-soft); color: var(--primary-dongker); }

        .main-wrapper { max-width: 1200px; margin: 0 auto; padding: 1rem 1.2rem; }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .page-header h2 { font-size: 1.2rem; font-weight: 800; margin: 0; }
        .btn-back {
            background: rgba(139,92,246,0.1);
            color: var(--purple-asset);
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-back:hover { background: rgba(139,92,246,0.2); color: var(--purple-asset); }

        /* SUMMARY CARDS */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 1.2rem;
        }
        .summary-card {
            background: var(--surface-card);
            border-radius: 14px;
            padding: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.03);
            position: relative;
            overflow: hidden;
        }
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px; height: 100%;
        }
        .summary-card.trx::before { background: var(--purple-asset); }
        .summary-card.dana::before { background: var(--danger-red); }
        .summary-card.est::before { background: var(--success-green); }
        .summary-card.margin::before { background: var(--warning-orange); }

        .summary-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        .summary-value {
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 2px;
        }
        .summary-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* FILTER CARD */
        .filter-card {
            background: var(--surface-card);
            border-radius: 16px;
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            margin-bottom: 1rem;
        }
        .filter-card .form-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        .filter-card .form-control,
        .filter-card .form-select {
            border-radius: 10px;
            border: 1px solid #E2E8F0;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 8px 12px;
        }

        /* DATE GROUP */
        .date-group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 16px 0 10px;
            padding: 8px 14px;
            background: rgba(139,92,246,0.08);
            border-radius: 10px;
            border-left: 4px solid var(--purple-asset);
        }
        .date-group-header h6 {
            font-weight: 800;
            font-size: 0.85rem;
            color: var(--purple-asset);
            margin: 0;
        }
        .date-group-header .badge {
            background: var(--purple-asset);
            color: white;
            font-weight: 700;
            font-size: 0.7rem;
            padding: 5px 10px;
            border-radius: 20px;
        }

        /* HISTORY CARD */
        .history-card {
            background: var(--surface-card);
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.03);
            border-left: 4px solid var(--purple-asset);
            cursor: pointer;
            transition: all 0.2s;
        }
        .history-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.06);
        }
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
        }
        .history-title { flex: 1; min-width: 0; }
        .history-title-main {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }
        .history-title-sub {
            font-size: 0.7rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .history-price {
            text-align: right;
            flex-shrink: 0;
        }
        .history-price-value {
            font-weight: 800;
            font-size: 0.9rem;
            color: var(--danger-red);
        }
        .history-price-label {
            font-size: 0.65rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .history-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            border-top: 1px dashed #E2E8F0;
            font-size: 0.7rem;
        }
        .history-meta-left {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .badge-custom {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.65rem;
            font-weight: 700;
        }
        .badge-cash { background: rgba(59,130,246,0.1); color: var(--accent-blue); }
        .badge-transfer { background: rgba(59,130,246,0.1); color: var(--accent-blue); }
        .badge-ewallet { background: rgba(139,92,246,0.1); color: var(--purple-asset); }

        .history-time {
            color: var(--text-muted);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
            background: var(--surface-card);
            border-radius: 16px;
        }
        .empty-state i {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 12px;
        }

        /* MODAL */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .modal-header {
            border-bottom: 1px solid #F1F5F9;
            padding: 1.2rem 1.5rem;
            background: linear-gradient(135deg, rgba(139,92,246,0.1) 0%, #fff 100%);
            border-radius: 20px 20px 0 0;
        }
        .modal-body { padding: 1.5rem; }

        .detail-section { margin-bottom: 18px; }
        .detail-section-title {
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .detail-card {
            background: #F8FAFC;
            border-radius: 12px;
            padding: 14px;
            border: 1px solid #E2E8F0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 7px 0;
            font-size: 0.8rem;
            border-bottom: 1px dashed #E2E8F0;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-row .label { color: var(--text-muted); font-weight: 500; }
        .detail-row .value { font-weight: 700; color: var(--text-main); text-align: right; max-width: 60%; word-break: break-word; }

        /* POPUP MENU (MOBILE) */
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
            transition: all 0.2s;
        }
        .more-menu-popup.show { display: grid; transform: translateX(-50%) scale(1); opacity: 1; }
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
        }
        .more-menu-popup a i { color: var(--primary-dongker); font-size: 1rem; }
        .more-menu-popup a:hover { background: var(--primary-dongker-soft); }

        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(2px);
            z-index: 1090;
            display: none;
        }
        .popup-overlay.show { display: block; }

        /* BOTTOM NAV */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: var(--bottom-nav-height); background: var(--surface-card);
            display: flex; justify-content: space-around; align-items: center;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.08); border-top: 1px solid #EDF2F7; z-index: 1050;
            padding-bottom: env(safe-area-inset-bottom, 0);
        }
        .nav-item {
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
            text-decoration: none; color: #A0AEC0; font-size: 0.7rem; font-weight: 600; flex: 1; height: 100%;
            cursor: pointer;
        }
        .nav-item i { font-size: 1.25rem; }
        .nav-item.active { color: var(--primary-dongker); }

        /* DESKTOP SIDEBAR */
        .desktop-sidebar { display: none; }
        @media (min-width: 992px) {
            body { padding-bottom: 0; display: flex; background: #F8FAFC; }
            .bottom-nav { display: none !important; }
            .more-menu-popup, .popup-overlay { display: none !important; }
            .navbar-top { position: static; box-shadow: none; background: transparent; padding: 1.5rem 2rem 0; width: 100%; }
            .navbar-top .logo-area { display: none; }
            .desktop-sidebar {
                display: flex; flex-direction: column; width: var(--sidebar-width); background: var(--surface-card);
                height: 100vh; position: sticky; top: 0; border-right: 1px solid #E2E8F0; padding: 1.5rem; z-index: 1010;
            }
            .sidebar-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #F1F5F9; }
            .sidebar-menu { display: flex; flex-direction: column; gap: 5px; flex-grow: 1; }
            .sidebar-link {
                display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-muted);
                text-decoration: none; font-weight: 600; font-size: 0.85rem; border-radius: 12px; transition: all 0.2s;
            }
            .sidebar-link i { font-size: 1.1rem; width: 20px; text-align: center; }
            .sidebar-link:hover, .sidebar-link.active { background: var(--primary-dongker-soft); color: var(--primary-dongker); }
            .sidebar-divider { height: 1px; background: #F1F5F9; margin: 10px 0; }
            .content-wrapper { flex-grow: 1; display: flex; flex-direction: column; min-width: 0; }
            .main-wrapper { max-width: 1200px; padding: 1.5rem 2rem; }
            .summary-grid { grid-template-columns: repeat(4, 1fr); }
        }

        @media (max-width: 575.98px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .summary-value { font-size: 0.9rem; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR DESKTOP -->
<div class="desktop-sidebar">
    <div class="sidebar-brand">
        <img src="asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
        <div class="logo-text">Happy Cell<small>Admin Panel</small></div>
    </div>
    <div class="sidebar-menu">
        <a href="index.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
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
        <a href="beli_customer.php" class="sidebar-link active"><i class="fas fa-hand-holding-usd" style="color: #8B5CF6;"></i> Buy Out</a>
        <a href="service_hp.php" class="sidebar-link"><i class="fas fa-tools"></i> Service HP</a>
    </div>
</div>

<div class="content-wrapper">
    <!-- NAVBAR -->
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
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
            </ul>
        </div>
    </div>

    <div class="main-wrapper">
        <!-- PAGE HEADER -->
        <div class="page-header">
            <h2><i class="fas fa-history me-2" style="color: var(--purple-asset);"></i>Riwayat Beli dari Customer</h2>
            <a href="beli_customer.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>

        <!-- SUMMARY CARDS -->
        <div class="summary-grid">
            <div class="summary-card trx">
                <div class="summary-icon" style="background: rgba(139,92,246,0.1); color: var(--purple-asset);">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="summary-value"><?= number_format($total_trx, 0, ',', '.') ?></div>
                <div class="summary-label">Total Transaksi</div>
            </div>
            <div class="summary-card dana">
                <div class="summary-icon" style="background: rgba(239,68,68,0.1); color: var(--danger-red);">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="summary-value" style="color: var(--danger-red);">Rp <?= number_format($total_dana_bayar, 0, ',', '.') ?></div>
                <div class="summary-label">Total Dana Dibayar</div>
            </div>
            <div class="summary-card est">
                <div class="summary-icon" style="background: rgba(16,185,129,0.1); color: var(--success-green);">
                    <i class="fas fa-tag"></i>
                </div>
                <div class="summary-value" style="color: var(--success-green);">Rp <?= number_format($total_estimasi_jual, 0, ',', '.') ?></div>
                <div class="summary-label">Total Estimasi Jual</div>
            </div>
            <div class="summary-card margin">
                <div class="summary-icon" style="background: rgba(245,158,11,0.1); color: var(--warning-orange);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="summary-value" style="color: <?= $total_estimasi_margin < 0 ? 'var(--danger-red)' : 'var(--warning-orange)' ?>;">Rp <?= number_format($total_estimasi_margin, 0, ',', '.') ?></div>
                <div class="summary-label">Total Estimasi Margin</div>
            </div>
        </div>

        <!-- FILTER -->
        <div class="filter-card">
            <form method="GET">
                <div class="row g-2">
                    <div class="col-6 col-md-3">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Tanggal Akhir</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Cari (IMEI/Nama/Customer)</label>
                        <input type="text" name="search" class="form-control" placeholder="Kata kunci..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Metode Bayar</label>
                        <select name="metode" class="form-select">
                            <option value="">Semua</option>
                            <?php foreach ($metodeList as $mt): ?>
                                <option value="<?= htmlspecialchars($mt) ?>" <?= $metode === $mt ? 'selected' : '' ?>><?= htmlspecialchars($mt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn w-100" style="background: var(--purple-asset); color: white; font-weight: 700; padding: 8px;">
                            <i class="fas fa-filter"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- LIST RIWAYAT -->
        <?php if (!empty($grouped_data)): ?>
            <?php foreach ($grouped_data as $tanggal => $items): 
                $tgl_display = date('d M Y', strtotime($tanggal));
                $subtotal = array_sum(array_column($items, 'harga_beli'));
            ?>
                <div class="date-group-header">
                    <h6><i class="far fa-calendar-alt me-2"></i><?= $tgl_display ?></h6>
                    <span class="badge"><?= count($items) ?> Transaksi · Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                </div>

                <?php foreach ($items as $row): 
                    $waktu = date('H:i', strtotime($row['tanggal']));
                    $nama_lengkap = trim(($row['nama_barang'] ?? '') . ' ' . ($row['tipe'] ?? ''));
                    $metode_bayar = $row['metode_bayar_ke_customer'] ?? 'Cash';
                    
                    if (stripos($metode_bayar, 'transfer') !== false) {
                        $badge_class = 'badge-transfer';
                    } elseif (stripos($metode_bayar, 'e-wallet') !== false || stripos($metode_bayar, 'ovo') !== false || stripos($metode_bayar, 'dana') !== false || stripos($metode_bayar, 'gopay') !== false) {
                        $badge_class = 'badge-ewallet';
                    } else {
                        $badge_class = 'badge-cash';
                    }
                ?>
                    <div class="history-card" onclick='showDetail(<?= json_encode([
                        'id' => $row['id'],
                        'imei' => $row['imei'],
                        'merk' => '',
                        'nama_barang' => $row['nama_barang'] ?? '-',
                        'tipe' => $row['tipe'] ?? '-',
                        'warna' => $row['warna'] ?? '-',
                        'internal' => $row['internal'] ?? '-',
                        'kondisi' => $row['kondisi'] ?? '-',
                        'harga_beli' => (int)($row['harga_beli'] ?? 0),
                        'harga_jual_est' => (int)($row['harga_jual_estimasi'] ?? 0),
                        'metode_bayar' => $metode_bayar,
                        'nama_customer' => $row['nama_customer'] ?? '-',
                        'no_hp' => $row['no_hp'] ?? '-',
                        'catatan' => $row['catatan'] ?? '-',
                        'tanggal' => date('d M Y H:i', strtotime($row['tanggal'])),
                        'user' => $row['user'] ?? '-'
                    ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                        <div class="history-header">
                            <div class="history-title">
                                <div class="history-title-main"><?= htmlspecialchars($nama_lengkap ?: 'HP') ?></div>
                                <div class="history-title-sub">
                                    <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($row['nama_customer'] ?? '-') ?></span>
                                    <span>•</span>
                                    <span style="font-family: monospace;"><?= htmlspecialchars($row['imei']) ?></span>
                                </div>
                            </div>
                            <div class="history-price">
                                <div class="history-price-value">Rp <?= number_format((int)$row['harga_beli'], 0, ',', '.') ?></div>
                                <div class="history-price-label">Dana Dibayar</div>
                            </div>
                        </div>
                        <div class="history-meta">
                            <div class="history-meta-left">
                                <span class="badge-custom <?= $badge_class ?>">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <?= htmlspecialchars($metode_bayar) ?>
                                </span>
                                <?php if (!empty($row['kondisi'])): ?>
                                    <span class="badge-custom" style="background: rgba(139,92,246,0.1); color: var(--purple-asset);">
                                        <i class="fas fa-info-circle"></i>
                                        <?= htmlspecialchars($row['kondisi']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="history-time">
                                <i class="far fa-clock"></i> <?= $waktu ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox d-block"></i>
                <div style="font-size: 0.9rem; font-weight: 600;">Tidak ada riwayat pembelian</div>
                <div style="font-size: 0.8rem; margin-top: 4px;">Coba ubah filter tanggal atau kata kunci pencarian</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL DETAIL -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-receipt me-2" style="color: var(--purple-asset);"></i>Detail Pembelian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBodyContent"></div>
        </div>
    </div>
</div>

<!-- BOTTOM NAV (MOBILE) -->
<nav class="bottom-nav">
    <a href="index.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a href="stock_in.php" class="nav-item"><i class="fas fa-arrow-circle-down" style="color: #10B981;"></i><span>Stock In</span></a>
    <a href="admin_stock_out.php" class="nav-item"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i><span>Stock Out</span></a>
    <a href="return.php" class="nav-item"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i><span>Retur</span></a>
    <div class="nav-item active" id="moreMenuBtn" role="button"><i class="fas fa-bars"></i><span>Menu</span></div>
</nav>

<!-- POPUP MENU OVERLAY -->
<div class="more-menu-popup" id="moreMenuPopup">
    <a href="data_master.php"><i class="fas fa-database"></i> Data Master</a>
    <a href="data_barang.php"><i class="fas fa-box"></i> Data Produk</a>
    <a href="penjualan.php"><i class="fas fa-shopping-cart"></i> Transaksi Jual</a>
    <a href="keuangan.php"><i class="fas fa-chart-pie"></i> Analisis Laba</a>
    <a href="manajemen_label.php"><i class="fas fa-print"></i> Cetak Label</a>
    <a href="tukar_tambah.php"><i class="fas fa-exchange-alt"></i> Tukar Tambah</a>
    <a href="beli_customer.php"><i class="fas fa-hand-holding-usd"></i> Buy Out</a>
    <a href="service_hp.php"><i class="fas fa-tools"></i> Service HP</a>
</div>
<div class="popup-overlay" id="popupOverlay"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ==========================================
// POPUP MENU MOBILE
// ==========================================
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

// ==========================================
// MODAL DETAIL
// ==========================================
function showDetail(data) {
    const modalBody = document.getElementById('modalBodyContent');
    const margin = data.harga_jual_est - data.harga_beli;
    const marginPct = data.harga_beli > 0 ? ((margin / data.harga_beli) * 100).toFixed(1) : 0;
    
    let html = `
        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-mobile-alt" style="color: var(--purple-asset);"></i> Detail HP</div>
            <div class="detail-card">
                <div class="detail-row"><span class="label">Nama Barang</span><span class="value">${data.nama_barang}</span></div>
                <div class="detail-row"><span class="label">Tipe/Varian</span><span class="value">${data.tipe || '-'}</span></div>
                <div class="detail-row"><span class="label">Warna</span><span class="value">${data.warna || '-'}</span></div>
                <div class="detail-row"><span class="label">Internal</span><span class="value">${data.internal || '-'} GB</span></div>
                <div class="detail-row"><span class="label">Kondisi</span><span class="value">${data.kondisi || '-'}</span></div>
                <div class="detail-row"><span class="label">IMEI</span><span class="value" style="font-family: monospace;">${data.imei}</span></div>
            </div>
        </div>

        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-user" style="color: var(--accent-blue);"></i> Data Customer</div>
            <div class="detail-card">
                <div class="detail-row"><span class="label">Nama</span><span class="value">${data.nama_customer}</span></div>
                <div class="detail-row"><span class="label">No. HP</span><span class="value">${data.no_hp || '-'}</span></div>
            </div>
        </div>

        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-money-bill-wave" style="color: var(--success-green);"></i> Informasi Harga</div>
            <div class="detail-card">
                <div class="detail-row">
                    <span class="label">Harga Beli (Dana Dibayar)</span>
                    <span class="value" style="color: var(--danger-red); font-weight: 800;">Rp ${data.harga_beli.toLocaleString('id-ID')}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Estimasi Harga Jual</span>
                    <span class="value" style="color: var(--success-green); font-weight: 800;">Rp ${data.harga_jual_est.toLocaleString('id-ID')}</span>
                </div>
                <div class="detail-row" style="border-top: 2px solid #E2E8F0; padding-top: 10px; margin-top: 4px;">
                    <span class="label" style="font-weight: 800;"><i class="fas fa-chart-line me-1"></i> Estimasi Margin</span>
                    <span class="value" style="color: ${margin >= 0 ? 'var(--success-green)' : 'var(--danger-red)'}; font-size: 1rem; font-weight: 800;">
                        Rp ${margin.toLocaleString('id-ID')} (${marginPct}%)
                    </span>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-info-circle" style="color: var(--warning-orange);"></i> Informasi Transaksi</div>
            <div class="detail-card">
                <div class="detail-row"><span class="label">Metode Bayar</span><span class="value">${data.metode_bayar}</span></div>
                <div class="detail-row"><span class="label">Tanggal</span><span class="value">${data.tanggal}</span></div>
                <div class="detail-row"><span class="label">Petugas</span><span class="value">${data.user}</span></div>
                <div class="detail-row"><span class="label">Catatan</span><span class="value">${data.catatan || '-'}</span></div>
            </div>
        </div>
    `;

    modalBody.innerHTML = html;
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}
</script>
</body>
</html>