<?php
session_start();
require_once '../api/auth_helper.php';
restrictTo(['admin']); 
include '../connection.php';

// =========================================================================
// ENDPOINT AJAX: AMBIL DETAIL BARANG BERDASARKAN IMEI
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_barang_detail') {
    header('Content-Type: application/json');
    $imei = trim($_GET['imei'] ?? '');
    
    if (!preg_match('/^[0-9]{5,}$/', $imei)) {
        echo json_encode(['success' => false, 'message' => 'IMEI tidak valid']);
        exit();
    }
    
    try {
        $stmt = $koneksi->prepare("SELECT nama, tipe, warna, internal, harga_jual, modal, status FROM barang WHERE imei = ? LIMIT 1");
        $stmt->bind_param("s", $imei);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'data' => [
                    'nama' => $data['nama'],
                    'tipe' => $data['tipe'],
                    'warna' => $data['warna'],
                    'internal' => $data['internal'],
                    'harga_jual' => (int)$data['harga_jual'],
                    'status' => $data['status']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan']);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("AJAX Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem']);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Stock Out · Happy Cellular</title>
    <link rel="shortcut icon" href="../asset/Happy.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/@ericblade/quagga2/dist/quagga.min.js"></script>

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
            --danger-red: #EF4444;
        }

        * { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }

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
            max-width: 500px;
            margin: 1.5rem auto;
            padding: 0 1.2rem;
        }

        /* Tombol Riwayat */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .page-header h3 { font-size: 1.1rem; font-weight: 800; margin: 0; }
        .btn-riwayat {
            background: rgba(239,68,68,0.1);
            color: var(--danger-red);
            border: none;
            padding: 8px 14px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-riwayat:hover { background: rgba(239,68,68,0.2); color: var(--danger-red); }

        .scan-card {
            background: var(--surface-card);
            border-radius: 24px;
            padding: 2.5rem 1.5rem 3.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            position: relative;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.03);
            margin-bottom: 2rem;
        }
        .card-icon {
            width: 64px; height: 64px;
            background: var(--primary-dongker-soft);
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            font-size: 1.8rem;
            color: var(--primary-dongker);
        }
        .scan-card h2 {
            font-weight: 800;
            color: var(--text-main);
            font-size: 1.4rem;
            margin-bottom: 6px;
        }

        .input-wrapper {
            background: #F8FAFC;
            border: 1.5px solid #E2E8F0;
            border-radius: 16px;
            padding: 6px 14px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            transition: 0.3s;
        }
        .input-wrapper:focus-within {
            border-color: var(--primary-dongker);
            background: white;
            box-shadow: 0 0 0 3px var(--primary-dongker-soft);
        }
        .input-wrapper input {
            background: transparent;
            border: none;
            width: 100%;
            padding: 12px 8px;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
            outline: none;
            text-align: center;
            letter-spacing: 0.5px;
        }
        .btn-clear {
            background: none; border: none; color: var(--text-muted); font-size: 1.1rem; padding: 8px; cursor: pointer;
        }
        .btn-clear:hover { color: var(--danger-red); }

        .btn-manual {
            width: 100%;
            padding: 14px;
            border-radius: 14px;
            font-weight: 700;
            background: var(--primary-dongker-soft);
            border: 1px solid var(--primary-dongker-soft);
            color: var(--primary-dongker);
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: 0.2s;
            cursor: pointer;
        }
        .btn-manual:hover { background: #dce7f3; }
        .btn-manual:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-float-scan {
            position: absolute;
            bottom: -32px;
            left: 50%;
            transform: translateX(-50%);
            width: 64px; height: 64px;
            background: var(--danger-red);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.6rem;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 10px 24px rgba(239, 68, 68, 0.3);
            transition: 0.3s;
            cursor: pointer;
            z-index: 5;
        }
        .btn-float-scan:hover { transform: translateX(-50%) scale(1.06); box-shadow: 0 14px 30px rgba(239, 68, 68, 0.45); }
        .btn-float-scan:active { transform: translateX(-50%) scale(0.94); }

        .section-heading {
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;
        }
        .section-heading h3 { font-size: 1rem; font-weight: 800; color: var(--text-main); margin: 0; }
        
        .activity-item {
            background: var(--surface-card);
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0,0,0,0.02);
            border-left: 4px solid var(--danger-red);
            animation: slideIn 0.4s ease;
        }
        @keyframes slideIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .act-name { font-weight: 700; color: var(--text-main); font-size: 0.9rem; }
        .act-meta { font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; }

        /* PREVIEW CARD STYLES */
        .preview-card {
            background: linear-gradient(135deg, #EEF4FA 0%, #F8FAFC 100%);
            border: 1.5px solid var(--primary-dongker);
            border-radius: 16px;
            padding: 16px;
            margin: 16px 0;
            animation: slideDown 0.3s ease;
            text-align: left;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .preview-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #CBD5E1;
        }
        .preview-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-dongker);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }
        .preview-title {
            flex: 1;
        }
        .preview-title-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
        }
        .preview-title-value {
            font-size: 0.95rem;
            color: var(--primary-dongker);
            font-weight: 800;
        }
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .preview-item {
            background: white;
            padding: 10px;
            border-radius: 10px;
            border: 1px solid #E2E8F0;
        }
        .preview-item-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 4px;
        }
        .preview-item-value {
            font-size: 0.85rem;
            color: var(--text-main);
            font-weight: 700;
        }
        .preview-price {
            background: var(--success-green);
            color: white;
            padding: 12px;
            border-radius: 10px;
            margin-top: 12px;
            text-align: center;
        }
        .preview-price-label {
            font-size: 0.7rem;
            font-weight: 600;
            opacity: 0.9;
            margin-bottom: 4px;
        }
        .preview-price-value {
            font-size: 1.2rem;
            font-weight: 800;
        }
        .preview-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-top: 8px;
        }
        .preview-status.ready {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-green);
        }
        .preview-status.sold {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-red);
        }

        .bottom-nav { position:fixed; bottom:0; left:0; right:0; height:var(--bottom-nav-height); background:var(--surface-card); display:flex; justify-content:space-around; align-items:center; box-shadow:0 -8px 30px rgba(0,0,0,0.04); border-top:1px solid #EDF2F7; z-index:1050; padding-bottom:env(safe-area-inset-bottom,0); }
        .nav-item { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; text-decoration:none; color:#A0AEC0; font-size:0.7rem; font-weight:600; flex:1; height:100%; transition:all 0.2s ease; }
        .nav-item i { font-size:1.25rem; }
        .nav-item.active { color:var(--primary-dongker); }

        .more-menu-popup { position:fixed; bottom:calc(var(--bottom-nav-height) + 12px); left:50%; transform:translateX(-50%) scale(0.95); width:calc(100% - 2.4rem); max-width:500px; background:var(--surface-card); border-radius:20px; box-shadow:0 15px 35px rgba(30,41,59,0.15); padding:10px; display:none; grid-template-columns:repeat(2,1fr); gap:8px; z-index:1100; opacity:0; transition:all 0.2s ease; }
        .more-menu-popup.show { display:grid; transform:translateX(-50%) scale(1); opacity:1; }
        .more-menu-popup a { padding:12px; text-decoration:none; color:var(--text-main); font-weight:600; font-size:0.8rem; border-radius:12px; display:flex; align-items:center; gap:10px; background:#F8FAFC; cursor:pointer; }
        .more-menu-popup a i { color:var(--primary-dongker); font-size:1rem; }
        .more-menu-popup a:hover { background:var(--primary-dongker-soft); }

        .popup-overlay { position:fixed; inset:0; background:rgba(15,23,42,0.12); backdrop-filter:blur(2px); z-index:1090; display:none; }
        .popup-overlay.show { display:block; }

        .desktop-sidebar { display:none; }
        @media (min-width: 992px) {
            body { padding-bottom:0; display:flex; background:#F8FAFC; }
            .bottom-nav, .popup-overlay, .more-menu-popup { display:none !important; }
            .navbar-top { position:static; box-shadow:none; background:transparent; padding:1.5rem 2rem 0; width:100%; }
            .navbar-top .logo-area { display:none; }
            .desktop-sidebar {
                display:flex; flex-direction:column; width:var(--sidebar-width); background:var(--surface-card);
                height:100vh; position:sticky; top:0; border-right:1px solid #E2E8F0; padding:1.5rem; z-index:1010;
            }
            .sidebar-brand { display:flex; align-items:center; gap:10px; margin-bottom:2rem; padding-bottom:1rem; border-bottom:1px solid #F1F5F9; }
            .sidebar-menu { display:flex; flex-direction:column; gap:5px; flex-grow:1; }
            .sidebar-link {
                display:flex; align-items:center; gap:12px; padding:12px 16px; color:var(--text-muted); text-decoration:none;
                font-weight:600; font-size:0.85rem; border-radius:12px; transition:all 0.2s;
            }
            .sidebar-link i { font-size:1.1rem; width:20px; text-align:center; }
            .sidebar-link:hover, .sidebar-link.active { background:var(--primary-dongker-soft); color:var(--primary-dongker); }
            .sidebar-divider { height:1px; background:#F1F5F9; margin:10px 0; }
            .content-wrapper { flex-grow:1; display:flex; flex-direction:column; min-width:0; }
            .main-wrapper { max-width:1200px; width:100%; padding:1.5rem 2rem; margin:0; display:flex; gap:2rem; align-items:flex-start; }
            .scan-card { flex:1; margin-bottom:0; }
            .recent-container { flex:1; }
        }

        #scanner-container { display: none; position: fixed; inset: 0; background: #000; z-index: 2000; }
        #reader { width:100%; height:100%; }
        #reader video { width:100%; height:100%; object-fit:cover; }
        .scanner-header { position: absolute; top: 0; left: 0; width: 100%; padding: 20px; display: flex; justify-content: space-between; z-index: 25; background: linear-gradient(to bottom, rgba(0,0,0,0.8), transparent); }
        .btn-glass { background: rgba(255,255,255,0.2); backdrop-filter: blur(8px); border: none; color: white; border-radius: 30px; padding: 10px 20px; font-weight: 600; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-glass:hover { background: rgba(255,255,255,0.3); }
        .btn-glass.bg-danger { background: rgba(239,68,68,0.8) !important; }
        .btn-glass.bg-danger:hover { background: rgba(239,68,68,1) !important; }
        .scanner-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10; pointer-events: none; clip-path: polygon(0% 0%, 0% 100%, 100% 100%, 100% 0%, 0% 0%, calc(50% - 130px) calc(50% - 90px), calc(50% + 130px) calc(50% - 90px), calc(50% + 130px) calc(50% + 90px), calc(50% - 130px) calc(50% + 90px), calc(50% - 130px) calc(50% - 90px)); }
        .scanner-reticle { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 260px; height: 180px; z-index: 15; pointer-events: none; }
        .scanner-reticle::before, .scanner-reticle::after, .scanner-reticle .corner-bottom::before, .scanner-reticle .corner-bottom::after { content: ''; position: absolute; width: 30px; height: 30px; border-color: #fff; border-style: solid; transition: border-color 0.3s; }
        .scanner-reticle::before { top:0; left:0; border-width:4px 0 0 4px; border-top-left-radius:12px; }
        .scanner-reticle::after { top:0; right:0; border-width:4px 4px 0 0; border-top-right-radius:12px; }
        .scanner-reticle .corner-bottom::before { bottom:0; left:0; border-width:0 0 4px 4px; border-bottom-left-radius:12px; }
        .scanner-reticle .corner-bottom::after { bottom:0; right:0; border-width:0 4px 4px 0; border-bottom-right-radius:12px; }
        .scan-laser { position: absolute; width: 100%; height: 2px; background: var(--danger-red); top: 0; box-shadow: 0 0 12px var(--danger-red); animation: laser 2s ease-in-out infinite; }
        @keyframes laser { 0%, 100% { top: 0; opacity: 0; } 10%, 90% { opacity: 1; } 50% { top: 100%; } }
        .scanner-helper-text { position: absolute; top: calc(50% + 120px); left: 50%; transform: translateX(-50%); color: white; font-weight: 500; font-size: 0.9rem; z-index: 15; text-shadow: 0 2px 4px rgba(0,0,0,0.5); text-align: center; width: 100%; }
        .scanner-success .scanner-reticle::before, .scanner-success .scanner-reticle::after, .scanner-success .corner-bottom::before, .scanner-success .corner-bottom::after { border-color: var(--success-green); }

        .pay-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 4px 0 12px; }
        .pay-btn { background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 14px; color: #1E293B; padding: 14px 6px 12px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 7px; font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: 0.75rem; line-height: 1.3; text-align: center; transition: all 0.2s; }
        .pay-btn i { font-size: 1.35rem; }
        .pay-btn:hover { background: #EEF4FA; }
        .pay-btn.active { background: #EEF4FA; border-color: var(--primary-dongker); color: var(--primary-dongker); }
        .pay-confirm-badge { display: none; align-items: center; gap: 8px; margin-top: 12px; padding: 9px 14px; background: #EEF4FA; border: 1px solid var(--primary-dongker); border-radius: 10px; font-size: 0.82rem; font-weight: 700; color: var(--primary-dongker); }
        
        @media (max-width: 480px) {
            .swal2-popup { padding: 1rem !important; border-radius: 20px !important; }
            .pay-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 8px; }
            .pay-btn { padding: 10px 4px 8px; font-size: 0.75rem; }
            .pay-btn i { font-size: 1.2rem; }
        }
    </style>
</head>
<body>

<!-- DESKTOP SIDEBAR -->
<div class="desktop-sidebar">
    <div class="sidebar-brand">
        <img src="asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
        <div class="logo-text">Happy Cell<small>Admin Panel</small></div>
    </div>
    <div class="sidebar-menu">
        <a href="index.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="stock_in.php" class="sidebar-link"><i class="fas fa-arrow-circle-down" style="color:#10B981;"></i> Stock In</a>
        <a href="admin_stock_out.php" class="sidebar-link active"><i class="fas fa-arrow-circle-up" style="color:#EF4444;"></i> Stock Out</a>
        <a href="return.php" class="sidebar-link"><i class="fas fa-rotate-left" style="color:#F59E0B;"></i> Retur</a>
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
    <div class="navbar-top">
        <div class="logo-area">
            <img src="asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
            <div class="logo-text">Happy Cell<small>Stock Out</small></div>
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
        <!-- TOMBOL RIWAYAT -->
        <div class="page-header w-100">
            <h3><i class="fas fa-arrow-circle-up me-2" style="color: var(--danger-red);"></i>Stock Out</h3>
            <a href="riwayat_stock_out.php" class="btn-riwayat"><i class="fas fa-history"></i> Riwayat</a>
        </div>

        <div class="scan-card w-100">
            <div class="card-icon"><i class="fas fa-barcode"></i></div>
            <h2>Scan Barang Keluar</h2>
            <p class="text-muted" style="font-size:0.85rem;">Masukkan IMEI manual atau gunakan kamera</p>

            <div class="input-wrapper">
                <input type="text" id="imei_input" placeholder="Masukkan IMEI / Barcode" autofocus>
                <button class="btn-clear" onclick="clearInput()"><i class="fas fa-times-circle"></i></button>
            </div>

            <div id="preview-container"></div>

            <button class="btn-manual" id="btn-proses" onclick="tanyaKeterangan(document.getElementById('imei_input').value)" disabled>
                <i class="fas fa-check-circle"></i> Proses Transaksi
            </button>

            <button class="btn-float-scan" onclick="openScanner()">
                <i class="fas fa-camera"></i>
            </button>
        </div>

        <div class="recent-container w-100">
            <div class="section-heading">
                <h3><i class="fas fa-history text-muted me-1"></i> Transaksi Terakhir</h3>
            </div>
            <div id="recentScans">
                <div class="text-center text-muted py-4 small">
                    <i class="fas fa-receipt fs-2 mb-2" style="opacity: 0.5;"></i><br>Belum ada aktivitas
                </div>
            </div>
        </div>
    </div>
</div>

<!-- BOTTOM NAV (Mobile) -->
<nav class="bottom-nav">
    <a href="index.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a href="stock_in.php" class="nav-item"><i class="fas fa-arrow-circle-down" style="color:#10B981;"></i><span>Stock In</span></a>
    <a href="admin_stock_out.php" class="nav-item active"><i class="fas fa-arrow-circle-up" style="color:#EF4444;"></i><span>Stock Out</span></a>
    <a href="return.php" class="nav-item"><i class="fas fa-rotate-left" style="color:#F59E0B;"></i><span>Retur</span></a>
    <div class="nav-item" id="moreMenuBtn" role="button"><i class="fas fa-bars"></i><span>Menu</span></div>
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

<!-- SCANNER MODAL -->
<div id="scanner-container">
    <div class="scanner-header">
        <button class="btn-glass" onclick="switchCamera()">
            <i class="fas fa-sync-alt"></i> Kamera
        </button>
        <button class="btn-glass" id="torchBtn" onclick="toggleTorch()" style="display:none;">
            <i class="fas fa-bolt"></i> Senter
        </button>
        <button class="btn-glass bg-danger" onclick="closeScanner()">
            <i class="fas fa-times"></i> Batal
        </button>
    </div>
    <div id="reader"></div>
    <div class="scanner-overlay"></div>
    <div class="scanner-reticle">
        <div class="corner-bottom"></div>
        <div class="scan-laser"></div>
    </div>
    <div class="scanner-helper-text">Posisikan barcode di dalam area kotak</div>
</div>

<audio id="beep" src="https://www.soundjay.com/buttons/sounds/beep-07.mp3" preload="auto"></audio>

<script>
let isBusy = false;
const recent = [];
let scannerMode = 'quagga';
let quaggaInitialized = false;
let currentFacing = 'environment';
let isTorchOn = false;
let track = null;
let nativeStream = null;
window.scanCooldown = false;
window.lastScan = "";

let _selectedPaymentId    = null;
let _selectedPaymentLabel = null;

window.hargaJualDefault = 0;
window.modalBarang = 0;
window.barangData = null;

const swalConfig = {
    background: '#FFFFFF',
    color: '#1E293B',
    confirmButtonColor: '#1E4A7A',
    cancelButtonColor: '#F1F5F9',
    customClass: { popup: 'rounded-4', cancelButton: 'text-dark fw-bold' }
};

const beep = document.getElementById('beep');

const paymentMethods = [
    { id: 'Cash',            label: 'Cash',            icon: 'fa-money-bill-wave', color: '#10B981' },
    { id: 'Transfer',        label: 'Transfer',        icon: 'fa-university',      color: '#3B82F6' },
    { id: 'Kredivo',         label: 'Kredivo',         icon: 'fa-credit-card',     color: '#EF4444' },
    { id: 'Akulaku',         label: 'Akulaku',         icon: 'fa-wallet',          color: '#F59E0B' },
    { id: 'Shopee PayLater', label: 'Shopee PayLater', icon: 'fa-shopping-bag',    color: '#F97316' },
];

// ===== FUNGSI FORMAT RUPIAH =====
function formatRupiah(angka) {
    if (!angka) return '';
    const number_string = angka.toString().replace(/[^,\d]/g, '');
    const split = number_string.split(',');
    const sisa = split[0].length % 3;
    let rupiah = split[0].substr(0, sisa);
    const ribuan = split[0].substr(sisa).match(/\d{3}/gi);
    if (ribuan) {
        const separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }
    return rupiah;
}

function parseRupiah(value) {
    if (!value) return 0;
    return parseInt(value.replace(/[^0-9]/g, '')) || 0;
}

// ===== FUNGSI PREVIEW BARANG =====
let searchTimeout;
function previewBarang(imei) {
    const previewContainer = document.getElementById('preview-container');
    const btnProses = document.getElementById('btn-proses');
    
    if (!imei || imei.length < 5) {
        previewContainer.innerHTML = '';
        btnProses.disabled = true;
        window.barangData = null;
        return;
    }
    
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        try {
            const resp = await fetch('admin_stock_out.php?action=get_barang_detail&imei=' + encodeURIComponent(imei));
            const result = await resp.json();
            
            if (result.success && result.data) {
                const data = result.data;
                window.barangData = data;
                window.hargaJualDefault = data.harga_jual || 0;
                
                const statusClass = data.status === 'READY' ? 'ready' : 'sold';
                const statusText = data.status === 'READY' ? 'READY' : 'SOLD';
                
                previewContainer.innerHTML = `
                    <div class="preview-card">
                        <div class="preview-header">
                            <div class="preview-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div class="preview-title">
                                <div class="preview-title-label">Detail Barang</div>
                                <div class="preview-title-value">${data.nama} ${data.tipe}</div>
                            </div>
                        </div>
                        <div class="preview-grid">
                            <div class="preview-item">
                                <div class="preview-item-label">Warna</div>
                                <div class="preview-item-value">${data.warna || '-'}</div>
                            </div>
                            <div class="preview-item">
                                <div class="preview-item-label">Internal</div>
                                <div class="preview-item-value">${data.internal || '-'}</div>
                            </div>
                        </div>
                        <div class="preview-price">
                            <div class="preview-price-label">Harga Jual</div>
                            <div class="preview-price-value">Rp ${formatRupiah(data.harga_jual)}</div>
                        </div>
                        <div style="text-align: center;">
                            <span class="preview-status ${statusClass}">${statusText}</span>
                        </div>
                    </div>
                `;
                
                btnProses.disabled = data.status !== 'READY';
            } else {
                previewContainer.innerHTML = '';
                btnProses.disabled = true;
                window.barangData = null;
            }
        } catch (e) {
            console.error('Error fetching barang detail:', e);
            previewContainer.innerHTML = '';
            btnProses.disabled = true;
            window.barangData = null;
        }
    }, 500);
}

function buildPaymentHTML() {
    const btns = paymentMethods.map(m => {
        const safeId = m.id.replace(/\s/g, '_');
        return `<button type="button" class="pay-btn" id="pbtn-${safeId}"
            onclick="pilihPayment('${m.id}','${m.label}')">
            <i class="fas ${m.icon}" style="color:${m.color}"></i>
            ${m.label}
        </button>`;
    }).join('');
    return `
        <p style="color:#64748B;font-size:0.85rem;margin-bottom:14px;font-weight:500;">
            Pilih metode pembayaran customer
        </p>
        <div class="pay-grid">${btns}</div>
        <div class="pay-confirm-badge" id="pay-confirm-badge">
            <i class="fas fa-check-circle"></i>
            <span id="pay-confirm-label"></span>
        </div>
    `;
}

function pilihPayment(id, label) {
    _selectedPaymentId    = id;
    _selectedPaymentLabel = label;
    document.querySelectorAll('.pay-btn').forEach(b => b.classList.remove('active'));
    const btn = document.getElementById('pbtn-' + id.replace(/\s/g, '_'));
    if (btn) btn.classList.add('active');
    const badge = document.getElementById('pay-confirm-badge');
    const lbl   = document.getElementById('pay-confirm-label');
    if (badge && lbl) {
        lbl.textContent = label + ' dipilih';
        badge.style.display = 'flex';
    }
    const confirmBtn = Swal.getConfirmButton();
    if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.style.opacity = '1';
        confirmBtn.style.cursor  = 'pointer';
    }
    updateSimulasiCicilan();
}

function updateSimulasiCicilan() {
    const metode = _selectedPaymentId;
    const isCicilan = ['Kredivo','Akulaku','Shopee PayLater'].includes(metode);
    const dpWrapper = document.getElementById('dp-wrapper');
    const simulasiEl = document.getElementById('simulasi-cicilan');
    if (!dpWrapper || !simulasiEl) return;
    if (isCicilan) {
        dpWrapper.style.display = 'block';
        const hargaInput = document.getElementById('harga-jual-input');
        const dpInput = document.getElementById('dp-input');
        const harga = parseRupiah(hargaInput?.value);
        const dp = parseRupiah(dpInput?.value);
        const sisa = harga - dp;
        document.getElementById('sisa-cicilan').textContent = 'Rp ' + formatRupiah(sisa > 0 ? sisa : 0);
        simulasiEl.style.display = 'block';
    } else {
        dpWrapper.style.display = 'none';
        simulasiEl.style.display = 'none';
    }
}

function clearInput() {
    document.getElementById('imei_input').value = '';
    document.getElementById('imei_input').focus();
    document.getElementById('preview-container').innerHTML = '';
    document.getElementById('btn-proses').disabled = true;
    window.barangData = null;
}

function addActivity(nama, metode, catatan, time) {
    recent.unshift({ nama, metode, catatan, time });
    renderActivity();
}

function renderActivity() {
    const el = document.getElementById('recentScans');
    if (!el) return;
    if(recent.length === 0) {
        el.innerHTML = `<div class="text-center text-muted py-4 small"><i class="fas fa-receipt fs-2 mb-2" style="opacity: 0.5;"></i><br>Belum ada aktivitas</div>`;
        return;
    }
    el.innerHTML = recent.slice(0, 5).map(r => `
        <div class="activity-item">
            <div>
                <div class="act-name">${r.nama}</div>
                <div class="act-meta">${r.time} &nbsp;·&nbsp; ${r.metode}${r.catatan ? ' · ' + r.catatan : ''}</div>
            </div>
        </div>
    `).join('');
}

async function tanyaKeterangan(imei) {
    if (!imei || imei.length < 5) {
        return Swal.fire({ ...swalConfig, icon: 'warning', title: 'IMEI tidak valid', text: 'Minimal 5 karakter.' });
    }
    if (isBusy) return;
    
    if (!window.barangData) {
        return Swal.fire({ ...swalConfig, icon: 'warning', title: 'Data tidak ditemukan', text: 'Silakan tunggu data barang dimuat.' });
    }
    
    if (window.barangData.status !== 'READY') {
        return Swal.fire({ ...swalConfig, icon: 'error', title: 'Barang tidak tersedia', text: 'Status barang sudah SOLD.' });
    }

    _selectedPaymentId    = null;
    _selectedPaymentLabel = null;

    // Step 1: Pilih metode
    const step1 = await Swal.fire({
        ...swalConfig,
        title: '<span style="font-size:1.1rem;font-weight:800;color:var(--text-main);">Metode Pembayaran</span>',
        html: buildPaymentHTML(),
        showCancelButton: true,
        confirmButtonText: 'Lanjut <i class="fas fa-arrow-right ms-1"></i>',
        cancelButtonText: 'Batal',
        didOpen: () => {
            const btn = Swal.getConfirmButton();
            btn.disabled = true; btn.style.opacity = '0.4'; btn.style.cursor  = 'not-allowed';
        },
        preConfirm: () => {
            if (!_selectedPaymentId) {
                Swal.showValidationMessage('Pilih metode pembayaran terlebih dahulu');
                return false;
            }
            return true;
        }
    });

    if (!step1.isConfirmed || !_selectedPaymentId) return;

    const metodeId    = _selectedPaymentId;
    const metodeLabel = _selectedPaymentLabel;
    const isCicilan = ['Kredivo','Akulaku','Shopee PayLater'].includes(metodeId);

    // Step 2: Detail transaksi dengan UI yang lebih bagus
    const step2 = await Swal.fire({
        ...swalConfig,
        title: '<span style="font-size:1.1rem;font-weight:800;color:var(--text-main);">Detail Transaksi</span>',
        width: '500px',
        html: `
            <style>
                .swal-input-group { margin-bottom: 14px; text-align: left; }
                .swal-input-group label {
                    display: flex; align-items: center; gap: 6px;
                    color: #64748B; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600;
                }
                .swal-input-group label i { font-size: 0.85rem; color: var(--primary-dongker); }
                .swal-input-wrapper {
                    position: relative; display: flex; align-items: center;
                    background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 12px;
                    transition: all 0.2s;
                }
                .swal-input-wrapper:focus-within {
                    border-color: var(--primary-dongker); background: white;
                    box-shadow: 0 0 0 3px rgba(30, 74, 122, 0.1);
                }
                .swal-input-prefix {
                    padding: 0 10px; color: #64748B; font-weight: 700; font-size: 0.9rem;
                    border-right: 1px solid #E2E8F0; background: #F1F5F9;
                    height: 44px; display: flex; align-items: center;
                    border-radius: 12px 0 0 12px;
                }
                .swal-input-field {
                    flex: 1; border: none; background: transparent; padding: 12px 14px;
                    font-size: 0.95rem; font-weight: 700; color: #1E293B; outline: none;
                    font-family: 'Plus Jakarta Sans', sans-serif;
                }
                .swal-input-field::placeholder { color: #94A3B8; font-weight: 500; }
                .row-mobile { display: flex; flex-wrap: wrap; margin: 0 -6px; }
                .col-mobile { flex: 1 0 50%; padding: 0 6px; }
                .info-box {
                    background: linear-gradient(135deg, #EEF4FA 0%, #F8FAFC 100%);
                    border: 1px solid #1E4A7A; border-radius: 12px;
                    padding: 12px 16px; margin-bottom: 16px;
                    display: flex; align-items: center; gap: 10px;
                }
                .info-box i { font-size: 1.2rem; color: #1E4A7A; }
                .info-box-content { flex: 1; }
                .info-box-label { font-size: 0.7rem; color: #64748B; font-weight: 600; text-transform: uppercase; }
                .info-box-value { font-size: 0.9rem; color: #1E4A7A; font-weight: 800; }
                .simulasi-box {
                    background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px;
                    padding: 12px 14px; margin-top: 12px;
                }
                .simulasi-row {
                    display: flex; justify-content: space-between; align-items: center;
                    font-size: 0.8rem; margin-bottom: 4px;
                }
                .simulasi-row:last-child { margin-bottom: 0; padding-top: 6px; border-top: 1px dashed #E2E8F0; }
                .simulasi-label { color: #64748B; font-weight: 600; }
                .simulasi-value { color: #1E4A7A; font-weight: 800; }
                @media (max-width: 480px) {
                    .col-mobile { flex: 1 0 100%; margin-bottom: 10px; }
                }
            </style>

            <div class="info-box">
                <i class="fas fa-tag"></i>
                <div class="info-box-content">
                    <div class="info-box-label">Metode Pembayaran</div>
                    <div class="info-box-value">${metodeLabel}</div>
                </div>
            </div>

            <div class="swal-input-group">
                <label><i class="fas fa-money-bill-wave"></i> Harga Jual (Rp)</label>
                <div class="swal-input-wrapper">
                    <div class="swal-input-prefix">Rp</div>
                    <input id="harga-jual-input" class="swal-input-field" type="text" 
                           value="${formatRupiah(window.hargaJualDefault)}" 
                           placeholder="0" inputmode="numeric">
                </div>
            </div>

            <div id="dp-wrapper" style="display:none;">
                <div class="swal-input-group">
                    <label><i class="fas fa-wallet"></i> DP / Uang Muka (Rp)</label>
                    <div class="swal-input-wrapper">
                        <div class="swal-input-prefix">Rp</div>
                        <input id="dp-input" class="swal-input-field" type="text" 
                               placeholder="0" inputmode="numeric">
                    </div>
                </div>
            </div>

            <div class="row-mobile" style="margin-top:4px;">
                <div class="col-mobile">
                    <div class="swal-input-group">
                        <label><i class="fas fa-user"></i> Nama Customer</label>
                        <div class="swal-input-wrapper">
                            <input id="nama-customer" class="swal-input-field" placeholder="Nama lengkap">
                        </div>
                    </div>
                </div>
                <div class="col-mobile">
                    <div class="swal-input-group">
                        <label><i class="fas fa-phone"></i> No. WhatsApp</label>
                        <div class="swal-input-wrapper">
                            <input id="no-hp-customer" class="swal-input-field" placeholder="0812xxx" inputmode="tel">
                        </div>
                    </div>
                </div>
            </div>

            <div class="swal-input-group">
                <label><i class="fas fa-sticky-note"></i> Catatan Tambahan</label>
                <div class="swal-input-wrapper">
                    <input id="catatan-input" class="swal-input-field" placeholder="Opsional (Kondisi/Kelengkapan)">
                </div>
            </div>

            <div id="simulasi-cicilan" style="display:none;">
                <div class="simulasi-box">
                    <div class="simulasi-row">
                        <span class="simulasi-label">Harga Barang</span>
                        <span class="simulasi-value" id="sim-harga">Rp 0</span>
                    </div>
                    <div class="simulasi-row">
                        <span class="simulasi-label">DP Dibayar</span>
                        <span class="simulasi-value" id="sim-dp">Rp 0</span>
                    </div>
                    <div class="simulasi-row">
                        <span class="simulasi-label">Sisa dibiayai ${metodeLabel}</span>
                        <span class="simulasi-value" id="sisa-cicilan" style="color: #10B981; font-size: 0.95rem;">Rp 0</span>
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check-circle me-1"></i> Proses',
        cancelButtonText: '<i class="fas fa-arrow-left me-1"></i> Kembali',
        didOpen: () => {
            const hargaInput = document.getElementById('harga-jual-input');
            const dpInput = document.getElementById('dp-input');
            
            if (hargaInput) {
                hargaInput.addEventListener('input', function(e) {
                    const cursorPos = this.selectionStart;
                    const oldLength = this.value.length;
                    this.value = formatRupiah(parseRupiah(this.value));
                    const newLength = this.value.length;
                    this.setSelectionRange(cursorPos + (newLength - oldLength), cursorPos + (newLength - oldLength));
                    updateSimulasiCicilan();
                });
            }
            
            if (dpInput) {
                dpInput.addEventListener('input', function(e) {
                    const cursorPos = this.selectionStart;
                    const oldLength = this.value.length;
                    this.value = formatRupiah(parseRupiah(this.value));
                    const newLength = this.value.length;
                    this.setSelectionRange(cursorPos + (newLength - oldLength), cursorPos + (newLength - oldLength));
                    updateSimulasiCicilan();
                });
            }
            
            updateSimulasiCicilan();
        },
        preConfirm: () => {
            const harga = parseRupiah(document.getElementById('harga-jual-input').value);
            const nama = document.getElementById('nama-customer').value.trim();
            const noHp = document.getElementById('no-hp-customer').value.trim();
            const catatan = document.getElementById('catatan-input').value.trim();
            const dp = parseRupiah(document.getElementById('dp-input')?.value);
            
            if (!harga || harga <= 0) { Swal.showValidationMessage('Harga jual harus diisi'); return false; }
            if (!nama) { Swal.showValidationMessage('Nama customer wajib diisi'); return false; }
            if (['Kredivo','Akulaku','Shopee PayLater'].includes(_selectedPaymentId)) {
                if (!dp || dp < 0) { Swal.showValidationMessage('DP wajib diisi untuk cicilan'); return false; }
                if (dp > harga) { Swal.showValidationMessage('DP tidak boleh melebihi harga jual'); return false; }
            }
            return { harga: harga.toString(), nama, noHp, catatan, dp: dp.toString() };
        }
    });

    if (step2.isDismissed && step2.dismiss === Swal.DismissReason.cancel) {
        return tanyaKeterangan(imei);
    }
    if (!step2.isConfirmed) return;

    const { harga, nama, noHp, catatan, dp } = step2.value;
    kirimData(imei, metodeLabel, harga, nama, noHp, catatan, dp);
}

function kirimData(imei, metodeLabel, hargaFinal, namaCustomer, noHp, catatan, dp) {
    isBusy = true;
    Swal.fire({ ...swalConfig, title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    const fd = new FormData();
    fd.append('action', 'scan_out');
    fd.append('imei', imei);
    fd.append('metode', metodeLabel);
    fd.append('harga_jual', hargaFinal);
    fd.append('nama_customer', namaCustomer);
    fd.append('no_hp', noHp);
    fd.append('keterangan', catatan);
    fd.append('dp', dp);

    fetch('../api/ajax_stock_out.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                Swal.fire({
                    ...swalConfig,
                    icon: 'success',
                    title: 'Berhasil',
                    html: `<b style="color:var(--primary-dongker);font-size:1.1rem;">${res.nama}</b><br><small style="color:var(--text-muted)">Transaksi selesai diproses</small>`
                });
                const now = new Date();
                addActivity(res.nama, metodeLabel, catatan, now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }));
                clearInput();
            } else {
                Swal.fire({ ...swalConfig, icon: 'error', title: 'Gagal', text: res.msg });
            }
        })
        .catch(() => Swal.fire({ ...swalConfig, icon: 'error', title: 'Koneksi Gagal', text: 'Periksa koneksi internet anda.' }))
        .finally(() => { isBusy = false; });
}

// ===== POPUP MENU LAINNYA =====
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

// ===== SCANNER FUNCTIONS =====
function canUseBarcodeDetector() { return ('BarcodeDetector' in window) && ('getUserMedia' in navigator.mediaDevices); }
async function updateTorchAvailability() {
    let videoTrack = null;
    if (scannerMode === 'native' && nativeStream) { videoTrack = nativeStream.getVideoTracks()[0]; } 
    else if (scannerMode === 'quagga' && quaggaInitialized) { const stream = Quagga.CameraAccess.getActiveStream(); if (stream) videoTrack = stream.getVideoTracks()[0]; }
    const torchBtn = document.getElementById('torchBtn');
    if (videoTrack) { track = videoTrack; torchBtn.style.display = track.getCapabilities().torch ? 'flex' : 'none'; } 
    else { torchBtn.style.display = 'none'; }
}

function toggleTorch() {
    if (!track) return;
    isTorchOn = !isTorchOn;
    track.applyConstraints({ advanced: [{ torch: isTorchOn }] }).catch(() => Swal.fire({ ...swalConfig, icon: 'error', title: 'Gagal menyalakan senter' }));
    const icon = document.querySelector('#torchBtn i');
    const btn  = document.getElementById('torchBtn');
    if (isTorchOn) { icon.classList.replace('fa-bolt', 'fa-bolt-slash'); btn.style.background = 'rgba(255,255,255,0.5)'; } 
    else { icon.classList.replace('fa-bolt-slash', 'fa-bolt'); btn.style.background = 'rgba(255,255,255,0.2)'; }
}

async function startNativeScanner(facing) {
    const readerEl = document.getElementById('reader'); readerEl.innerHTML = '';
    const video = document.createElement('video');
    video.setAttribute('autoplay', ''); video.setAttribute('playsinline', ''); video.setAttribute('muted', '');
    video.style.cssText = 'width:100%;height:100%;object-fit:cover;';
    readerEl.appendChild(video);
    nativeStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: facing, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
    video.srcObject = nativeStream; await video.play();
    const barcodeDetector = new BarcodeDetector({ formats: ['code_128','ean_13','ean_8','code_39','upc_a'] });
    const detectLoop = async () => {
        if (!nativeStream || !video.readyState) return;
        try {
            const barcodes = await barcodeDetector.detect(video);
            if (barcodes.length > 0 && !window.scanCooldown) {
                const code = barcodes[0].rawValue;
                if (code !== window.lastScan) { handleSuccessfulScan(code); return; }
            }
        } catch (err) {}
        if (nativeStream) requestAnimationFrame(detectLoop);
    };
    detectLoop();
}

function stopNativeScanner() { if (nativeStream) { nativeStream.getTracks().forEach(t => t.stop()); nativeStream = null; } document.getElementById('reader').innerHTML = ''; }

function startQuagga(facing) {
    if (quaggaInitialized) { Quagga.stop(); Quagga.offDetected(); quaggaInitialized = false; }
    Quagga.init({
        inputStream: { name: "Live", type: "LiveStream", target: document.querySelector('#reader'), constraints: { facingMode: facing, width: { min: 640, ideal: 1280 }, height: { min: 480, ideal: 720 } }, area: { top: "25%", right: "15%", left: "15%", bottom: "30%" } },
        decoder: { readers: ["code_128_reader","ean_reader","ean_8_reader","code_39_reader","upc_reader"] },
        locator: { patchSize: "medium", halfSample: false }, numOfWorkers: navigator.hardwareConcurrency || 4, frequency: 10, locate: true
    }, err => {
        if (err) { Swal.fire({ ...swalConfig, icon: 'error', title: 'Kamera tidak dapat diakses' }); closeScanner(); return; }
        quaggaInitialized = true; Quagga.start();
    });
    Quagga.offDetected();
    Quagga.onDetected(result => { const code = result.codeResult.code; if (!window.scanCooldown && code !== window.lastScan) handleSuccessfulScan(code); });
}

function handleSuccessfulScan(code) {
    window.scanCooldown = true; window.lastScan = code; beep.play().catch(() => {});
    document.getElementById('scanner-container').classList.add('scanner-success');
    setTimeout(() => {
        document.getElementById('imei_input').value = code;
        previewBarang(code);
        closeScanner();
        setTimeout(() => { window.scanCooldown = false; window.lastScan = ""; }, 2000);
    }, 400);
}

async function startScanner(facing) {
    if (scannerMode === 'native' && nativeStream) stopNativeScanner();
    else if (scannerMode === 'quagga' && quaggaInitialized) { Quagga.stop(); Quagga.offDetected(); quaggaInitialized = false; }
    if (canUseBarcodeDetector()) { try { await startNativeScanner(facing); scannerMode = 'native'; } catch (e) { startQuagga(facing); scannerMode = 'quagga'; } } 
    else { startQuagga(facing); scannerMode = 'quagga'; }
    await updateTorchAvailability();
}

function openScanner() {
    document.getElementById('scanner-container').style.display = 'block'; document.getElementById('scanner-container').classList.remove('scanner-success');
    window.scanCooldown = false; window.lastScan = ""; isTorchOn = false;
    const torchIcon = document.querySelector('#torchBtn i');
    if (torchIcon) { torchIcon.classList.remove('fa-bolt-slash'); torchIcon.classList.add('fa-bolt'); document.getElementById('torchBtn').style.background = 'rgba(255,255,255,0.2)'; }
    startScanner(currentFacing);
}

function closeScanner() {
    document.getElementById('scanner-container').style.display = 'none';
    if (track && isTorchOn) track.applyConstraints({ advanced: [{ torch: false }] }).catch(() => {});
    if (scannerMode === 'native') stopNativeScanner(); else if (scannerMode === 'quagga' && quaggaInitialized) { Quagga.stop(); Quagga.offDetected(); quaggaInitialized = false; }
    track = null; isTorchOn = false;
}

function switchCamera() {
    currentFacing = (currentFacing === 'environment') ? 'user' : 'environment';
    if (track && isTorchOn) { track.applyConstraints({ advanced: [{ torch: false }] }).catch(() => {}); isTorchOn = false; }
    startScanner(currentFacing);
}

// Event listener untuk input IMEI
document.getElementById('imei_input').addEventListener('input', function(e) {
    previewBarang(this.value);
});

document.getElementById('imei_input').addEventListener('keypress', e => { 
    if (e.key === 'Enter') {
        e.preventDefault();
        if (!document.getElementById('btn-proses').disabled) {
            tanyaKeterangan(e.target.value);
        }
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>