<?php
require_once '../api/auth_helper.php';
restrictTo(['admin']);
include '../connection.php';

// ==================== PASTIKAN KOLOM STATUS CETAK ADA ====================
function ensureKolomCetak($koneksi) {
    $check = mysqli_query($koneksi, "SHOW COLUMNS FROM barang LIKE 'label_dicetak'");
    if ($check && mysqli_num_rows($check) == 0) {
        mysqli_query($koneksi, "ALTER TABLE barang ADD COLUMN label_dicetak TINYINT(1) NOT NULL DEFAULT 0");
        mysqli_query($koneksi, "ALTER TABLE barang ADD COLUMN label_dicetak_at DATETIME NULL");
    }
}
ensureKolomCetak($koneksi);

// ==================== FILTER PRODUK ====================
$filterStatus = $_GET['status'] ?? 'READY';
$filterTipe   = $_GET['tipe'] ?? 'SEMUA';
$filterCetak  = $_GET['cetak'] ?? 'BELUM'; // Default diubah menjadi BELUM dicetak
$filterStatusSafe = mysqli_real_escape_string($koneksi, $filterStatus);
$filterTipeSafe   = mysqli_real_escape_string($koneksi, $filterTipe);

// Daftar status unik (untuk dropdown filter)
$statusList = [];
$qStatus = mysqli_query($koneksi, "SELECT DISTINCT status FROM barang WHERE status IS NOT NULL AND status <> '' ORDER BY status ASC");
if ($qStatus) { while ($s = mysqli_fetch_assoc($qStatus)) { $statusList[] = $s['status']; } }

// Daftar tipe unik (dipakai sebagai filter kategori produk)
$tipeList = [];
$qTipe = mysqli_query($koneksi, "SELECT DISTINCT tipe FROM barang WHERE tipe IS NOT NULL AND tipe <> '' ORDER BY tipe ASC");
if ($qTipe) { while ($t = mysqli_fetch_assoc($qTipe)) { $tipeList[] = $t['tipe']; } }

