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
$status_pencairan = $_GET['status_pencairan'] ?? '';

// =========================================================================
// AMBIL LIST METODE PEMBAYARAN (untuk dropdown filter)
// =========================================================================
$metodeList = [];
$q_metode = mysqli_query($koneksi, "SELECT DISTINCT metode_pembayaran FROM penjualan WHERE metode_pembayaran IS NOT NULL AND metode_pembayaran != '' ORDER BY metode_pembayaran");
while ($m = mysqli_fetch_assoc($q_metode)) {
    $clean = trim(str_replace('(TT)', '', $m['metode_pembayaran']));
    if (!empty($clean) && !in_array($clean, $metodeList)) {
        $metodeList[] = $clean;
    }
}

// =========================================================================
// BUILD WHERE CLAUSE
// =========================================================================
$where = "WHERE DATE(so.tanggal) BETWEEN '" . mysqli_real_escape_string($koneksi, $start_date) . "' 
                               AND '" . mysqli_real_escape_string($koneksi, $end_date) . "'";

if (!empty($search)) {
    $s = mysqli_real_escape_string($koneksi, $search);
    $where .= " AND (so.imei LIKE '%$s%' 
                    OR b.nama LIKE '%$s%' 
                    OR b.tipe LIKE '%$s%' 
                    OR p.nama_customer LIKE '%$s%'
                    OR p.no_hp LIKE '%$s%')";
}

if (!empty($metode)) {
    $m_esc = mysqli_real_escape_string($koneksi, $metode);
    $where .= " AND REPLACE(p.metode_pembayaran, ' (TT)', '') = '$m_esc'";
}

if (!empty($status_pencairan)) {
    $sp_esc = mysqli_real_escape_string($koneksi, $status_pencairan);
    $where .= " AND p.status_pencairan = '$sp_esc'";
}

// =========================================================================
// QUERY SUMMARY
// =========================================================================
$q_summary = "SELECT 
                COUNT(DISTINCT so.id) as total_trx,
                COALESCE(SUM(p.harga_jual), 0) as total_omzet,
                COALESCE(SUM(b.modal), 0) as total_modal
              FROM stock_out so
              LEFT JOIN barang b ON so.imei = b.imei
              LEFT JOIN penjualan p ON so.imei = p.imei
              $where";

$res_summary = mysqli_query($koneksi, $q_summary);
$summary = mysqli_fetch_assoc($res_summary);

$total_trx   = (int)($summary['total_trx'] ?? 0);
$total_omzet = (int)($summary['total_omzet'] ?? 0);
$total_modal = (int)($summary['total_modal'] ?? 0);
$total_laba  = $total_omzet - $total_modal;

// =========================================================================
// QUERY LIST RIWAYAT (GROUPED BY TANGGAL)
// =========================================================================
$q_list = "SELECT 
              so.id AS so_id,
              so.imei,
              so.keterangan AS so_keterangan,
              so.user AS so_user,
              so.tanggal AS so_tanggal,
              so.status AS so_status,
              so.tanggal_return,
              
              b.nama AS b_nama,
              b.tipe AS b_tipe,
              b.warna AS b_warna,
              b.internal AS b_internal,
              b.modal AS b_modal,
              b.harga_jual AS b_harga_jual_db,
              b.status AS b_status_barang,
              
              p.id AS p_id,
              p.harga_jual AS p_harga_jual,
              p.dp AS p_dp,
              p.nama_customer AS p_customer,
              p.no_hp AS p_no_hp,
              p.metode_pembayaran AS p_metode,
              p.status_pencairan AS p_status_pencairan,
              p.tanggal_pencairan AS p_tanggal_pencairan,
              p.jenis_transaksi AS p_jenis
              
           FROM stock_out so
           LEFT JOIN barang b ON so.imei = b.imei
           LEFT JOIN penjualan p ON so.imei = p.imei
           $where
           ORDER BY so.tanggal DESC";

$res_list = mysqli_query($koneksi, $q_list);
$grouped_data = [];
if ($res_list && mysqli_num_rows($res_list) > 0) {
    while ($row = mysqli_fetch_assoc($res_list)) {
        $tgl = date('Y-m-d', strtotime($row['so_tanggal']));
        $grouped_data[$tgl][] = $row;
    }
}

// Helper function
function cleanMetode($metode) {
    if (empty($metode)) return '-';
    return trim(str_replace('(TT)', '', $metode));
}

