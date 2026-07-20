<?php
session_start();
if(!isset($_SESSION['login']) || $_SESSION['level'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

include '../connection.php';

// ==========================================
// LOGIKA NAVIGASI TANGGAL (KIRI & KANAN)
// ==========================================
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$date_obj = new DateTime($selected_date);

// Tombol Kiri (Hari Sebelumnya)
$date_prev = clone $date_obj;
$date_prev->modify('-1 day');
$prev_url_date = $date_prev->format('Y-m-d');

// Tombol Kanan (Hari Berikutnya)
$date_next = clone $date_obj;
$date_next->modify('+1 day');
$next_url_date = $date_next->format('Y-m-d');

// Format Teks Tanggal Indonesia Manual
$hari_array = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
$bulan_array = array(
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
);

$wd = $date_obj->format('w');
$j = $date_obj->format('j');
$n = $date_obj->format('n');
$Y = $date_obj->format('Y');

$formatted_date_text = $hari_array[$wd] . ", " . $j . " " . $bulan_array[$n] . " " . $Y;


// ==========================================
// STATISTIK KEUANGAN BERDASARKAN TANGGAL
// ==========================================
// 1. Total Transaksi (Semua jenis transaksi)
$q_transaksi = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM penjualan WHERE DATE(tanggal) = '$selected_date'");
$total_transaksi = mysqli_fetch_assoc($q_transaksi)['total'] ?? 0;

// 2. Total Omzet Kotor (Total Harga Jual)
$q_omzet = mysqli_query($koneksi, "SELECT SUM(harga_jual) as total FROM penjualan WHERE DATE(tanggal) = '$selected_date'");
$total_omzet = mysqli_fetch_assoc($q_omzet)['total'] ?? 0;

// 3. Total Laba Bersih
$q_laba = mysqli_query($koneksi, "SELECT SUM(
    CASE 
        WHEN p.jenis_transaksi = 'SERVICE' THEN p.harga_jual
        WHEN tt.id IS NOT NULL THEN tt.laba
        ELSE p.harga_jual - COALESCE(b.modal, 0)
    END
) as total 
FROM penjualan p 
LEFT JOIN barang b ON p.imei = b.imei 
LEFT JOIN tukar_tambah tt ON p.imei = tt.imei_baru
WHERE DATE(p.tanggal) = '$selected_date'");
$total_laba = mysqli_fetch_assoc($q_laba)['total'] ?? 0;


// ==========================================
// AMBIL LIST TRANSAKSI DENGAN JOIN
// ==========================================
$query_list = "SELECT p.*, 
               b.nama as nama_barang_asli, b.tipe, b.warna, b.internal, b.modal as modal_asli,
               s.merk as sv_merk, s.model as sv_model, s.kerusakan as sv_kerusakan,
               tt.laba as tt_laba, tt.harga_lama as tt_harga_lama, tt.selisih as tt_selisih,
               p.dp as dp_penjualan
               FROM penjualan p 
               LEFT JOIN barang b ON p.imei = b.imei 
               LEFT JOIN service_hp s ON (p.jenis_transaksi = 'SERVICE' AND p.imei = CONCAT('SVC-', s.id))
               LEFT JOIN tukar_tambah tt ON p.imei = tt.imei_baru
               WHERE DATE(p.tanggal) = '$selected_date' 
               ORDER BY p.id DESC";
$result_list = mysqli_query($koneksi, $query_list);
$total_rows = mysqli_num_rows($result_list);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Transaksi Jual · Happy Cellular</title>
    
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
            --danger-red: #EF4444;
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

        .dropdown-menu-custom .dropdown-item.text-danger { color: #EF4444; }
        .dropdown-menu-custom .dropdown-item.text-danger i { color: #EF4444; }
        .dropdown-menu-custom .dropdown-item.text-danger:hover { background: rgba(239, 68, 68, 0.05); }

        /* MAIN WRAPPER */
        .main-wrapper {
            max-width: 540px;
            margin: 0 auto;
            padding: 1rem 1.2rem;
        }

        /* TANGGAL NAVIGASI BAR */
        .date-navigation-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--surface-card);
            border-radius: 16px;
            padding: 10px 14px;
            margin-bottom: 18px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.01);
            border: 1px solid var(--border-color);
        }

        .btn-date-nav {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: var(--primary-dongker-soft);
            color: var(--primary-dongker);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-date-nav:hover {
            background: var(--primary-dongker);
            color: #FFFFFF;
        }

        .current-date-display { text-align: center; flex-grow: 1; }
        .current-date-display h4 { font-size: 0.9rem; font-weight: 700; color: var(--text-main); margin: 0; text-transform: capitalize; }
        .current-date-display small { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }

        /* METRICS MINI DASHBOARD */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .metric-mini-card {
            background: var(--surface-card);
            padding: 12px 10px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.01);
            box-shadow: 0 4px 14px rgba(0,0,0,0.01);
        }

        .metric-mini-card .m-title { font-size: 0.7rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 4px; }
        .metric-mini-card .m-value { font-size: 0.85rem; font-weight: 800; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .metric-mini-card.active-laba .m-value { color: var(--success-green); }

        /* TRANSACTION LIST ITEM */
        .transaction-container { display: flex; flex-direction: column; gap: 12px; }

        .transaction-card-row {
            display: flex;
            align-items: center;
            padding: 14px;
            background: var(--surface-card);
            border-radius: 18px;
            position: relative;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.01);
            border: 1px solid rgba(0,0,0,0.01);
        }

        .trans-icon-box {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-right: 14px;
            flex-shrink: 0;
        }

        .trans-info-area { flex-grow: 1; min-width: 0; }

        .trans-product-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-main);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .trans-meta-sub { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; margin-bottom: 2px; }

        .trans-imei-code {
            font-family: monospace;
            background: #F1F5F9;
            padding: 1px 5px;
            border-radius: 4px;
            color: var(--text-main);
            font-weight: 600;
        }

        .trans-payment-badge {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            background: var(--primary-dongker-soft);
            color: var(--primary-dongker);
            padding: 2px 8px;
            border-radius: 6px;
        }

        .trans-tt-badge {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            background: rgba(139, 92, 246, 0.1);
            color: #8B5CF6;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .trans-service-badge {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            background: #DBEAFE;
            color: #1E40AF;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .trans-tt-info {
            font-size: 0.7rem;
            color: #8B5CF6;
            font-weight: 600;
            margin-top: 2px;
        }

        .trans-amount-side { text-align: right; flex-shrink: 0; margin-left: 10px; }
        .trans-price-val { font-weight: 700; font-size: 0.9rem; color: var(--text-main); }
        .trans-profit-val { font-size: 0.7rem; font-weight: 600; color: var(--success-green); margin-top: 1px; }

        /* BOTTOM NAVIGATION MOBILE */
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

        /* POPUP MENU LAINNYA MOBILE */
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

        .desktop-sidebar { display: none; }

        /* DESKTOP LAYOUT */
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
            .sidebar-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #F1F5F9; }
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
            .main-wrapper { max-width: 1000px; width: 100%; padding: 1.5rem 2rem; margin: 0; }
            .date-navigation-bar { max-width: 450px; margin-bottom: 24px; }
            .metrics-grid { grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
            .metric-mini-card { padding: 20px 16px; text-align: left; }
            .metric-mini-card .m-title { font-size: 0.75rem; margin-bottom: 6px; }
            .metric-mini-card .m-value { font-size: 1.25rem; }
            .transaction-container { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        }
    </style>
</head>
<body>

<div class="desktop-sidebar">
    <div class="sidebar-brand">
        <img src="asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
        <div class="logo-text">
            Happy Cell
            <small>Admin Panel</small>
        </div>
    </div>
    <div class="sidebar-menu">
        <a href="index.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="stock_in.php" class="sidebar-link"><i class="fas fa-arrow-circle-down" style="color: #10B981;"></i> Stock In</a>
        <a href="admin_stock_out.php" class="sidebar-link"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i> Stock Out</a>
        <a href="return.php" class="sidebar-link"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i> Retur</a>
        
        <div class="sidebar-divider"></div>
        
        <a href="data_master.php" class="sidebar-link"><i class="fas fa-database"></i> Data Master</a>
        <a href="data_barang.php" class="sidebar-link"><i class="fas fa-box"></i> Data Produk</a>
        <a href="penjualan.php" class="sidebar-link active"><i class="fas fa-shopping-cart"></i> Transaksi Jual</a>
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
                <small>Admin Panel</small>
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
        
        <div class="date-navigation-bar">
            <a href="penjualan.php?date=<?= $prev_url_date ?>" class="btn-date-nav" title="Hari Sebelumnya">
                <i class="fas fa-chevron-left"></i>
            </a>
            
            <div class="current-date-display">
                <h4><?= $formatted_date_text ?></h4>
                <small><?= ($selected_date == date('Y-m-d')) ? 'Hari Ini' : 'Arsip Penjualan' ?></small>
            </div>
            
            <a href="penjualan.php?date=<?= $next_url_date ?>" class="btn-date-nav" title="Hari Berikutnya">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>

        <div class="metrics-grid">
            <div class="metric-mini-card">
                <span class="m-title">TRANSAKSI</span>
                <div class="m-value"><?= number_format($total_transaksi, 0, ',', '.') ?> Item</div>
            </div>
            <div class="metric-mini-card">
                <span class="m-title">OMZET KOTOR</span>
                <div class="m-value">Rp <?= number_format($total_omzet, 0, ',', '.') ?></div>
            </div>
            <div class="metric-mini-card active-laba">
                <span class="m-title">LABA BERSIH</span>
                <div class="m-value">Rp <?= number_format($total_laba, 0, ',', '.') ?></div>
            </div>
        </div>

        <h5 class="fw-bold mb-3" style="font-size: 1rem;"><i class="fas fa-list-ul me-2 text-primary"></i>Rincian Unit Terjual & Service</h5>
        
        <main class="transaction-container">
            <?php if($total_rows > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result_list)): 
                    $jenis = $row['jenis_transaksi'] ?? 'HP';
                    $is_service = ($jenis == 'SERVICE');
                    $is_tt = ($row['tt_laba'] !== null);
                    $is_downgrade = ($is_tt && $row['tt_selisih'] < 0); // DETEKSI DOWNGRADE
                    
                    if ($is_service) {
                        $nama_barang = 'Service HP';
                        $subtitle_detail = '';
                        if (!empty($row['sv_merk'])) $subtitle_detail .= $row['sv_merk'] . ' ';
                        if (!empty($row['sv_model'])) $subtitle_detail .= $row['sv_model'];
                        if (!empty($row['sv_kerusakan'])) $subtitle_detail .= ' - ' . $row['sv_kerusakan'];
                        $tipe = $warna = $internal = '';
                        $imei_display = $row['imei'] ?? '-';
                        $modal = 0;
                        $laba_bersih = $row['harga_jual'] ?? 0;
                    } elseif ($is_tt) {
                        $nama_barang = $row['nama_barang_asli'] ?? 'Produk Terhapus';
                        $tipe = isset($row['tipe']) ? ' · ' . $row['tipe'] : '';
                        $warna = isset($row['warna']) ? ' · ' . $row['warna'] : '';
                        $internal = isset($row['internal']) ? ' · ' . $row['internal'] . 'GB' : '';
                        $imei_display = $row['imei'] ?? '-';
                        $modal = $row['modal_asli'] ?? 0;
                        $laba_bersih = $row['tt_laba'];
                    } else {
                        $nama_barang = $row['nama_barang_asli'] ?? ($row['nama_barang'] ?? 'Produk Terhapus');
                        $tipe = isset($row['tipe']) ? ' · ' . $row['tipe'] : '';
                        $warna = isset($row['warna']) ? ' · ' . $row['warna'] : '';
                        $internal = isset($row['internal']) ? ' · ' . $row['internal'] . 'GB' : '';
                        $imei_display = $row['imei'] ?? '-';
                        $modal = $row['modal_asli'] ?? ($row['modal'] ?? 0);
                        $harga_jual = $row['harga_jual'] ?? 0;
                        $laba_bersih = $harga_jual - $modal;
                    }
                ?>
                <div class="transaction-card-row">
                    <!-- ICON BOX: Warna berubah merah jika downgrade -->
                    <div class="trans-icon-box" style="background: <?= $is_service ? 'rgba(59,130,246,0.1)' : ($is_tt ? ($is_downgrade ? 'rgba(239, 68, 68, 0.1)' : 'rgba(139,92,246,0.1)') : 'rgba(16, 185, 129, 0.1)') ?>; color: <?= $is_service ? '#3B82F6' : ($is_tt ? ($is_downgrade ? '#EF4444' : '#8B5CF6') : '#10B981') ?>;">
                        <i class="fas <?= $is_service ? 'fa-tools' : ($is_tt ? 'fa-exchange-alt' : 'fa-mobile-alt') ?>"></i>
                    </div>
                    
                    <div class="trans-info-area">
                        <div class="trans-product-name">
                            <?= htmlspecialchars($nama_barang) ?>
                            <?php if ($is_service && !empty($subtitle_detail)): ?>
                                <small style="font-weight:500;font-size:0.7rem;" class="d-block text-muted"><?= htmlspecialchars($subtitle_detail) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="trans-meta-sub">
                            <?php if (!$is_service): ?>
                                <span class="text-dark fw-semibold d-block mb-1" style="font-size:0.75rem;">
                                    <?= htmlspecialchars(ltrim($tipe . $warna . $internal, ' · ')) ?>
                                </span>
                            <?php endif; ?>
                            IMEI: <span class="trans-imei-code"><?= htmlspecialchars($imei_display) ?></span>
                        </div>
                        <div>
                            <span class="trans-payment-badge"><?= htmlspecialchars($row['metode_pembayaran'] ?? 'Cash') ?></span>
                            <?php if ($is_service): ?>
                                <span class="trans-service-badge ms-1">SERVICE</span>
                            <?php elseif ($is_tt): ?>
                                <!-- Badge TT berubah merah jika downgrade -->
                                <span class="trans-tt-badge ms-1" style="<?= $is_downgrade ? 'background: rgba(239, 68, 68, 0.1); color: #EF4444;' : '' ?>">TUKAR TAMBAH</span>
                                <?php if ($is_downgrade): ?>
                                    <span class="ms-1" style="background: rgba(239, 68, 68, 0.1); color: #EF4444; font-size: 0.65rem; font-weight: 700; padding: 2px 8px; border-radius: 6px;">DOWNGRADE</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="trans-payment-badge ms-1" style="background:#F1F5F9;color:#64748B;"><?= htmlspecialchars($jenis) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- INFO TAMBAHAN TT -->
                        <?php if ($is_tt): ?>
                        <div class="trans-tt-info" style="<?= $is_downgrade ? 'color: #EF4444; font-weight: 700;' : '' ?>">
                            <?php if ($is_downgrade): ?>
                                <i class="fas fa-arrow-circle-down me-1"></i>
                                <strong>Downgrade (Kembalian):</strong> Rp <?= number_format(abs($row['tt_selisih']), 0, ',', '.') ?>
                            <?php else: ?>
                                <i class="fas fa-exchange-alt me-1"></i>
                                Nilai Tukar: Rp <?= number_format($row['tt_harga_lama'] ?? 0, 0, ',', '.') ?>
                                <span class="text-muted" style="font-size: 0.65rem;">(Sisa: Rp <?= number_format($row['tt_selisih'] ?? 0, 0, ',', '.') ?>)</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="trans-amount-side">
                        <div class="trans-price-val">Rp <?= number_format($row['harga_jual'] ?? 0, 0, ',', '.') ?></div>
                        <div class="trans-profit-val">+Rp <?= number_format($laba_bersih, 0, ',', '.') ?></div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-5 w-100 bg-white rounded-4 border" style="grid-column: span 2;">
                    <i class="fa-solid fa-basket-shopping fa-2x mb-2 text-light d-block"></i>
                    <h6 class="text-muted fw-bold">Tidak ada penjualan</h6>
                    <p class="text-muted small mb-0">Belum ada catatan transaksi keluar pada tanggal ini.</p>
                </div>
            <?php endif; ?>
        </main>

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
    <a href="beli_customer.php"><i class="fas fa-hand-holding-usd"></i> Buy Out</a>
    <a href="service_hp.php"><i class="fas fa-tools"></i> Service HP</a>
</div>

<div class="popup-overlay" id="popupOverlay"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
</script>
</body>
</html>