// Bangun query sesuai filter yang dipilih
$whereParts = [];
if ($filterStatusSafe !== '' && $filterStatusSafe !== 'SEMUA') {
    $whereParts[] = "status = '$filterStatusSafe'";
}
if ($filterTipeSafe !== '' && $filterTipeSafe !== 'SEMUA') {
    $whereParts[] = "tipe = '$filterTipeSafe'";
}
if ($filterCetak === 'SUDAH') {
    $whereParts[] = "label_dicetak = 1";
} elseif ($filterCetak === 'BELUM') {
    $whereParts[] = "label_dicetak = 0";
}
$whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cetak Label · Happy Cellular</title>
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
            --warning-orange: #F59E0B;
            --danger-red: #EF4444;
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
        .main-wrapper { max-width: 1200px; margin: 0 auto; padding: 1.5rem; }
        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1.5rem 0 1rem;
        }
        .section-heading h3 { font-weight: 800; font-size: 1.1rem; margin-bottom: 0; }
        .section-heading span { font-size: 0.75rem; font-weight: 600; background: var(--primary-dongker-soft); padding: 4px 12px; border-radius: 20px; color: var(--primary-dongker); }
        .card-main {
            background: var(--surface-card);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.01);
            margin-bottom: 1.5rem;
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        .filter-bar select {
            border-radius: 30px;
            border: 1px solid #E2E8F0;
            padding: 0.4rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-main);
            background: #F8FAFC;
        }
        .search-box {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 40px;
            padding: 0.6rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            max-width: 320px;
        }
        .search-box i { color: var(--text-muted); }
        .search-box input {
            background: transparent;
            border: none;
            outline: none;
            width: 100%;
            font-size: 0.9rem;
        }
        .selection-info {
            background: rgba(30, 74, 122, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--primary-dongker);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table thead th {
            background: #F8FAFD;
            color: #5c6f87;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #E2E8F0;
            padding: 1rem 0.8rem;
            white-space: nowrap;
        }
        .data-table tbody td {
            padding: 1rem 0.8rem;
            font-weight: 500;
            color: #2d3e5f;
            border-bottom: 1px solid #EFF2F9;
        }
        .imei-code {
            font-family: monospace;
            background: #f0f4fa;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--primary-dongker);
            white-space: nowrap;
        }
        .badge-status {
            font-weight: 600;
            font-size: 0.7rem;
            padding: 4px 10px;
            border-radius: 30px;
            display: inline-block;
        }
        .badge-ready { background: #d1fae5; color: #065f46; }
        .badge-return { background: #fee2e2; color: #991b1b; }
        .badge-other { background: #e2e8f0; color: #334155; }
        .badge-printed { background: #DBEAFE; color: #1E40AF; }
        .badge-unprinted { background: #FEF3C7; color: #92400E; }
        .checkbox-custom {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-dongker);
            cursor: pointer;
        }
        .qty-input {
            width: 64px;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 6px 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
        }
        .btn-print {
            background: var(--primary-dongker);
            border: none;
            border-radius: 40px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            color: white;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-print:hover {
            background: #0e3355;
            transform: translateY(-1px);
        }
        .btn-print:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-preview {
            background: #fff;
            border: 1px solid var(--primary-dongker);
            border-radius: 40px;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            color: var(--primary-dongker);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-preview:hover { background: var(--primary-dongker-soft); }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
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
            box-shadow: 0 -4px 20px rgba(0,0,0,0.08);
            border-top: 1px solid #EDF2F7;
            z-index: 1050;
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
        }
        .nav-item i { font-size: 1.25rem; }
        .nav-item.active { color: var(--primary-dongker); }
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
        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(2px);
            z-index: 1090;
            display: none;
        }
        .popup-overlay.show { display: block; }
        .desktop-sidebar { display: none; }
        @media (min-width: 992px) {
            body { padding-bottom: 0; display: flex; background: #F8FAFC; }
            .bottom-nav, .popup-overlay, .more-menu-popup { display: none !important; }
            .navbar-top { position: static; box-shadow: none; background: transparent; padding: 1.5rem 2rem 0; width: 100%; }
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
            .main-wrapper { padding: 1.5rem 2rem; }
        }
        #previewFrame { width: 100%; min-height: 65vh; border: none; background: #e5e5e5; }
    </style>
</head>
<body>

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
        <a href="manajemen_label.php" class="sidebar-link active"><i class="fas fa-print"></i> Cetak Label</a>
        <a href="tukar_tambah.php" class="sidebar-link"><i class="fas fa-exchange-alt"></i> Tukar Tambah</a>
        <a href="beli_customer.php" class="sidebar-link"><i class="fas fa-hand-holding-usd"></i> Buy Out</a>
        <a href="service_hp.php" class="sidebar-link"><i class="fas fa-tools"></i> Service HP</a>
    </div>
</div>

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
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
            </ul>
        </div>
    </div>

    <div class="main-wrapper">
        <div class="card-main">
            <div class="section-heading">
                <h3><i class="fas fa-tags me-2"></i>Cetak Label Harga</h3>
                <span id="countSelected">0</span>
            </div>

            <div class="table-header">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Cari nama, IMEI, atau tipe...">
                </div>

                <div class="filter-bar">
                    <select id="filterStatus" title="Filter status">
                        <option value="SEMUA" <?= $filterStatus === 'SEMUA' ? 'selected' : '' ?>>Semua Status</option>
                        <?php foreach ($statusList as $st): ?>
                        <option value="<?= htmlspecialchars($st) ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="filterTipe" title="Filter tipe/kategori">
                        <option value="SEMUA" <?= $filterTipe === 'SEMUA' ? 'selected' : '' ?>>Semua Tipe</option>
                        <?php foreach ($tipeList as $tp): ?>
                        <option value="<?= htmlspecialchars($tp) ?>" <?= $filterTipe === $tp ? 'selected' : '' ?>><?= htmlspecialchars($tp) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="filterCetak" title="Filter status cetak">
                        <option value="SEMUA" <?= $filterCetak === 'SEMUA' ? 'selected' : '' ?>>Semua (Cetak)</option>
                        <option value="BELUM" <?= $filterCetak === 'BELUM' ? 'selected' : '' ?>>Belum Dicetak</option>
                        <option value="SUDAH" <?= $filterCetak === 'SUDAH' ? 'selected' : '' ?>>Sudah Dicetak</option>
                    </select>
                    <select id="templateSelect" title="Ukuran label">
                        <option value="50x20" selected>Kecil (50×20mm)</option>
                        <option value="40x30">Sedang (40×30mm)</option>
                        <option value="58x40">Besar (58×40mm) - Resi</option>
                        <option value="30x20">Mini (30×20mm)</option>
                    </select>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn-preview" type="button" onclick="previewSelected()">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                    <button class="btn-print" type="button" onclick="printSelected()">
                        <i class="fas fa-print"></i> Cetak Label
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="5%"><input type="checkbox" id="selectAll" class="checkbox-custom"></th>
                            <th>Barang</th>
                            <th>IMEI</th>
                            <th>Tanggal Input</th>
                            <th>Spesifikasi</th>
                            <th>Status</th>
                            <th>Cetak</th>
                            <th width="90">Qty</th>
                        </tr>
                    </thead>
                    <tbody id="productTable">
                        <?php
                        $query = "SELECT id, nama, tipe, imei, warna, internal, status, label_dicetak, label_dicetak_at, tanggal FROM barang $whereSql ORDER BY id DESC";
                        $result = mysqli_query($koneksi, $query);
                        if($result && mysqli_num_rows($result) > 0):
                            while($row = mysqli_fetch_assoc($result)):
                                $badgeClass = 'badge-other';
                                if($row['status'] === 'READY') $badgeClass = 'badge-ready';
                                elseif($row['status'] === 'RETURN_SUPPLIER') $badgeClass = 'badge-return';
                                $sudahCetak = !empty($row['label_dicetak']);
                        ?>
                        <tr data-id="<?= $row['id'] ?>">
                            <td><input type="checkbox" class="item-checkbox checkbox-custom" value="<?= $row['id'] ?>"></td>
                            <td>
                                <strong><?= htmlspecialchars($row['nama']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($row['tipe']) ?></small>
                            </td>
                            <td><span class="imei-code"><?= htmlspecialchars($row['imei']) ?></span></td>
                            <td style="font-size:0.8rem; color:var(--text-muted);">
                                <?= date('d M Y', strtotime($row['tanggal'])) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($row['warna']) ?> / 
                                <span style="color: var(--text-muted);"><?= htmlspecialchars($row['internal']) ?></span>
                            </td>
                            <td><span class="badge-status <?= $badgeClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                            <td>
                                <?php if ($sudahCetak): ?>
                                <span class="badge-status badge-printed" title="<?= htmlspecialchars($row['label_dicetak_at'] ?? '') ?>">
                                    <i class="fas fa-check-circle"></i> Sudah
                                </span>
                                <?php else: ?>
                                <span class="badge-status badge-unprinted">
                                    <i class="fas fa-circle-notch"></i> Belum
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="number" class="qty-input" value="1" min="1" max="99" onclick="event.stopPropagation()">
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-box-open fa-3x mb-3 d-block opacity-25"></i>
                                    <h6>Belum ada barang untuk filter ini</h6>
                                    <p class="text-muted small">Coba ubah filter status, tipe, atau status cetak di atas.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="previewModalEl" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius:20px; overflow:hidden;">
      <div class="modal-header">
        <h6 class="modal-title mb-0"><i class="fas fa-eye me-2"></i>Preview Label</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="previewFrame" src="about:blank"></iframe>
      </div>
    </div>
  </div>
</div>

<nav class="bottom-nav">
    <a href="index.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
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
    <a href="beli_customer.php"><i class="fas fa-hand-holding-usd"></i> Buy Out</a>
    <a href="service_hp.php"><i class="fas fa-tools"></i> Service HP</a>
</div>
<div class="popup-overlay" id="popupOverlay"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ==================== FILTER PRODUK (reload halaman dengan query string) ====================
    function applyFilter() {
        const status = document.getElementById('filterStatus').value;
        const tipe = document.getElementById('filterTipe').value;
        const cetak = document.getElementById('filterCetak').value;
        const params = new URLSearchParams();
        params.set('status', status);
        params.set('tipe', tipe);
        params.set('cetak', cetak);
        window.location.href = 'manajemen_label.php?' + params.toString();
    }
    document.getElementById('filterStatus').addEventListener('change', applyFilter);
    document.getElementById('filterTipe').addEventListener('change', applyFilter);
    document.getElementById('filterCetak').addEventListener('change', applyFilter);

    // ==================== Checkbox & Qty Logic ====================
    const selectAll = document.getElementById('selectAll');
    const countSpan = document.getElementById('countSelected');
    const checkboxes = () => document.querySelectorAll('.item-checkbox');

    function updateCount() {
        const checked = document.querySelectorAll('.item-checkbox:checked').length;
        countSpan.textContent = checked;
    }

    selectAll.addEventListener('change', function() {
        checkboxes().forEach(cb => cb.checked = this.checked);
        updateCount();
    });

    document.getElementById('productTable').addEventListener('change', function(e) {
        if(e.target.classList.contains('item-checkbox')) {
            const all = checkboxes();
            selectAll.checked = Array.from(all).every(cb => cb.checked);
            updateCount();
        }
    });

    // Ambil id + qty dari baris yang dicentang -> ["5:2","8:1",...]
    function getSelectedWithQty() {
        const rows = [];
        document.querySelectorAll('#productTable tr[data-id]').forEach(row => {
            const cb = row.querySelector('.item-checkbox');
            if (cb && cb.checked) {
                const qtyInput = row.querySelector('.qty-input');
                let qty = parseInt(qtyInput ? qtyInput.value : '1', 10);
                if (isNaN(qty) || qty < 1) qty = 1;
                if (qty > 99) qty = 99;
                rows.push(cb.value + ':' + qty);
            }
        });
        return rows;
    }

    function buildPrintUrl(extra) {
        const selected = getSelectedWithQty();
        if (selected.length === 0) {
            alert('Pilih minimal satu barang untuk dicetak!');
            return null;
        }
        const template = document.getElementById('templateSelect').value;
        const params = new URLSearchParams();
        params.set('ids', selected.join(','));
        params.set('template', template);
        if (extra) {
            Object.keys(extra).forEach(k => params.set(k, extra[k]));
        }
        return 'print_massal.php?' + params.toString();
    }

    // ==================== Cetak (via hidden iframe) ====================
    function printSelected() {
        const url = buildPrintUrl();
        if (!url) return;

        const btn = document.querySelector('.btn-print');
        if (btn) btn.disabled = true;

        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.name = 'printFrame_' + Date.now();
        iframe.src = url;
        document.body.appendChild(iframe);

        function cleanup() {
            window.removeEventListener('message', onMessage);
            if (iframe.parentNode) document.body.removeChild(iframe);
            if (btn) btn.disabled = false;
            // refresh agar status cetak otomatis terupdate di layar
            setTimeout(() => window.location.reload(), 500);
        }
        function onMessage(e) {
            if (e.data === 'label-print-done') cleanup();
        }
        window.addEventListener('message', onMessage);
        
        // fallback jika postMessage gagal, tetap bersihkan setelah beberapa detik
        setTimeout(cleanup, 6000);
    }

    // ==================== Preview ====================
    let previewModal;
    function previewSelected() {
        const url = buildPrintUrl({ preview: 1 });
        if (!url) return;
        document.getElementById('previewFrame').src = url;
        if (!previewModal) previewModal = new bootstrap.Modal(document.getElementById('previewModalEl'));
        previewModal.show();
    }

    // Terima pesan dari iframe preview (tombol "Cetak Sekarang" / "Tutup" di dalam print_massal.php)
    window.addEventListener('message', function(e) {
        if (e.data === 'label-preview-confirm-print') {
            if (previewModal) previewModal.hide();
            printSelected();
        } else if (e.data === 'label-preview-close') {
            if (previewModal) previewModal.hide();
        }
    });

    // ==================== Search Filter Produk ====================
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const val = this.value.toLowerCase();
        document.querySelectorAll('#productTable tr[data-id]').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
        });
    });

    // Row click to toggle checkbox
    document.querySelectorAll('#productTable tr[data-id]').forEach(row => {
        row.addEventListener('click', function(e) {
            if(e.target.tagName === 'INPUT') return;
            const cb = this.querySelector('.item-checkbox');
            cb.checked = !cb.checked;
            cb.dispatchEvent(new Event('change'));
        });
    });

    // Update count pada saat halaman dimuat
    window.addEventListener('load', function() {
        updateCount();
    });

    // Mobile Menu Toggle
    const moreBtn = document.getElementById('moreMenuBtn');
    const popup = document.getElementById('moreMenuPopup');
    const overlay = document.getElementById('popupOverlay');
    if(moreBtn) {
        moreBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            popup.classList.toggle('show');
            overlay.classList.toggle('show');
        });
        overlay.addEventListener('click', () => {
            popup.classList.remove('show');
            overlay.classList.remove('show');
        });
        document.addEventListener('click', (e) => {
            if (!popup.contains(e.target) && e.target !== moreBtn && !moreBtn.contains(e.target)) {
                popup.classList.remove('show');
                overlay.classList.remove('show');
            }
        });
    }
</script>
</body>
</html>