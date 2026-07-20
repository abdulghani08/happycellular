<?php
session_start();
require_once '../api/auth_helper.php';

// Proteksi halaman: Hanya admin yang boleh masuk
restrictTo(['admin']);
include '../connection.php';

// ================= PROSES AJAX =================
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    error_reporting(0); // Matikan error sementara agar JSON tidak rusak
    while (ob_get_level()) ob_end_clean(); // Bersihkan output buffer ekstra
    header('Content-Type: application/json');
    
    try {
        if (!$koneksi) {
            throw new Exception("Koneksi database gagal.");
        }
        $action = $_POST['action'] ?? '';
        $imei   = preg_replace('/\D/', '', trim((string)($_POST['imei'] ?? '')));

        if ($imei === '') {
            throw new Exception("IMEI tidak valid.");
        }
        if (strlen($imei) !== 15) {
            throw new Exception("IMEI harus 15 digit angka.");
        }

        // ---------------------------------------------------------
        // ACTION 1: CEK IMEI
        // ---------------------------------------------------------
        if ($action === 'cek_imei') {
            // Cek di tabel barang (cukup 1x query untuk semua status)
            $q_barang = mysqli_query($koneksi, "SELECT * FROM barang WHERE imei = '$imei' LIMIT 1");
            if (!$q_barang) throw new Exception(mysqli_error($koneksi));

            if (mysqli_num_rows($q_barang) > 0) {
                $barang = mysqli_fetch_assoc($q_barang);
                if ($barang['status'] === 'READY') {
                    echo json_encode(['status' => 'success', 'data' => $barang]);
                } else {
                    echo json_encode(['status' => 'error', 'msg' => 'Barang tidak tersedia (status: ' . $barang['status'] . ')']);
                }
            } else {
                // Jika tidak ada di tabel barang, cek apakah sudah pernah direturn
                $q_return = mysqli_query($koneksi, "SELECT id FROM return_supplier WHERE imei = '$imei' LIMIT 1");
                if (!$q_return) throw new Exception(mysqli_error($koneksi));

                if (mysqli_num_rows($q_return) > 0) {
                    echo json_encode(['status' => 'error', 'msg' => 'Barang ini sudah pernah direturn ke supplier!']);
                } else {
                    echo json_encode(['status' => 'not_found', 'msg' => 'IMEI tidak ditemukan di database. Lanjut input manual?']);
                }
            }
            exit;
        }

        // ---------------------------------------------------------
        // ACTION 2: PROSES RETURN (Manual & Auto)
        // ---------------------------------------------------------
        if ($action === 'proses_return') {
            $is_manual = isset($_POST['is_manual']) && $_POST['is_manual'] === '1';

            mysqli_begin_transaction($koneksi);
            try {
                // Siapkan variabel data
                if ($is_manual) {
                    $nama     = mysqli_real_escape_string($koneksi, trim($_POST['nama'] ?? ''));
                    $tipe     = mysqli_real_escape_string($koneksi, trim($_POST['tipe'] ?? ''));
                    $warna    = mysqli_real_escape_string($koneksi, trim($_POST['warna'] ?? ''));
                    $internal = mysqli_real_escape_string($koneksi, trim($_POST['internal'] ?? ''));
                    $supplier = mysqli_real_escape_string($koneksi, trim($_POST['supplier'] ?? ''));

                    if (empty($nama)) throw new Exception("Data input manual tidak lengkap!");

                    // PROTEKSI 1: Cek apakah IMEI manual ini sudah ada di tabel return_supplier sebelumnya
                    $q_cek_ret = mysqli_query($koneksi, "SELECT id FROM return_supplier WHERE imei = '$imei' LIMIT 1");
                    if (mysqli_num_rows($q_cek_ret) > 0) throw new Exception("Barang dengan IMEI ini sudah pernah direturn!");

                    // PROTEKSI 2: Cek jika IMEI ini terdaftar di tabel barang tetapi statusnya BUKAN 'READY' (Misal sudah SOLD)
                    $q_cek_brg = mysqli_query($koneksi, "SELECT status FROM barang WHERE imei='$imei' LIMIT 1");
                    if (mysqli_num_rows($q_cek_brg) > 0) {
                        $brg_cek = mysqli_fetch_assoc($q_cek_brg);
                        if ($brg_cek['status'] !== 'READY') {
                            throw new Exception("IMEI ini terdaftar di sistem dengan status '" . $brg_cek['status'] . "'. Tidak bisa direturn manual!");
                        }
                    }
                } else {
                    // Ambil data dari database jika otomatis (Scan otomatis status READY)
                    $q = mysqli_query($koneksi, "SELECT * FROM barang WHERE imei='$imei' AND status='READY' FOR UPDATE");
                    if (!$q) throw new Exception(mysqli_error($koneksi));
                    if (mysqli_num_rows($q) == 0) throw new Exception("Barang tidak bisa direturn atau status tidak READY.");
                    
                    $barang   = mysqli_fetch_assoc($q);
                    $nama     = mysqli_real_escape_string($koneksi, $barang['nama']);
                    $tipe     = mysqli_real_escape_string($koneksi, $barang['tipe']);
                    $warna    = mysqli_real_escape_string($koneksi, $barang['warna']);
                    $internal = mysqli_real_escape_string($koneksi, $barang['internal']);
                    $supplier = mysqli_real_escape_string($koneksi, $barang['supplier']);
                }

                // 1. Hapus dari tabel barang (Hanya tereksekusi jika lolos proteksi di atas)
                $d_barang = mysqli_query($koneksi, "DELETE FROM barang WHERE imei='$imei'");
                if (!$d_barang) throw new Exception(mysqli_error($koneksi));

                // 2. Insert ke tabel return_supplier
                $q_insert = "INSERT INTO return_supplier (imei, nama, tipe, warna, internal, supplier, tanggal_return) 
                             VALUES ('$imei', '$nama', '$tipe', '$warna', '$internal', '$supplier', NOW())";
                $i_return = mysqli_query($koneksi, $q_insert);
                if (!$i_return) throw new Exception(mysqli_error($koneksi));

                // 3. Simpan perubahan secara permanen
                mysqli_commit($koneksi);
                
                date_default_timezone_set('Asia/Jakarta');
                echo json_encode([
                    'status'   => 'success', 
                    'msg'      => 'Return ke supplier berhasil',
                    'nama'     => stripslashes($nama),
                    'supplier' => stripslashes($supplier),
                    'time'     => date('H:i')
                ]);
            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                echo json_encode(['status' => 'error', 'msg' => 'Gagal proses: ' . $e->getMessage()]);
            }
            exit;
        }

        throw new Exception("Aksi tidak dikenali.");
    } catch (Exception $e) {
        // http_response_code(500) dihilangkan agar JavaScript browser tidak memblokir pembacaan pesan JSON error-nya
        echo json_encode(['status' => 'error', 'msg' => 'Sistem error: ' . $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Return Supplier · Happy Cellular</title>
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
            --warning-orange: #F59E0B;
            --warning-orange-soft: #FEF3C7;
        }
        * { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-body); color: var(--text-main); min-height: 100vh; padding-bottom: calc(var(--bottom-nav-height) + 2rem); }

        /* NAVBAR & SIDEBAR */
        .navbar-top { background: var(--surface-card); padding: 1.2rem 1.5rem 0.8rem; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
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

        /* MAIN WRAPPER & CARDS */
        .main-wrapper { max-width: 500px; margin: 1.5rem auto; padding: 0 1.2rem; }

        /* Page Header dengan tombol Riwayat */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .page-header h3 { font-size: 1.1rem; font-weight: 800; margin: 0; }
        .btn-riwayat {
            background: rgba(245,158,11,0.1);
            color: var(--warning-orange);
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
        .btn-riwayat:hover { background: rgba(245,158,11,0.2); color: #b45309; }

        .scan-card { background: var(--surface-card); border-radius: 24px; padding: 2.5rem 1.5rem 3.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.04); position: relative; text-align: center; border: 1px solid rgba(0,0,0,0.03); margin-bottom: 2rem; }
        .card-icon { width: 64px; height: 64px; background: var(--warning-orange-soft); border-radius: 18px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 1.8rem; color: var(--warning-orange); }
        .scan-card h2 { font-weight: 800; color: var(--text-main); font-size: 1.4rem; margin-bottom: 6px; }
        .input-wrapper { background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 16px; padding: 6px 14px; margin: 20px 0; display: flex; align-items: center; transition: 0.3s; }
        .input-wrapper:focus-within { border-color: var(--warning-orange); background: white; box-shadow: 0 0 0 3px var(--warning-orange-soft); }
        .input-wrapper input { background: transparent; border: none; width: 100%; padding: 12px 8px; font-size: 1.1rem; font-weight: 700; color: var(--text-main); outline: none; text-align: center; letter-spacing: 0.5px; }
        .btn-clear { background: none; border: none; color: var(--text-muted); font-size: 1.1rem; padding: 8px; cursor: pointer; display: none; }
        .btn-manual { width: 100%; padding: 14px; border-radius: 14px; font-weight: 700; background: var(--warning-orange-soft); border: 1px solid var(--warning-orange-soft); color: var(--warning-orange); display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; cursor: pointer; }
        .btn-manual:hover { background: #fde68a; color: #b45309; }
        .btn-float-scan { position: absolute; bottom: -32px; left: 50%; transform: translateX(-50%); width: 64px; height: 64px; background: var(--warning-orange); color: white; border: none; border-radius: 50%; font-size: 1.6rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 24px rgba(245, 158, 11, 0.3); transition: 0.3s; cursor: pointer; z-index: 5; }

        /* RECENT ACTIVITY */
        .section-heading { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .section-heading h3 { font-size: 1rem; font-weight: 800; color: var(--text-main); margin: 0; }
        .activity-item { background: var(--surface-card); border-radius: 16px; padding: 14px 16px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 14px rgba(0, 0, 0, 0.02); border: 1px solid rgba(0,0,0,0.02); border-left: 4px solid var(--warning-orange); animation: slideIn 0.4s ease; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .act-name { font-weight: 700; color: var(--text-main); font-size: 0.9rem; }
        .act-meta { font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; }

        /* BOTTOM NAV & POPUP */
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; height: var(--bottom-nav-height); background: var(--surface-card); display: flex; justify-content: space-around; align-items: center; box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.04); border-top: 1px solid #EDF2F7; z-index: 1050; padding-bottom: env(safe-area-inset-bottom, 0); }
        .nav-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; text-decoration: none; color: #A0AEC0; font-size: 0.7rem; font-weight: 600; flex: 1; height: 100%; cursor: pointer; }
        .nav-item i { font-size: 1.25rem; }
        .nav-item.active { color: var(--primary-dongker); }
        .more-menu-popup { position: fixed; bottom: calc(var(--bottom-nav-height) + 12px); left: 50%; transform: translateX(-50%) scale(0.95); width: calc(100% - 2.4rem); max-width: 500px; background: var(--surface-card); border-radius: 20px; box-shadow: 0 15px 35px rgba(30, 41, 59, 0.15); padding: 10px; display: none; grid-template-columns: repeat(2, 1fr); gap: 8px; z-index: 1100; opacity: 0; transition: all 0.2s; }
        .more-menu-popup.show { display: grid; transform: translateX(-50%) scale(1); opacity: 1; }
        .more-menu-popup a { padding: 12px; text-decoration: none; color: var(--text-main); font-weight: 600; font-size: 0.8rem; border-radius: 12px; display: flex; align-items: center; gap: 10px; background: #F8FAFC; }
        .more-menu-popup a i { color: var(--primary-dongker); font-size: 1rem; }
        .popup-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.12); backdrop-filter: blur(2px); z-index: 1090; display: none; }
        .popup-overlay.show { display: block; }

        /* DESKTOP LAYOUT */
        .desktop-sidebar { display: none; }
        @media (min-width: 992px) {
            body { padding-bottom: 0; display: flex; background: #F8FAFC; }
            .bottom-nav, .popup-overlay, .more-menu-popup { display: none !important; }
            .navbar-top { position: static; box-shadow: none; background: transparent; padding: 1.5rem 2rem 0; width: 100%; }
            .navbar-top .logo-area { display: none; }
            .desktop-sidebar { display: flex; flex-direction: column; width: var(--sidebar-width); background: var(--surface-card); height: 100vh; position: sticky; top: 0; border-right: 1px solid #E2E8F0; padding: 1.5rem; z-index: 1010; }
            .sidebar-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #F1F5F9; }
            .sidebar-menu { display: flex; flex-direction: column; gap: 5px; flex-grow: 1; }
            .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 0.85rem; border-radius: 12px; transition: all 0.2s; }
            .sidebar-link i { font-size: 1.1rem; width: 20px; text-align: center; }
            .sidebar-link:hover, .sidebar-link.active { background: var(--primary-dongker-soft); color: var(--primary-dongker); }
            .sidebar-divider { height: 1px; background: #F1F5F9; margin: 10px 0; }
            .content-wrapper { flex-grow: 1; display: flex; flex-direction: column; min-width: 0; }
            .main-wrapper { max-width: 1200px; width: 100%; padding: 1.5rem 2rem; margin: 0; display: flex; gap: 2rem; align-items: flex-start; }
            .scan-card { flex: 1; margin-bottom: 0; }
            .recent-container { flex: 1; }
        }

        /* SCANNER STYLES */
        #scanner-container { display: none; position: fixed; inset: 0; background: #000; z-index: 2000; }
        #reader { width:100%; height:100%; }
        #reader video { width:100%; height:100%; object-fit:cover; }
        .scanner-header { position: absolute; top: 0; left: 0; width: 100%; padding: 20px; display: flex; justify-content: space-between; z-index: 25; background: linear-gradient(to bottom, rgba(0,0,0,0.8), transparent); }
        .btn-glass { background: rgba(255,255,255,0.2); backdrop-filter: blur(8px); border: none; color: white; border-radius: 30px; padding: 10px 20px; font-weight: 600; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .btn-glass.bg-danger { background: rgba(239,68,68,0.8) !important; }
        .scanner-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10; pointer-events: none; clip-path: polygon(0% 0%, 0% 100%, 100% 100%, 100% 0%, 0% 0%, calc(50% - 130px) calc(50% - 90px), calc(50% + 130px) calc(50% - 90px), calc(50% + 130px) calc(50% + 90px), calc(50% - 130px) calc(50% + 90px), calc(50% - 130px) calc(50% - 90px)); }
        .scanner-reticle { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 260px; height: 180px; z-index: 15; pointer-events: none; }
        .scanner-reticle::before, .scanner-reticle::after, .scanner-reticle .corner-bottom::before, .scanner-reticle .corner-bottom::after { content: ''; position: absolute; width: 30px; height: 30px; border-color: #fff; border-style: solid; }
        .scanner-reticle::before { top:0; left:0; border-width:4px 0 0 4px; border-top-left-radius:12px; }
        .scanner-reticle::after { top:0; right:0; border-width:4px 4px 0 0; border-top-right-radius:12px; }
        .scanner-reticle .corner-bottom::before { bottom:0; left:0; border-width:0 0 4px 4px; border-bottom-left-radius:12px; }
        .scanner-reticle .corner-bottom::after { bottom:0; right:0; border-width:0 4px 4px 0; border-bottom-right-radius:12px; }
        .scan-laser { position: absolute; width: 100%; height: 2px; background: var(--warning-orange); top: 0; box-shadow: 0 0 12px var(--warning-orange); animation: laser 2s ease-in-out infinite; }
        @keyframes laser { 0%, 100% { top: 0; opacity: 0; } 10%, 90% { opacity: 1; } 50% { top: 100%; } }
        .scanner-helper-text { position: absolute; top: calc(50% + 120px); left: 50%; transform: translateX(-50%); color: white; font-weight: 500; font-size: 0.9rem; z-index: 15; text-shadow: 0 2px 4px rgba(0,0,0,0.5); text-align: center; width: 100%; }
        .scanner-success .scanner-reticle::before, .scanner-success .scanner-reticle::after, .scanner-success .corner-bottom::before, .scanner-success .corner-bottom::after { border-color: var(--success-green); }
    </style>
</head>
<body>

<div class="desktop-sidebar">
    <div class="sidebar-brand">
        <img src="asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
        <div class="logo-text">Happy Cell<small>Return Supplier</small></div>
    </div>
    <div class="sidebar-menu">
        <a href="home_admin.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="stock_in.php" class="sidebar-link"><i class="fas fa-arrow-circle-down" style="color:#10B981;"></i> Stock In</a>
        <a href="admin_stock_out.php" class="sidebar-link"><i class="fas fa-arrow-circle-up" style="color:#EF4444;"></i> Stock Out</a>
        <a href="return.php" class="sidebar-link active"><i class="fas fa-rotate-left" style="color:#F59E0B;"></i> Retur</a>
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
            <div class="logo-text">Happy Cell<small>Return Supplier</small></div>
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
            <h3><i class="fas fa-rotate-left me-2" style="color: var(--warning-orange);"></i>Return Supplier</h3>
            <a href="riwayat_return.php" class="btn-riwayat"><i class="fas fa-history"></i> Riwayat</a>
        </div>

        <div class="scan-card w-100">
            <div class="card-icon"><i class="fas fa-undo-alt"></i></div>
            <h2>Return Supplier</h2>
            <p class="text-muted" style="font-size:0.85rem;">Scan atau masukkan manual IMEI barang return</p>

            <div class="input-wrapper">
                <input type="text" id="imei_input" placeholder="Masukkan IMEI / Barcode" autofocus>
                <button class="btn-clear" id="clear_btn" onclick="clearInput()"><i class="fas fa-times-circle"></i></button>
            </div>

            <button class="btn-manual" onclick="cekProsesReturn(document.getElementById('imei_input').value)">
                <i class="fas fa-sync"></i> Proses Return
            </button>

            <button class="btn-float-scan" onclick="openScanner()">
                <i class="fas fa-camera"></i>
            </button>
        </div>

        <div class="recent-container w-100">
            <div class="section-heading">
                <h3><i class="fas fa-history text-muted me-1"></i> Riwayat Return Terbaru</h3>
            </div>
            <div id="recentScans">
                <?php
                $q_recent = mysqli_query($koneksi, "SELECT * FROM return_supplier ORDER BY tanggal_return DESC LIMIT 5");
                if ($q_recent && mysqli_num_rows($q_recent) > 0) {
                    while ($row = mysqli_fetch_assoc($q_recent)) {
                        $time = date('H:i', strtotime($row['tanggal_return']));
                        echo '
                        <div class="activity-item">
                            <div>
                                <div class="act-name">'.htmlspecialchars($row['nama']).'</div>
                                <div class="act-meta">'.$time.' &nbsp;·&nbsp; '.htmlspecialchars($row['supplier'] ?? '-').'</div>
                            </div>
                            <div class="text-end">
                                <i class="fas fa-check-circle text-warning fs-5"></i>
                            </div>
                        </div>';
                    }
                } else {
                    echo '<div class="text-center text-muted py-4 small" id="empty-state"><i class="fas fa-box-open fs-2 mb-2" style="opacity: 0.5;"></i><br>Belum ada aktivitas return</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<nav class="bottom-nav">
    <a href="home_admin.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a href="stock_in.php" class="nav-item"><i class="fas fa-arrow-circle-down" style="color: #10B981;"></i><span>Stock In</span></a>
    <a href="admin_stock_out.php" class="nav-item"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i><span>Stock Out</span></a>
    <a href="return.php" class="nav-item active"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i><span>Retur</span></a>
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

<div id="scanner-container">
    <div class="scanner-header">
        <button class="btn-glass" onclick="switchCamera()"><i class="fas fa-sync-alt"></i> Kamera</button>
        <button class="btn-glass" id="torchBtn" onclick="toggleTorch()" style="display:none;"><i class="fas fa-bolt"></i> Senter</button>
        <button class="btn-glass bg-danger" onclick="closeScanner()"><i class="fas fa-times"></i> Batal</button>
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
// ==========================================
// STATE & CONFIG
// ==========================================
let isBusy = false;
let scannerMode = 'quagga';
let quaggaInitialized = false;
let currentFacing = 'environment';
let isTorchOn = false;
let track = null;
let nativeStream = null;
window.scanCooldown = false;
window.lastScan = "";

const swalConfig = {
    background: '#FFFFFF', color: '#1E293B',
    confirmButtonColor: '#F59E0B', cancelButtonColor: '#F1F5F9',
    customClass: { popup: 'rounded-4', cancelButton: 'text-dark fw-bold' }
};

const beep = document.getElementById('beep');
const imeiInput = document.getElementById('imei_input');
const clearBtn = document.getElementById('clear_btn');

// ==========================================
// EVENT LISTENERS UI
// ==========================================
imeiInput.addEventListener('input', function() {
    clearBtn.style.display = this.value.length > 0 ? 'block' : 'none';
});

imeiInput.addEventListener('keypress', e => { 
    if (e.key === 'Enter') cekProsesReturn(e.target.value); 
});

function clearInput() {
    imeiInput.value = '';
    clearBtn.style.display = 'none';
    imeiInput.focus();
}

function prependActivity(nama, supplier, time) {
    const el = document.getElementById('recentScans');
    const emptyState = document.getElementById('empty-state');
    if (emptyState) emptyState.remove();

    const newHTML = `
        <div class="activity-item">
            <div>
                <div class="act-name">${nama}</div>
                <div class="act-meta">${time} &nbsp;·&nbsp; ${supplier || '-'}</div>
            </div>
            <div class="text-end">
                <i class="fas fa-check-circle text-warning fs-5"></i>
            </div>
        </div>
    `;
    el.insertAdjacentHTML('afterbegin', newHTML);
}

// ==========================================
// FUNGSI AJAX: CEK & RETURN
// ==========================================
function normalizeImei(value) {
    return String(value || '').replace(/\D/g, '').trim();
}

async function cekProsesReturn(rawImei) {
    const imei = normalizeImei(rawImei);
    if (!imei || imei.length !== 15) {
        return Swal.fire({ ...swalConfig, icon: 'warning', title: 'IMEI tidak valid', text: 'IMEI harus 15 digit angka.' });
    }
    if (isBusy) return;
    
    isBusy = true;
    Swal.fire({ title: 'Mengecek Data...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const fdCheck = new FormData();
        fdCheck.append('action', 'cek_imei');
        fdCheck.append('imei', imei);

        const respCheck = await fetch('return', { method: 'POST', headers:{'X-Requested-With': 'XMLHttpRequest'}, body: fdCheck });
        const dataCheck = await respCheck.json();
        
        Swal.close();

        if (dataCheck.status === 'success') {
            const conf = await Swal.fire({
                ...swalConfig, icon: 'warning', title: 'Konfirmasi Return',
                html: `
                    <div style="text-align:left; background:#FEF3C7; border:1px solid #F59E0B; border-radius:10px; padding:12px; margin-top:10px; font-size:0.9rem; color:#b45309;">
                        <strong>Nama:</strong> ${dataCheck.data.nama}<br>
                        <strong>Tipe/Warna:</strong> ${dataCheck.data.tipe || '-'} / ${dataCheck.data.warna || '-'}<br>
                        <strong>Supplier:</strong> ${dataCheck.data.supplier || '-'}
                    </div>
                    <p style="margin-top:15px; font-weight:700; font-size:0.95rem;">Kembalikan barang ini ke supplier?</p>
                `,
                showCancelButton: true, confirmButtonText: '<i class="fas fa-undo"></i> Ya, Return!', cancelButtonText: 'Batal'
            });

            if(conf.isConfirmed) eksekusiReturn(imei, false);

        } else if (dataCheck.status === 'not_found') {
            const askManual = await Swal.fire({
                ...swalConfig, icon: 'info', title: 'Tidak Ditemukan', text: dataCheck.msg,
                showCancelButton: true, confirmButtonText: 'Input Manual', cancelButtonText: 'Batal', confirmButtonColor: '#1E4A7A'
            });

            if (askManual.isConfirmed) tampilkanFormManual(imei);
        } else {
            Swal.fire({ ...swalConfig, icon: 'error', title: 'Gagal', text: dataCheck.msg });
        }
    } catch (e) {
        console.error("Detail Error JS:", e);
        Swal.fire({ ...swalConfig, icon: 'error', title: 'Error', text: 'Terjadi masalah jaringan atau server.' });
    } finally {
        isBusy = false;
    }
}

async function tampilkanFormManual(prefilledImei) {
    const { value: formValues } = await Swal.fire({
        ...swalConfig, title: '<span style="font-size:1.2rem;font-weight:800;color:var(--text-main);">Form Return Manual</span>',
        html: `
            <style>
                .swal2-input { height: 44px; font-size: 0.95rem; padding: 0 10px; margin-bottom: 10px; border: 1px solid #E2E8F0; background: #F8FAFC; color: #1E293B; border-radius: 10px; width:100%; box-sizing:border-box;}
                .swal2-input:focus { border-color: #F59E0B; box-shadow: 0 0 0 3px #FEF3C7; }
                .form-label-sw { display:block; text-align:left; color:#64748B; font-size:0.8rem; margin-bottom:4px; font-weight:600; }
                .row-sw { display:flex; gap:8px; margin-bottom:0; }
                .col-sw { flex:1; }
            </style>
            <label class="form-label-sw">IMEI Barang</label>
            <input id="sw-imei" class="swal2-input" value="${prefilledImei}" readonly style="background:#E2E8F0; color:#64748B;">
            <label class="form-label-sw">Nama Barang / HP <span style="color:#EF4444">*</span></label>
            <input id="sw-nama" class="swal2-input" placeholder="Contoh: iPhone 13 Pro">
            <div class="row-sw">
                <div class="col-sw"><label class="form-label-sw">Tipe</label><input id="sw-tipe" class="swal2-input"></div>
                <div class="col-sw"><label class="form-label-sw">Warna</label><input id="sw-warna" class="swal2-input"></div>
            </div>
            <div class="row-sw">
                <div class="col-sw"><label class="form-label-sw">Internal (GB)</label><input id="sw-internal" class="swal2-input" placeholder="128"></div>
                <div class="col-sw"><label class="form-label-sw">Supplier</label><input id="sw-supplier" class="swal2-input" placeholder="Nama Supplier"></div>
            </div>
        `,
        showCancelButton: true, confirmButtonText: 'Simpan & Return', cancelButtonText: 'Batal',
        preConfirm: () => {
            const nama = document.getElementById('sw-nama').value.trim();
            if(!nama) { Swal.showValidationMessage('Nama barang wajib diisi!'); return false; }
            return {
                imei: document.getElementById('sw-imei').value, nama: nama,
                tipe: document.getElementById('sw-tipe').value, warna: document.getElementById('sw-warna').value,
                internal: document.getElementById('sw-internal').value, supplier: document.getElementById('sw-supplier').value
            }
        }
    });

    if (formValues) eksekusiReturn(formValues.imei, true, formValues);
}

function eksekusiReturn(rawImei, isManual = false, manualData = null) {
    const imei = normalizeImei(rawImei);
    if (!imei || imei.length !== 15) {
        return Swal.fire({ ...swalConfig, icon: 'error', title: 'Gagal', text: 'IMEI harus 15 digit angka.' });
    }

    Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    const fd = new FormData();
    fd.append('action', 'proses_return');
    fd.append('imei', imei);
    
    if(isManual && manualData) {
        fd.append('is_manual', '1');
        fd.append('nama', manualData.nama); fd.append('tipe', manualData.tipe);
        fd.append('warna', manualData.warna); fd.append('internal', manualData.internal);
        fd.append('supplier', manualData.supplier);
    }

    fetch('return', { method: 'POST', headers:{'X-Requested-With': 'XMLHttpRequest'}, body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                Swal.fire({
                    ...swalConfig, icon: 'success', title: 'Berhasil', 
                    html: `<b style="color:var(--warning-orange);font-size:1.1rem;">${res.nama}</b><br><small style="color:var(--text-muted)">Berhasil direturn ke supplier</small>`
                });
                prependActivity(res.nama, res.supplier, res.time);
                clearInput();
            } else {
                Swal.fire({ ...swalConfig, icon: 'error', title: 'Gagal', text: res.msg });
            }
        })
        .catch((err) => {
            console.error("Fetch Error:", err);
            Swal.fire({ ...swalConfig, icon: 'error', title: 'Error Server', text: 'Terjadi masalah saat memproses data.' });
        });
}

// ==========================================
// POPUP MENU MOBILE
// ==========================================
const moreBtn = document.getElementById('moreMenuBtn');
const popup = document.getElementById('moreMenuPopup');
const overlay = document.getElementById('popupOverlay');

if (moreBtn) {
    moreBtn.addEventListener('click', e => { e.stopPropagation(); popup.classList.toggle('show'); overlay.classList.toggle('show'); });
}
if (overlay) {
    overlay.addEventListener('click', () => { popup.classList.remove('show'); overlay.classList.remove('show'); });
}

// ==========================================
// SCANNER UTILITIES
// ==========================================
function canUseBarcodeDetector() { return ('BarcodeDetector' in window) && ('getUserMedia' in navigator.mediaDevices); }

async function updateTorchAvailability() {
    let videoTrack = null;
    if (scannerMode === 'native' && nativeStream) videoTrack = nativeStream.getVideoTracks()[0];
    else if (scannerMode === 'quagga' && quaggaInitialized) { const stream = Quagga.CameraAccess.getActiveStream(); if (stream) videoTrack = stream.getVideoTracks()[0]; }
    const torchBtn = document.getElementById('torchBtn');
    if (videoTrack) { track = videoTrack; torchBtn.style.display = track.getCapabilities().torch ? 'flex' : 'none'; } 
    else torchBtn.style.display = 'none';
}

function toggleTorch() {
    if (!track) return;
    isTorchOn = !isTorchOn;
    track.applyConstraints({ advanced: [{ torch: isTorchOn }] }).catch(() => {});
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
                if (code !== window.lastScan) return handleSuccessfulScan(code);
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
        imeiInput.value = code; clearBtn.style.display = 'block'; closeScanner(); cekProsesReturn(code);
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
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>