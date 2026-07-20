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

$where = "WHERE DATE(tanggal_return) BETWEEN '" . mysqli_real_escape_string($koneksi, $start_date) . "' 
                               AND '" . mysqli_real_escape_string($koneksi, $end_date) . "'";

if (!empty($search)) {
    $s = mysqli_real_escape_string($koneksi, $search);
    $where .= " AND (imei LIKE '%$s%' OR nama LIKE '%$s%' OR tipe LIKE '%$s%' OR supplier LIKE '%$s%' OR warna LIKE '%$s%')";
}

// =========================================================================
// QUERY SUMMARY
// =========================================================================
$q_summary = "SELECT 
                COUNT(*) as total_return,
                COUNT(DISTINCT imei) as total_imei_unik
              FROM return_supplier
              $where";

$res_summary = mysqli_query($koneksi, $q_summary);
$summary = mysqli_fetch_assoc($res_summary);
$total_return = (int)($summary['total_return'] ?? 0);
$total_imei_unik = (int)($summary['total_imei_unik'] ?? 0);

// =========================================================================
// QUERY LIST RIWAYAT
// =========================================================================
$q_list = "SELECT * FROM return_supplier $where ORDER BY tanggal_return DESC";
$res_list = mysqli_query($koneksi, $q_list);

$grouped_data = [];
if ($res_list && mysqli_num_rows($res_list) > 0) {
    while ($row = mysqli_fetch_assoc($res_list)) {
        $tgl = date('Y-m-d', strtotime($row['tanggal_return']));
        $grouped_data[$tgl][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Riwayat Return Supplier · Happy Cellular</title>
    <link rel="shortcut icon" href="../asset/Happy.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            --warning-orange-soft: #FEF3C7;
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

        /* NAVBAR & SIDEBAR */
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
            display: flex; align-items: center; gap: 6px; 
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

        /* MAIN WRAPPER */
        .main-wrapper { max-width: 1200px; margin: 0 auto; padding: 1rem 1.2rem; }

        /* PAGE HEADER */
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
            background: var(--warning-orange-soft);
            color: var(--warning-orange);
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
        .btn-back:hover {
            background: #fde68a;
            color: #b45309;
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
        .summary-card.total::before { background: var(--warning-orange); }
        .summary-card.imei::before { background: var(--accent-blue); }

        .summary-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        .summary-value {
            font-size: 1.1rem;
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
        .filter-card .form-control {
            border-radius: 10px;
            border: 1px solid #E2E8F0;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 8px 12px;
        }
        .btn-filter {
            background: var(--warning-orange);
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
            text-decoration: none;
            display: block;
            text-align: center;
        }

        /* DATE GROUP */
        .date-group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 16px 0 10px;
            padding: 8px 14px;
            background: var(--warning-orange-soft);
            border-radius: 10px;
            border-left: 4px solid var(--warning-orange);
        }
        .date-group-header h6 {
            font-weight: 800;
            font-size: 0.85rem;
            color: #b45309;
            margin: 0;
        }
        .date-group-header .badge {
            background: var(--warning-orange);
            color: white;
            font-weight: 700;
            font-size: 0.7rem;
            padding: 5px 10px;
            border-radius: 20px;
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
        .table-responsive-stack table { min-width: 800px; width: max-content; }

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
        .badge-return {
            background: rgba(245,158,11,0.1);
            color: var(--warning-orange);
        }
        .btn-detail {
            border-radius: 8px; padding: 0.4rem 0.7rem; font-size: 0.75rem;
            font-weight: 600; border: none; cursor: pointer; transition: all 0.2s;
            background: rgba(245,158,11,0.1); color: var(--warning-orange);
        }
        .btn-detail:hover { background: rgba(245,158,11,0.2); }

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

        /* MODAL DETAIL */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .modal-header {
            border-bottom: 1px solid #F1F5F9;
            padding: 1.2rem 1.5rem;
            background: linear-gradient(135deg, var(--warning-orange-soft) 0%, #fff 100%);
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
            box-shadow: 0 -4px 20px rgba(0,0,0,0.08); border-top: 1px solid #EDF2F7; z-index: 1050;
            padding-bottom: env(safe-area-inset-bottom, 0);
        }
        .nav-item {
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
            text-decoration: none; color: #A0AEC0; font-size: 0.7rem; font-weight: 600; flex: 1; height: 100%; cursor: pointer;
        }
        .nav-item i { font-size: 1.25rem; }
        .nav-item.active { color: var(--primary-dongker); }

        /* DESKTOP LAYOUT */
        .desktop-sidebar { display: none; }
        @media (min-width: 992px) {
            body { padding-bottom: 0; display: flex; background: #F8FAFC; }
            .bottom-nav, .popup-overlay, .more-menu-popup { display: none !important; }
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
            .main-wrapper { max-width: 1200px; width: 100%; padding: 1.5rem 2rem; margin: 0; }
            .summary-grid { grid-template-columns: repeat(4, 1fr); }
        }

        @media (max-width: 575.98px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .summary-value { font-size: 0.95rem; }
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
        <a href="admin_stock_out.php" class="sidebar-link"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i> Stock Out</a>
        <a href="return.php" class="sidebar-link active"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i> Retur</a>
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
        <!-- PAGE HEADER -->
        <div class="page-header">
            <h2><i class="fas fa-history me-2" style="color: var(--warning-orange);"></i>Riwayat Return Supplier</h2>
            <a href="return.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>

        <!-- SUMMARY CARDS -->
        <div class="summary-grid">
            <div class="summary-card total">
                <div class="summary-icon" style="background: var(--warning-orange-soft); color: var(--warning-orange);">
                    <i class="fas fa-undo-alt"></i>
                </div>
                <div class="summary-value"><?= number_format($total_return, 0, ',', '.') ?></div>
                <div class="summary-label">Total Return</div>
            </div>
            <div class="summary-card imei">
                <div class="summary-icon" style="background: rgba(59,130,246,0.1); color: var(--accent-blue);">
                    <i class="fas fa-barcode"></i>
                </div>
                <div class="summary-value"><?= number_format($total_imei_unik, 0, ',', '.') ?></div>
                <div class="summary-label">IMEI Unik</div>
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
                        <label class="form-label">Cari (IMEI/Nama/Supplier)</label>
                        <input type="text" name="search" class="form-control" placeholder="Kata kunci..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-6 col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn-filter"><i class="fas fa-filter me-1"></i> Filter</button>
                    </div>
                    <div class="col-6 col-md-3 d-flex align-items-end">
                        <a href="riwayat_return.php" class="btn-reset"><i class="fas fa-redo me-1"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- LIST RIWAYAT (TABEL) -->
        <?php if (!empty($grouped_data)): ?>
            <?php foreach ($grouped_data as $tanggal => $items): 
                $tgl_display = date('d M Y', strtotime($tanggal));
            ?>
                <div class="date-group-header">
                    <h6><i class="far fa-calendar-alt me-2"></i><?= $tgl_display ?></h6>
                    <span class="badge"><?= count($items) ?> Return</span>
                </div>

                <div class="table-card">
                    <div class="table-responsive-stack">
                        <table class="table table-borderless align-middle">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>IMEI</th>
                                    <th>Nama Barang</th>
                                    <th>Supplier</th>
                                    <th>Warna</th>
                                    <th>Internal</th>
                                    <th>Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $r): 
                                    $waktu = date('H:i', strtotime($r['tanggal_return']));
                                    $nama_lengkap = trim(($r['nama'] ?? '') . ' ' . ($r['tipe'] ?? ''));
                                ?>
                                    <tr>
                                        <td><?= $waktu ?></td>
                                        <td style="font-family: monospace;"><?= htmlspecialchars($r['imei']) ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($nama_lengkap ?: 'Barang Return') ?></td>
                                        <td><?= htmlspecialchars($r['supplier'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($r['warna'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($r['internal'] ?? '-') ?></td>
                                        <td>
                                            <button class="btn-detail" onclick='showDetail(<?= json_encode([
                                                'id' => $r['id'],
                                                'imei' => $r['imei'],
                                                'nama' => $r['nama'] ?? '-',
                                                'tipe' => $r['tipe'] ?? '-',
                                                'warna' => $r['warna'] ?? '-',
                                                'internal' => $r['internal'] ?? '-',
                                                'supplier' => $r['supplier'] ?? '-',
                                                'tanggal' => date('d M Y H:i', strtotime($r['tanggal_return']))
                                            ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
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
            <div class="empty-state">
                <i class="fas fa-box-open d-block"></i>
                <div style="font-size: 0.9rem; font-weight: 600;">Tidak ada riwayat return</div>
                <div style="font-size: 0.8rem; margin-top: 4px;">Coba ubah filter tanggal atau kata kunci pencarian</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL DETAIL -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-undo-alt me-2" style="color: var(--warning-orange);"></i>Detail Return</h5>
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
    <a href="admin_stock_out.php" class="nav-item"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i><span>Stock Out</span></a>
    <a href="return.php" class="nav-item active"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i><span>Retur</span></a>
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
    
    let html = `
        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-box" style="color: var(--warning-orange);"></i> Detail Barang</div>
            <div class="detail-card">
                <div class="detail-row"><span class="label">Nama</span><span class="value">${data.nama}</span></div>
                <div class="detail-row"><span class="label">Tipe</span><span class="value">${data.tipe || '-'}</span></div>
                <div class="detail-row"><span class="label">Warna</span><span class="value">${data.warna || '-'}</span></div>
                <div class="detail-row"><span class="label">Internal</span><span class="value">${data.internal || '-'}</span></div>
                <div class="detail-row"><span class="label">IMEI</span><span class="value" style="font-family: monospace;">${data.imei}</span></div>
            </div>
        </div>

        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-truck" style="color: var(--accent-blue);"></i> Info Return</div>
            <div class="detail-card">
                <div class="detail-row"><span class="label">Supplier</span><span class="value">${data.supplier || '-'}</span></div>
                <div class="detail-row"><span class="label">Tanggal Return</span><span class="value">${data.tanggal}</span></div>
                <div class="detail-row"><span class="label">ID Return</span><span class="value">#${data.id}</span></div>
            </div>
        </div>

        <div style="background: var(--warning-orange-soft); border: 1px solid var(--warning-orange); border-radius: 10px; padding: 12px; text-align: center; color: #b45309; font-size: 0.85rem;">
            <i class="fas fa-info-circle me-1"></i>
            <strong>Barang ini sudah tidak tersedia di stok</strong>
        </div>
    `;

    modalBody.innerHTML = html;
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}
</script>
</body>
</html>