function isCicilan($metode) {
    $clean = cleanMetode($metode);
    return in_array($clean, ['Kredivo', 'Akulaku', 'Shopee PayLater']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Riwayat Stock Out · Happy Cellular</title>
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
            --danger-red: #EF4444;
            --accent-blue: #3B82F6;
            --purple-asset: #8B5CF6;
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

        .main-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 1.2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .page-header h2 {
            font-size: 1.2rem;
            font-weight: 800;
            margin: 0;
        }
        .btn-back {
            background: var(--primary-dongker-soft);
            color: var(--primary-dongker);
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
        .summary-card.trx::before { background: var(--primary-dongker); }
        .summary-card.omzet::before { background: var(--accent-blue); }
        .summary-card.modal::before { background: var(--warning-orange); }
        .summary-card.laba::before { background: var(--success-green); }

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
        .btn-filter {
            background: var(--primary-dongker);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.8rem;
            width: 100%;
        }
        .btn-reset {
            background: #F1F5F9;
            color: var(--text-muted);
            border: none;
            padding: 10px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.8rem;
            width: 100%;
        }

        /* TABLE CARD */
        .table-card {
            background: var(--surface-card);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.01);
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .table-responsive-stack {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 0;
        }
        .table-responsive-stack table { min-width: 900px; width: max-content; }

        .table thead th {
            background: #F8FAFC; color: var(--text-muted);
            font-weight: 700; font-size: 0.75rem; text-transform: uppercase;
            letter-spacing: 0.5px; border-bottom: 1px solid #E2E8F0;
            padding: 0.9rem 1rem;
        }
        .table tbody td {
            padding: 0.85rem 1rem;
            font-weight: 500; color: var(--text-main);
            vertical-align: middle; border-bottom: 1px solid #F1F5F9;
        }
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:hover { background: #F8FAFC; }

        .badge-custom {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.65rem;
            font-weight: 700;
        }
        .badge-cash { background: rgba(16,185,129,0.1); color: var(--success-green); }
        .badge-transfer { background: rgba(59,130,246,0.1); color: var(--accent-blue); }
        .badge-cicilan { background: rgba(245,158,11,0.1); color: var(--warning-orange); }
        .badge-cair { background: rgba(16,185,129,0.1); color: var(--success-green); }
        .badge-pending { background: rgba(245,158,11,0.1); color: var(--warning-orange); }
        .badge-return { background: rgba(59,130,246,0.1); color: var(--accent-blue); }

        .btn-detail {
            border-radius: 8px; padding: 0.4rem 0.7rem; font-size: 0.75rem;
            font-weight: 600; border: none; cursor: pointer; transition: all 0.2s;
            background: rgba(30,74,122,0.1); color: var(--primary-dongker);
        }
        .btn-detail:hover { background: rgba(30,74,122,0.2); }

        /* DATE GROUP */
        .date-group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 16px 0 10px;
            padding: 8px 14px;
            background: var(--primary-dongker-soft);
            border-radius: 10px;
            border-left: 4px solid var(--primary-dongker);
        }
        .date-group-header h6 {
            font-weight: 800;
            font-size: 0.85rem;
            color: var(--primary-dongker);
            margin: 0;
        }
        .date-group-header .badge {
            background: var(--primary-dongker);
            color: white;
            font-weight: 700;
            font-size: 0.7rem;
            padding: 5px 10px;
            border-radius: 20px;
        }

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
            box-shadow: 0 -4px 20px rgba(0,0,0,0.08); border-top: 1px solid #EDF2F7;
            z-index: 1050;
            padding-bottom: env(safe-area-inset-bottom, 0);
        }
        .nav-item {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 4px; text-decoration: none; color: #A0AEC0; font-size: 0.7rem;
            font-weight: 600; flex: 1; height: 100%; cursor: pointer;
        }
        .nav-item i { font-size: 1.25rem; }
        .nav-item.active { color: var(--primary-dongker); }

        /* MODAL */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .modal-header {
            border-bottom: 1px solid #F1F5F9;
            padding: 1.2rem 1.5rem;
            background: linear-gradient(135deg, var(--primary-dongker-soft) 0%, #fff 100%);
            border-radius: 20px 20px 0 0;
        }
        .modal-body { padding: 1.5rem; }

        .detail-section { margin-bottom: 18px; }
        .detail-section-title {
            font-size: 0.75rem; font-weight: 800; color: var(--text-muted);
            text-transform: uppercase; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;
        }
        .detail-card {
            background: #F8FAFC; border-radius: 12px; padding: 14px; border: 1px solid #E2E8F0;
        }
        .detail-row {
            display: flex; justify-content: space-between; align-items: center; padding: 7px 0;
            font-size: 0.8rem; border-bottom: 1px dashed #E2E8F0;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-row .label { color: var(--text-muted); font-weight: 500; }
        .detail-row .value { font-weight: 700; color: var(--text-main); text-align: right; max-width: 60%; word-break: break-word; }

        /* DESKTOP SIDEBAR */
        .desktop-sidebar { display: none; }

        @media (min-width: 992px) {
            body { padding-bottom: 0; display: flex; background: #F8FAFC; }
            .bottom-nav { display: none !important; }
            .more-menu-popup, .popup-overlay { display: none !important; }
            .navbar-top { position: static; box-shadow: none; background: transparent; padding: 1.5rem 2rem 0; width: 100%; }
            .navbar-top .logo-area { display: none; }
            .desktop-sidebar {
                display: flex; flex-direction: column; width: var(--sidebar-width);
                background: var(--surface-card); height: 100vh; position: sticky;
                top: 0; border-right: 1px solid #E2E8F0; padding: 1.5rem; z-index: 1010;
            }
            .sidebar-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #F1F5F9; }
            .sidebar-menu { display: flex; flex-direction: column; gap: 5px; flex-grow: 1; }
            .sidebar-link {
                display: flex; align-items: center; gap: 12px; padding: 12px 16px;
                color: var(--text-muted); text-decoration: none; font-weight: 600;
                font-size: 0.85rem; border-radius: 12px; transition: all 0.2s;
            }
            .sidebar-link i { font-size: 1.1rem; width: 20px; text-align: center; }
            .sidebar-link:hover, .sidebar-link.active { background: var(--primary-dongker-soft); color: var(--primary-dongker); }
            .sidebar-divider { height: 1px; background: #F1F5F9; margin: 10px 0; }
            .content-wrapper { flex-grow: 1; display: flex; flex-direction: column; min-width: 0; }
            .main-wrapper { max-width: 1400px; width: 100%; padding: 1.5rem 2rem; margin: 0; }
            .summary-grid { grid-template-columns: repeat(4, 1fr); }
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
        <a href="home_admin.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="stock_in.php" class="sidebar-link"><i class="fas fa-arrow-circle-down" style="color: #10B981;"></i> Stock In</a>
        <a href="admin_stock_out.php" class="sidebar-link active"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i> Stock Out</a>
        <a href="return.php" class="sidebar-link"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i> Retur</a>
        <div class="sidebar-divider"></div>
        <a href="data_master.php" class="sidebar-link"><i class="fas fa-database"></i> Data Master</a>
        <a href="data_barang.php" class="sidebar-link"><i class="fas fa-box"></i> Data Produk</a>
        <a href="penjualan.php" class="sidebar-link"><i class="fas fa-shopping-cart"></i> Transaksi Jual</a>
        <a href="keuangan.php" class="sidebar-link"><i class="fas fa-chart-pie"></i> Analisis Laba</a>
        <a href="manajemen_label.php" class="sidebar-link"><i class="fas fa-print"></i> Cetak Label</a>
        <a href="tukar_tambah.php" class="sidebar-link"><i class="fas fa-exchange-alt"></i> Tukar Tambah</a>
        <a href="beli_customer.php" class="sidebar-link"><i class="fas fa-hand-holding-usd"></i> Buy Out</a>
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
        <!-- HEADER -->
        <div class="page-header">
            <h2><i class="fas fa-history me-2" style="color: var(--primary-dongker);"></i>Riwayat Stock Out</h2>
            <a href="admin_stock_out.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>

        <!-- SUMMARY CARDS -->
        <div class="summary-grid">
            <div class="summary-card trx">
                <div class="summary-icon" style="background: var(--primary-dongker-soft); color: var(--primary-dongker);">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="summary-value"><?= number_format($total_trx, 0, ',', '.') ?></div>
                <div class="summary-label">Total Transaksi</div>
            </div>
            <div class="summary-card omzet">
                <div class="summary-icon" style="background: rgba(59,130,246,0.1); color: var(--accent-blue);">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="summary-value">Rp <?= number_format($total_omzet, 0, ',', '.') ?></div>
                <div class="summary-label">Total Omzet</div>
            </div>
            <div class="summary-card modal">
                <div class="summary-icon" style="background: rgba(245,158,11,0.1); color: var(--warning-orange);">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="summary-value">Rp <?= number_format($total_modal, 0, ',', '.') ?></div>
                <div class="summary-label">Total Modal</div>
            </div>
            <div class="summary-card laba">
                <div class="summary-icon" style="background: rgba(16,185,129,0.1); color: var(--success-green);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="summary-value" style="color: <?= $total_laba < 0 ? 'var(--danger-red)' : 'var(--success-green)' ?>;">
                    Rp <?= number_format($total_laba, 0, ',', '.') ?>
                </div>
                <div class="summary-label">Total Laba</div>
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
                    <div class="col-6 col-md-3">
                        <label class="form-label">Metode Pembayaran</label>
                        <select name="metode" class="form-select">
                            <option value="">Semua</option>
                            <?php foreach ($metodeList as $mt): ?>
                                <option value="<?= htmlspecialchars($mt) ?>" <?= $metode === $mt ? 'selected' : '' ?>><?= htmlspecialchars($mt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Status Pencairan</label>
                        <select name="status_pencairan" class="form-select">
                            <option value="">Semua</option>
                            <option value="Cair" <?= $status_pencairan === 'Cair' ? 'selected' : '' ?>>Cair</option>
                            <option value="Pending" <?= $status_pencairan === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn-filter"><i class="fas fa-filter me-1"></i> Filter</button>
                    </div>
                    <div class="col-12 col-md-3 d-flex align-items-end">
                        <a href="riwayat_stock_out.php" class="btn-reset"><i class="fas fa-redo me-1"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- LIST RIWAYAT (TABLE) -->
        <?php if (!empty($grouped_data)): ?>
            <?php foreach ($grouped_data as $tanggal => $items): 
                $tgl_display = date('d M Y', strtotime($tanggal));
                $subtotal = array_sum(array_column($items, 'p_harga_jual'));
            ?>
                <div class="date-group-header">
                    <h6><i class="far fa-calendar-alt me-2"></i><?= $tgl_display ?></h6>
                    <span class="badge"><?= count($items) ?> Transaksi · Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                </div>

                <div class="table-card">
                    <div class="table-responsive-stack">
                        <table class="table table-borderless align-middle">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>IMEI</th>
                                    <th>Nama Barang</th>
                                    <th>Customer</th>
                                    <th>Harga Jual</th>
                                    <th>Metode</th>
                                    <th>Pencairan</th>
                                    <th>Status</th>
                                    <th>Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $r): 
                                    $metode_pembayaran = $r['p_metode'] ?? '';
                                    $metodeClean = cleanMetode($metode_pembayaran);
                                    $isCicilan = isCicilan($metode_pembayaran);
                                    $isReturn = ($r['so_status'] ?? '') === 'RETURN';
                                    
                                    // Badge metode
                                    if ($metodeClean === 'Cash') {
                                        $badgeMetodeClass = 'badge-cash';
                                    } elseif ($metodeClean === 'Transfer') {
                                        $badgeMetodeClass = 'badge-transfer';
                                    } elseif ($isCicilan) {
                                        $badgeMetodeClass = 'badge-cicilan';
                                    } else {
                                        $badgeMetodeClass = 'badge-cash';
                                    }
                                    
                                    // Badge status pencairan
                                    $badgePencairan = '';
                                    if ($isCicilan) {
                                        if (($r['p_status_pencairan'] ?? '') === 'Cair') {
                                            $badgePencairan = '<span class="badge-custom badge-cair"><i class="fas fa-check-circle"></i>Cair</span>';
                                        } else {
                                            $badgePencairan = '<span class="badge-custom badge-pending"><i class="fas fa-hourglass-half"></i>Pending</span>';
                                        }
                                    }
                                    
                                    // Badge status
                                    $badgeStatus = '';
                                    if ($isReturn) {
                                        $badgeStatus = '<span class="badge-custom badge-return"><i class="fas fa-rotate-left"></i>Return</span>';
                                    } else {
                                        $badgeStatus = '<span class="badge-custom" style="background: rgba(239,68,68,0.1); color: var(--danger-red);"><i class="fas fa-arrow-up"></i>OUT</span>';
                                    }

                                    $waktu = date('H:i', strtotime($r['so_tanggal']));
                                    $hargaJual = (int)($r['p_harga_jual'] ?? 0);
                                    $namaBarang = trim(($r['b_nama'] ?? 'Barang Dihapus') . ' ' . ($r['b_tipe'] ?? ''));
                                    
                                    // Data untuk modal
                                    $detail_data = [
                                        'so_id' => $r['so_id'],
                                        'imei' => $r['imei'],
                                        'so_keterangan' => $r['so_keterangan'] ?? '-',
                                        'so_user' => $r['so_user'] ?? '-',
                                        'so_tanggal' => date('d M Y H:i', strtotime($r['so_tanggal'])),
                                        'so_status' => $r['so_status'] ?? '-',
                                        'tanggal_return' => $r['tanggal_return'] ? date('d M Y H:i', strtotime($r['tanggal_return'])) : '-',
                                        
                                        'b_nama' => $r['b_nama'] ?? '-',
                                        'b_tipe' => $r['b_tipe'] ?? '-',
                                        'b_warna' => $r['b_warna'] ?? '-',
                                        'b_internal' => $r['b_internal'] ?? '-',
                                        'b_modal' => (int)($r['b_modal'] ?? 0),
                                        'b_harga_jual_db' => (int)($r['b_harga_jual_db'] ?? 0),
                                        'b_status' => $r['b_status_barang'] ?? '-',
                                        
                                        'p_harga_jual' => (int)($r['p_harga_jual'] ?? 0),
                                        'p_dp' => (int)($r['p_dp'] ?? 0),
                                        'p_customer' => $r['p_customer'] ?? '-',
                                        'p_no_hp' => $r['p_no_hp'] ?? '-',
                                        'p_metode' => $metodeClean,
                                        'p_status_pencairan' => $r['p_status_pencairan'] ?? '-',
                                        'p_tanggal_pencairan' => $r['p_tanggal_pencairan'] ? date('d M Y H:i', strtotime($r['p_tanggal_pencairan'])) : '-',
                                        'p_jenis' => $r['p_jenis'] ?? 'HP'
                                    ];
                                ?>
                                    <tr>
                                        <td><?= $waktu ?></td>
                                        <td style="font-family: monospace;"><?= htmlspecialchars($r['imei']) ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($namaBarang) ?></td>
                                        <td><?= htmlspecialchars($r['p_customer'] ?? 'Umum') ?></td>
                                        <td class="fw-bold">Rp <?= number_format($hargaJual, 0, ',', '.') ?></td>
                                        <td><span class="badge-custom <?= $badgeMetodeClass ?>"><?= htmlspecialchars($metodeClean) ?></span></td>
                                        <td><?= $badgePencairan ?: '-' ?></td>
                                        <td><?= $badgeStatus ?></td>
                                        <td>
                                            <button class="btn-detail" onclick='showDetail(<?= json_encode($detail_data, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                <i class="fas fa-search"></i> Detail
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="table-card text-center py-5 text-muted">
                <i class="fas fa-inbox fa-2x mb-2 d-block" style="color: #CBD5E1;"></i>
                Belum ada riwayat stock out pada periode ini
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL DETAIL -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-receipt me-2" style="color: var(--primary-dongker);"></i>Detail Transaksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBodyContent"></div>
        </div>
    </div>
</div>

<!-- BOTTOM NAV (MOBILE) -->
<nav class="bottom-nav">
    <a href="home_admin.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a href="stock_in.php" class="nav-item"><i class="fas fa-arrow-circle-down" style="color: #10B981;"></i><span>Stock In</span></a>
    <a href="admin_stock_out.php" class="nav-item active"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i><span>Stock Out</span></a>
    <a href="return.php" class="nav-item"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i><span>Retur</span></a>
    <div class="nav-item" id="moreMenuBtn" role="button"><i class="fas fa-bars"></i><span>Menu</span></div>
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
    const laba = data.p_harga_jual - data.b_modal;
    const labaColor = laba < 0 ? '#EF4444' : '#10B981';
    const labaIcon = laba < 0 ? 'fa-arrow-trend-down' : 'fa-arrow-trend-up';
    const labaLabel = laba < 0 ? 'Rugi Bersih' : 'Laba Bersih';

    let html = '';

    // === INFO BARANG ===
    html += `
        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-box" style="color: var(--primary-dongker);"></i> Detail Barang</div>
            <div class="detail-card">
                <div class="detail-row"><span class="label">Nama</span><span class="value">${data.b_nama}</span></div>
                <div class="detail-row"><span class="label">Tipe</span><span class="value">${data.b_tipe}</span></div>
                <div class="detail-row"><span class="label">Warna</span><span class="value">${data.b_warna}</span></div>
                <div class="detail-row"><span class="label">Internal</span><span class="value">${data.b_internal}</span></div>
                <div class="detail-row"><span class="label">IMEI</span><span class="value" style="font-family: monospace;">${data.imei}</span></div>
                <div class="detail-row"><span class="label">Modal</span><span class="value" style="color: var(--warning-orange);">Rp ${data.b_modal.toLocaleString('id-ID')}</span></div>
                <div class="detail-row"><span class="label">Harga Jual (DB)</span><span class="value">Rp ${data.b_harga_jual_db.toLocaleString('id-ID')}</span></div>
                <div class="detail-row"><span class="label">Status Barang</span><span class="value">${data.b_status}</span></div>
            </div>
        </div>
    `;

    // === INFO TRANSAKSI ===
    html += `
        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-shopping-cart" style="color: var(--success-green);"></i> Info Transaksi</div>
            <div class="detail-card">
                <div class="detail-row"><span class="label">ID Transaksi</span><span class="value">#${data.so_id}</span></div>
                <div class="detail-row"><span class="label">Jenis</span><span class="value">${data.p_jenis}</span></div>
                <div class="detail-row"><span class="label">Customer</span><span class="value">${data.p_customer}</span></div>
                <div class="detail-row"><span class="label">No. HP</span><span class="value">${data.p_no_hp}</span></div>
                <div class="detail-row"><span class="label">Metode</span><span class="value">${data.p_metode}</span></div>
                <div class="detail-row"><span class="label">Harga Jual</span><span class="value" style="font-weight: 800; color: var(--primary-dongker);">Rp ${data.p_harga_jual.toLocaleString('id-ID')}</span></div>
                <div class="detail-row"><span class="label">DP</span><span class="value">Rp ${data.p_dp.toLocaleString('id-ID')}</span></div>
                <div class="detail-row"><span class="label">Status Pencairan</span><span class="value">${data.p_status_pencairan}</span></div>
                <div class="detail-row"><span class="label">Tgl Pencairan</span><span class="value">${data.p_tanggal_pencairan}</span></div>
            </div>
        </div>
    `;

    // === INFO STOCK OUT ===
    html += `
        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-arrow-circle-up" style="color: var(--danger-red);"></i> Info Stock Out</div>
            <div class="detail-card">
                <div class="detail-row"><span class="label">Tanggal Keluar</span><span class="value">${data.so_tanggal}</span></div>
                <div class="detail-row"><span class="label">Petugas</span><span class="value">${data.so_user}</span></div>
                <div class="detail-row"><span class="label">Status</span><span class="value">${data.so_status}</span></div>
                <div class="detail-row"><span class="label">Tanggal Return</span><span class="value">${data.tanggal_return}</span></div>
                <div class="detail-row"><span class="label">Keterangan</span><span class="value" style="font-size: 0.75rem;">${data.so_keterangan}</span></div>
            </div>
        </div>
    `;

    // === ANALISIS LABA ===
    html += `
        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-chart-pie" style="color: var(--success-green);"></i> Analisis Laba</div>
            <div class="detail-card" style="background: linear-gradient(135deg, rgba(16,185,129,0.05) 0%, rgba(59,130,246,0.05) 100%);">
                <div class="detail-row"><span class="label">Harga Jual</span><span class="value">Rp ${data.p_harga_jual.toLocaleString('id-ID')}</span></div>
                <div class="detail-row"><span class="label">Modal</span><span class="value" style="color: var(--danger-red);">- Rp ${data.b_modal.toLocaleString('id-ID')}</span></div>
                <div class="detail-row" style="border-top: 2px solid #E2E8F0; padding-top: 10px; margin-top: 4px;">
                    <span class="label" style="font-weight: 800;"><i class="fas ${labaIcon} me-1" style="color: ${labaColor};"></i> ${labaLabel}</span>
                    <span class="value" style="color: ${labaColor}; font-size: 1.1rem; font-weight: 800;">Rp ${laba.toLocaleString('id-ID')}</span>
                </div>
            </div>
        </div>
    `;

    modalBody.innerHTML = html;
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}
</script>
</body>
</html>