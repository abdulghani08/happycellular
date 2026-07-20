<?php
session_start();
require_once '../api/auth_helper.php';
restrictTo(['admin']);
require_once '../connection.php';

$merkList = [];
$qMerk = mysqli_query($koneksi, "SELECT id, nama_merk FROM master_merk WHERE status = 'aktif' ORDER BY nama_merk ASC");
while ($r = mysqli_fetch_assoc($qMerk)) { $merkList[] = $r; }

$supplierList = [];
$qSup = mysqli_query($koneksi, "SELECT id, nama_supplier FROM master_supplier WHERE status = 'aktif' ORDER BY nama_supplier ASC");
while ($r = mysqli_fetch_assoc($qSup)) { $supplierList[] = $r; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Stock In · Happy Cellular</title>
    <link rel="shortcut icon" href="../asset/Happy.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
        }
        * { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
        body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg-body); min-height:100vh; color:var(--text-main); padding-bottom:calc(var(--bottom-nav-height) + 2rem); }
        .navbar-top { background:var(--surface-card); padding:1.2rem 1.5rem 0.8rem; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:1000; box-shadow:0 2px 10px rgba(0,0,0,0.02); }
        .logo-area { display:flex; align-items:center; gap:10px; }
        .navbar-logo { width:32px; height:32px; object-fit:contain; border-radius:8px; }
        .logo-text { font-weight:800; font-size:1.3rem; color:var(--primary-dongker); letter-spacing:-0.5px; }
        .logo-text small { font-size:0.75rem; font-weight:500; color:var(--text-muted); display:block; margin-top:-2px; }
        .user-badge {
            background: var(--primary-dongker-soft); padding:8px 16px; border-radius:20px;
            color:var(--primary-dongker); font-weight:700; font-size:0.8rem;
            display:flex; align-items:center; gap:6px; border:none; cursor:pointer; transition:all 0.2s;
        }
        .user-badge:after {
            display: inline-block; margin-left: 0.255em; vertical-align: 0.255em;
            content: ""; border-top: 0.3em solid; border-right: 0.3em solid transparent;
            border-bottom: 0; border-left: 0.3em solid transparent;
        }
        .dropdown-menu-custom {
            border-radius: 16px; box-shadow: 0 10px 25px rgba(30,41,59,0.1);
            border: 1px solid rgba(0,0,0,0.05); padding: 8px;
            z-index: 1020 !important;
        }
        .dropdown-menu-custom .dropdown-item {
            font-size: 0.85rem; font-weight: 600; color: var(--text-main);
            padding: 10px 14px; border-radius: 10px; display: flex; align-items: center; gap: 10px;
        }
        .dropdown-menu-custom .dropdown-item i { color: var(--primary-dongker); font-size: 0.95rem; width: 16px; text-align: center; }
        .dropdown-menu-custom .dropdown-item:hover { background: var(--primary-dongker-soft); color: var(--primary-dongker); }

        .main-wrapper { max-width:600px; margin:0 auto; padding:1rem 1.2rem; }
        .btn-scan-hero { background:linear-gradient(135deg,var(--primary-dongker),var(--accent-blue)); border:none; color:white; border-radius:16px; padding:1rem; width:100%; font-size:1rem; font-weight:700; display:flex; align-items:center; justify-content:center; gap:10px; box-shadow:0 10px 25px -5px rgba(30,74,122,0.2); transition:transform 0.2s; margin-bottom:1rem; }
        .btn-scan-hero:active { transform:scale(0.97); }
        .card-custom { background:var(--surface-card); border-radius:20px; padding:1.5rem; margin-bottom:1.2rem; box-shadow:0 4px 18px rgba(0,0,0,0.02); border:1px solid rgba(0,0,0,0.01); }
        .card-title-custom { font-size:0.9rem; font-weight:800; color:var(--primary-dongker); letter-spacing:0.5px; text-transform:uppercase; display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:1.2rem; border-bottom:1px solid #F1F5F9; padding-bottom:12px; }
        .imei-chip { background:#f0fdf4; color:#10B981; border:1px solid #bbf7d0; border-radius:10px; padding:8px 12px; font-weight:700; font-size:0.85rem; display:inline-flex; align-items:center; gap:8px; margin:4px; }
        .imei-chip .btn-del { color:#EF4444; cursor:pointer; padding:2px; }
        .imei-chip.dup { background:#fef3c7; color:#d97706; border-color:#fde68a; }
        .form-floating > .form-control, .form-floating > .form-select { border-radius:12px; border:1px solid #E2E8F0; height:calc(3.5rem + 2px); padding:1rem 0.75rem; font-weight:600; font-size:0.9rem; color:var(--text-main); }
        .form-floating > .form-control:focus { border-color:var(--primary-dongker); box-shadow:0 0 0 3px var(--primary-dongker-soft); }
        .form-floating > label { color:var(--text-muted); font-weight:500; font-size:0.85rem; }
        .profit-badge { display:inline-block; padding:8px 14px; border-radius:10px; font-size:0.85rem; font-weight:700; margin-top:5px; width:100%; text-align:center; }
        .upload-area { display:flex; gap:15px; align-items:center; background:#F8FAFC; padding:15px; border-radius:12px; border:2px dashed #E2E8F0; }
        .img-preview-box { width:70px; height:70px; background:white; border-radius:12px; display:flex; align-items:center; justify-content:center; border:1px solid #E2E8F0; overflow:hidden; flex-shrink:0; }
        .img-preview-box img { width:100%; height:100%; object-fit:cover; }
        .summary-bar { background:var(--surface-card); border-radius:20px; padding:1rem 1.2rem; box-shadow:0 10px 30px rgba(0,0,0,0.08); border:1px solid rgba(0,0,0,0.02); position:sticky; bottom:calc(var(--bottom-nav-height) + 12px); z-index:100; margin-top:1rem; }
        .btn-save { background:var(--primary-dongker); color:white; border-radius:14px; padding:14px 20px; font-weight:700; font-size:1rem; width:100%; border:none; transition:0.2s; box-shadow:0 4px 12px rgba(30,74,122,0.2); display:flex; justify-content:center; align-items:center; gap:10px; }
        .btn-save:active { transform:scale(0.97); }

        .merge-indicator {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white; border-radius: 12px; padding: 12px 16px; margin-bottom: 12px;
            display: none; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 600;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); animation: slideDown 0.3s ease;
        }
        .merge-indicator.show { display: flex; }
        .merge-indicator i { font-size: 1.2rem; }
        @keyframes slideDown { from{transform:translateY(-10px); opacity:0;} to{transform:translateY(0); opacity:1;} }

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
        @media (min-width:992px) {
            body { padding-bottom:0; display:flex; background:#F8FAFC; }
            .bottom-nav, .popup-overlay, .more-menu-popup { display:none!important; }
            .navbar-top { position:static; box-shadow:none; background:transparent; padding:1.5rem 2rem 0; width:100%; }
            .navbar-top .logo-area { display:none; }
            .desktop-sidebar { display:flex; flex-direction:column; width:var(--sidebar-width); background:var(--surface-card); height:100vh; position:sticky; top:0; border-right:1px solid #E2E8F0; padding:1.5rem; z-index:1010; }
            .sidebar-brand { display:flex; align-items:center; gap:10px; margin-bottom:2rem; padding-bottom:1rem; border-bottom:1px solid #F1F5F9; }
            .sidebar-menu { display:flex; flex-direction:column; gap:5px; flex-grow:1; }
            .sidebar-link { display:flex; align-items:center; gap:12px; padding:12px 16px; color:var(--text-muted); text-decoration:none; font-weight:600; font-size:0.85rem; border-radius:12px; transition:all 0.2s; }
            .sidebar-link i { font-size:1.1rem; width:20px; text-align:center; }
            .sidebar-link:hover, .sidebar-link.active { background:var(--primary-dongker-soft); color:var(--primary-dongker); }
            .sidebar-divider { height:1px; background:#F1F5F9; margin:10px 0; }
            .content-wrapper { flex-grow:1; display:flex; flex-direction:column; min-width:0; }
            .main-wrapper { max-width:1200px; width:100%; padding:1.5rem 2rem; margin:0; }
            .summary-bar { bottom:20px; max-width:600px; margin-left:auto; margin-right:auto; }
        }
        #scannerModal { position:fixed; top:0; left:0; width:100%; height:100%; background:#000; z-index:10000; display:none; }
        .scanner-header { position:absolute; top:0; left:0; width:100%; padding:20px; display:flex; justify-content:space-between; z-index:10001; background:linear-gradient(to bottom, rgba(0,0,0,0.8), transparent); }
        .btn-glass { background:rgba(255,255,255,0.2); backdrop-filter:blur(8px); border:none; color:white; border-radius:30px; padding:10px 20px; font-weight:600; cursor:pointer; font-size:0.9rem; display:flex; align-items:center; gap:8px; transition:all 0.2s; }
        .btn-glass:hover { background:rgba(255,255,255,0.3); }
        .btn-glass.bg-danger { background:rgba(239,68,68,0.8)!important; }
        #reader { width:100%; height:100%; overflow:hidden; }
        #reader video { object-fit:cover; width:100%; height:100%; }
        .scanner-overlay { position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10001; pointer-events:none; clip-path: polygon(0% 0%, 0% 100%, 100% 100%, 100% 0%, 0% 0%, calc(50% - 140px) calc(50% - 90px), calc(50% + 140px) calc(50% - 90px), calc(50% + 140px) calc(50% + 90px), calc(50% - 140px) calc(50% + 90px), calc(50% - 140px) calc(50% - 90px)); }
        .scanner-reticle { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:280px; height:180px; z-index:10002; pointer-events:none; }
        .scanner-reticle::before, .scanner-reticle::after, .scanner-reticle .corner-bottom::before, .scanner-reticle .corner-bottom::after { content:''; position:absolute; width:30px; height:30px; border-color:#fff; border-style:solid; transition:border-color 0.3s; }
        .scanner-reticle::before { top:0; left:0; border-width:4px 0 0 4px; border-top-left-radius:12px; }
        .scanner-reticle::after { top:0; right:0; border-width:4px 4px 0 0; border-top-right-radius:12px; }
        .scanner-reticle .corner-bottom::before { bottom:0; left:0; border-width:0 0 4px 4px; border-bottom-left-radius:12px; }
        .scanner-reticle .corner-bottom::after { bottom:0; right:0; border-width:0 4px 4px 0; border-bottom-right-radius:12px; }
        .scan-line { position:absolute; top:0; left:0; width:100%; height:2px; background:#3B82F6; box-shadow:0 0 10px #3B82F6; animation:scanAnim 2s ease-in-out infinite; }
        @keyframes scanAnim { 0%,100% { top:0; opacity:0; } 10%,90% { opacity:1; } 50% { top:100%; } }
        .scanner-helper-text { position:absolute; top:calc(50% + 120px); left:50%; transform:translateX(-50%); color:white; font-weight:500; font-size:0.9rem; z-index:10002; text-shadow:0 2px 4px rgba(0,0,0,0.5); text-align:center; width:100%; }
        .scanner-success .scanner-reticle::before, .scanner-success .scanner-reticle::after, .scanner-success .scanner-reticle .corner-bottom::before, .scanner-success .scanner-reticle .corner-bottom::after { border-color:#10B981; }
        #loadingOverlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); backdrop-filter:blur(4px); z-index:2000; display:none; align-items:center; justify-content:center; color:white; flex-direction:column; }
        .toast-container { position:fixed; top:20px; left:50%; transform:translateX(-50%); z-index:9999; }
        .toast-mini { background:var(--primary-dongker); color:white; border-radius:30px; padding:12px 24px; font-weight:600; font-size:0.9rem; display:flex; align-items:center; gap:10px; margin-bottom:10px; box-shadow:0 10px 25px rgba(0,0,0,0.2); animation:slideDown 0.3s ease; }
        .toast-mini.success { background: var(--success-green); }
        .toast-mini.warning { background: #F59E0B; }
        .toast-mini.danger { background: #EF4444; }
        .toast-mini.merge { background: linear-gradient(135deg, #10B981, #059669); }
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
        <a href="stock_in.php" class="sidebar-link active"><i class="fas fa-arrow-circle-down" style="color:#10B981;"></i> Stock In</a>
        <a href="admin_stock_out.php" class="sidebar-link"><i class="fas fa-arrow-circle-up" style="color:#EF4444;"></i> Stock Out</a>
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
    <!-- NAVBAR ATAS -->
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
        <!-- KARTU 1: FORM SPESIFIKASI & DAFTAR IMEI UNTUK GRUP -->
        <div class="card-custom">
            <div class="card-title-custom">
                <div><i class="fas fa-layer-group me-2"></i> Spesifikasi Grup</div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary rounded-pill fw-bold" onclick="resetAllInputs()" title="Reset semua input termasuk dropdown"><i class="fas fa-redo me-1"></i>Reset Semua</button>
                    <button class="btn btn-sm btn-outline-danger rounded-pill fw-bold" onclick="resetCurrentGroup()" title="Reset hanya daftar IMEI"><i class="fas fa-times me-1"></i>Reset IMEI</button>
                </div>
            </div>

            <!-- NEW: Auto-merge indicator -->
            <div id="mergeIndicator" class="merge-indicator">
                <i class="fas fa-compress-arrows-alt"></i>
                <div>
                    <div id="mergeIndicatorText" style="font-weight:700;">Spesifikasi cocok dengan grup yang sudah ada</div>
                    <div id="mergeIndicatorSubtext" style="font-size:0.75rem; opacity:0.9;">IMEI akan otomatis digabungkan ke grup tersebut</div>
                </div>
            </div>

            <!-- Dropdown Master Bertingkat -->
            <div class="row g-2 mb-3">
                <div class="col-12">
                    <label class="small fw-bold text-muted mb-1">Merek Barang *</label>
                    <select id="merk" class="form-select master-select" style="border-radius:12px;">
                        <option value="">-- Pilih Merek --</option>
                        <?php foreach ($merkList as $m): ?>
                        <option value="<?= $m['id'] ?>" data-label="<?= htmlspecialchars($m['nama_merk']) ?>"><?= htmlspecialchars($m['nama_merk']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="small fw-bold text-muted mb-1">Nama Barang *</label>
                    <select id="namaBarang" class="form-select master-select" style="border-radius:12px;" disabled>
                        <option value="">-- Pilih Nama Barang --</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="small fw-bold text-muted mb-1">Kondisi *</label>
                    <select id="kondisi" class="form-select master-select" style="border-radius:12px;" disabled>
                        <option value="">-- Pilih Kondisi --</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="small fw-bold text-muted mb-1">Internal Storage *</label>
                    <select id="internal" class="form-select master-select" style="border-radius:12px;" disabled>
                        <option value="">-- Pilih Storage --</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="small fw-bold text-muted mb-1">Warna *</label>
                    <select id="warna" class="form-select master-select" style="border-radius:12px;" disabled>
                        <option value="">-- Pilih Warna --</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="small fw-bold text-muted mb-1">Supplier *</label>
                    <select id="supplier" class="form-select master-select" style="border-radius:12px;">
                        <option value="">-- Pilih Supplier --</option>
                        <?php foreach ($supplierList as $s): ?>
                        <option value="<?= htmlspecialchars($s['nama_supplier']) ?>"><?= htmlspecialchars($s['nama_supplier']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Harga & Keterangan -->
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div class="form-floating">
                        <input type="text" id="modal" class="form-control" placeholder="0" inputmode="numeric">
                        <label>Harga Modal (Rp) *</label>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-floating">
                        <input type="text" id="harga_jual" class="form-control" placeholder="0" inputmode="numeric">
                        <label>Harga Jual (Rp) *</label>
                    </div>
                </div>
                <div class="col-12" id="profitWrap" style="display:none;">
                    <span id="profitTxt" class="profit-badge"></span>
                </div>
                <div class="col-12">
                    <div class="form-floating">
                        <input type="text" id="keterangan" class="form-control" placeholder="Keterangan tambahan">
                        <label>Keterangan</label>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-floating">
                        <input type="date" id="tanggal" class="form-control">
                        <label>Tanggal Masuk</label>
                    </div>
                </div>
            </div>

            <!-- Upload Foto -->
            <div class="mb-3">
                <label class="small text-muted fw-bold mb-2 d-block">Foto Barang (Opsional)</label>
                <div class="upload-area">
                    <div class="img-preview-box" id="imagePreview">
                        <i class="fas fa-camera text-muted fs-4"></i>
                    </div>
                    <div class="d-flex flex-column gap-2 flex-grow-1">
                        <button class="btn btn-sm btn-outline-secondary w-100 rounded-pill fw-semibold" id="btnGallery"><i class="fas fa-folder me-2"></i> Pilih File</button>
                        <button class="btn btn-sm btn-danger rounded-pill fw-semibold" id="btnRemoveImage" style="display:none;"><i class="fas fa-trash-alt me-1"></i> Hapus</button>
                    </div>
                </div>
                <input type="file" id="fileInput" accept="image/*" hidden>
            </div>

            <!-- Input IMEI + Tombol Scan -->
            <div class="row g-2 mb-3">
                <div class="col-12">
                    <button class="btn-scan-hero" onclick="openScanner()">
                        <i class="fas fa-camera"></i> Scan IMEI via Kamera
                    </button>
                </div>
                <div class="col-8">
                    <input type="text" id="scanInput" class="form-control" placeholder="Scan atau ketik IMEI..." autocomplete="off" style="border-radius:12px;">
                </div>
                <div class="col-4">
                    <button class="btn btn-primary w-100 h-100 fw-bold" style="background:var(--primary-dongker); border:none; border-radius:12px;" onclick="tambahImeiKeGrup()">
                        <i class="fas fa-plus"></i> Tambah
                    </button>
                </div>
            </div>

            <!-- Daftar IMEI untuk grup saat ini -->
            <label class="small text-muted fw-bold d-block mb-2">IMEI dalam grup ini:</label>
            <div class="p-3 bg-light rounded-3" style="max-height:150px; overflow-y:auto;" id="currentImeiList">
                <div class="text-center text-muted py-2 small">
                    <i class="fas fa-box-open fs-5 mb-1" style="opacity:0.5;"></i><br>Belum ada IMEI
                </div>
            </div>

            <button class="btn btn-success w-100 mt-3 rounded-pill fw-bold" onclick="addGroupToBatch()">
                <i class="fas fa-check-circle"></i> Konfirmasi Grup & Tambah ke Batch
            </button>
        </div>

        <!-- KARTU 2: DAFTAR GRUP YANG SIAP DISIMPAN -->
        <div class="card-custom">
            <div class="card-title-custom">
                <div><i class="fas fa-layer-group me-2"></i> Grup yang Siap Disimpan</div>
                <button class="btn btn-sm btn-outline-danger rounded-pill fw-bold" onclick="clearAllGroups()">Hapus Semua Grup</button>
            </div>
            <div id="batchGroupsContainer" class="p-2">
                <div class="text-center text-muted py-3 small">
                    <i class="fas fa-folder-open fs-4 mb-1" style="opacity:0.5;"></i><br>Belum ada grup ditambahkan
                </div>
            </div>
        </div>

        <!-- STICKY ACTION BAR -->
        <div class="summary-bar">
            <button class="btn-save" onclick="simpanSemuaGroup()">
                <i class="fas fa-save"></i> SIMPAN SEMUA GRUP
            </button>
        </div>
    </div>
</div>

<!-- BOTTOM NAV (Mobile) -->
<nav class="bottom-nav">
    <a href="index.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a href="stock_in.php" class="nav-item active"><i class="fas fa-arrow-circle-down" style="color:#10B981;"></i><span>Stock In</span></a>
    <a href="admin_stock_out.php" class="nav-item"><i class="fas fa-arrow-circle-up" style="color:#EF4444;"></i><span>Stock Out</span></a>
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
<div id="scannerModal">
    <div class="scanner-header">
        <button class="btn-glass" onclick="switchCamera()"><i class="fas fa-sync-alt"></i> Kamera</button>
        <button class="btn-glass" id="torchBtn" onclick="toggleTorch()" style="display:none;"><i class="fas fa-bolt"></i> Senter</button>
        <button class="btn-glass bg-danger text-white" onclick="closeScanner()"><i class="fas fa-times"></i> Batal</button>
    </div>
    <div id="reader"></div>
    <div class="scanner-overlay"></div>
    <div class="scanner-reticle"><div class="corner-bottom"></div><div class="scan-line"></div></div>
    <div class="scanner-helper-text">Arahkan barcode ke dalam kotak</div>
</div>

<div id="loadingOverlay">
    <div class="spinner-border text-light mb-3" style="width:3rem; height:3rem;"></div>
    <div class="fw-bold">Menyimpan Data...</div>
</div>

<audio id="beepSound" src="https://www.soundjay.com/buttons/sounds/beep-07.mp3" preload="auto"></audio>

<script>
    // ===== GLOBAL VARIABLES =====
    let currentImeis = [];
    let batchGroups = [];
    let imageFile = null;
    let scanCooldown = false;
    let lastScan = "";
    let currentFacing = 'environment';
    let scannerMode = 'quagga';
    let quaggaInitialized = false;
    let isTorchOn = false;
    let track = null;
    let nativeStream = null;
    const beep = document.getElementById('beepSound');

    document.getElementById('tanggal').value = new Date().toISOString().split('T')[0];

    // ===== FORMAT RUPIAH =====
    const formatRp = v => { let n = v.replace(/[^0-9]/g,''); return n ? new Intl.NumberFormat('id-ID').format(n) : ''; };
    const parseRp = v => parseInt(v.replace(/[^0-9]/g, '')) || 0;

    function hitungProfit() {
        let modal = parseRp(document.getElementById('modal').value);
        let jual = parseRp(document.getElementById('harga_jual').value);
        let wrap = document.getElementById('profitWrap');
        let txt = document.getElementById('profitTxt');
        if(modal > 0 && jual > 0) {
            let profit = jual - modal;
            wrap.style.display = 'block';
            txt.innerHTML = `Estimasi Profit: Rp ${new Intl.NumberFormat('id-ID').format(profit)}`;
            txt.className = profit < 0 ? 'profit-badge bg-danger text-white' :
                            profit < 50000 ? 'profit-badge bg-warning text-dark' : 'profit-badge bg-success text-white';
        } else { wrap.style.display = 'none'; }
    }
    ['modal','harga_jual'].forEach(id => {
        let el = document.getElementById(id);
        el.addEventListener('input', () => { el.value = formatRp(el.value); hitungProfit(); checkMergeIndicator(); });
    });

    // ===== TOAST =====
    function toast(msg, type='success') {
        let c = document.getElementById('toastContainer') || (()=>{ let d=document.createElement('div'); d.id='toastContainer'; d.className='toast-container'; document.body.appendChild(d); return d; })();
        let icon = type==='success' ? '<i class="fas fa-check-circle fs-5"></i>' : 
                   type==='danger' ? '<i class="fas fa-times-circle fs-5"></i>' : 
                   type==='warning' ? '<i class="fas fa-exclamation-circle fs-5"></i>' :
                   type==='merge' ? '<i class="fas fa-compress-arrows-alt fs-5"></i>' :
                   '<i class="fas fa-info-circle fs-5"></i>';
        let t = document.createElement('div'); 
        t.className = `toast-mini ${type}`;
        t.innerHTML = `${icon} <span>${msg}</span>`;
        c.appendChild(t);
        setTimeout(()=>{ t.style.opacity = '0'; t.style.transition='opacity 0.3s'; setTimeout(()=>t.remove(), 300); }, 3000);
    }

    // ===== UPLOAD FOTO =====
    const fileInput = document.getElementById('fileInput'),
          imgPreview = document.getElementById('imagePreview'),
          btnRemove = document.getElementById('btnRemoveImage');
    function handleFile(f) {
        if (f.size > 2000000) { toast("Ukuran foto maksimal 2MB", "danger"); return false; }
        let reader = new FileReader();
        reader.onload = e => { imgPreview.innerHTML = `<img src="${e.target.result}">`; btnRemove.style.display='block'; };
        reader.readAsDataURL(f); imageFile = f; return true;
    }
    fileInput.onchange = e => { if(e.target.files[0]) handleFile(e.target.files[0]); };
    document.getElementById('btnGallery').onclick = ()=> fileInput.click();
    btnRemove.onclick = () => { imgPreview.innerHTML='<i class="fas fa-camera text-muted fs-4"></i>'; fileInput.value=''; imageFile=null; btnRemove.style.display='none'; };

    // ===== MASTER CASCADE DROPDOWN =====
    function getSelectedLabel(selectEl) {
        if (!selectEl || !selectEl.value) return '';
        const opt = selectEl.options[selectEl.selectedIndex];
        return (opt.dataset.label || opt.textContent || '').trim();
    }
    function buildNamaBarang() {
        const merkLabel = getSelectedLabel(document.getElementById('merk'));
        const barangLabel = getSelectedLabel(document.getElementById('namaBarang'));
        return `${merkLabel} ${barangLabel}`.trim().toUpperCase();
    }
    function resetSelect(selectEl, placeholder, disabled = true) {
        selectEl.innerHTML = `<option value="">${placeholder}</option>`;
        selectEl.disabled = disabled;
        selectEl.value = '';
    }
    function fillSelect(selectEl, items, placeholder, valueKey = 'value') {
        resetSelect(selectEl, placeholder, false);
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[valueKey] || item.nama_barang || item.value;
            opt.dataset.label = item.nama_barang || item.value || item[valueKey];
            opt.textContent = item.nama_barang || item.value || item[valueKey];
            selectEl.appendChild(opt);
        });
    }
    async function loadMasterBarang(merkId) {
        const namaBarangEl = document.getElementById('namaBarang');
        resetSelect(namaBarangEl, '-- Pilih Nama Barang --');
        resetSelect(document.getElementById('kondisi'), '-- Pilih Kondisi --');
        resetSelect(document.getElementById('internal'), '-- Pilih Storage --');
        resetSelect(document.getElementById('warna'), '-- Pilih Warna --');
        delete namaBarangEl.dataset.barangId;
        if (!merkId) return;
        const res = await fetch(`../api/get_master_barang.php?merk_id=${merkId}`);
        const json = await res.json();
        if (json.status !== 'success') return;
        resetSelect(namaBarangEl, '-- Pilih Nama Barang --', false);
        json.data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.nama_barang;
            opt.dataset.label = item.nama_barang;
            opt.dataset.barangId = item.id;
            opt.textContent = item.nama_barang;
            namaBarangEl.appendChild(opt);
        });
        checkMergeIndicator();
    }
    async function loadVarianLevel(level) {
        const barangId = document.getElementById('namaBarang').dataset.barangId || '';
        const kondisi = document.getElementById('kondisi').value;
        const internal = document.getElementById('internal').value;
        if (!barangId) return;
        if (level === 'kondisi') { resetSelect(document.getElementById('internal'), '-- Pilih Storage --'); resetSelect(document.getElementById('warna'), '-- Pilih Warna --'); }
        else if (level === 'storage') { resetSelect(document.getElementById('warna'), '-- Pilih Warna --'); }
        let url = `../api/get_master_varian.php?barang_id=${barangId}&level=${level}`;
        if (level === 'storage') url += `&kondisi=${encodeURIComponent(kondisi)}`;
        if (level === 'warna') url += `&kondisi=${encodeURIComponent(kondisi)}&internal_storage=${encodeURIComponent(internal)}`;
        const res = await fetch(url);
        const json = await res.json();
        if (json.status !== 'success') return;
        if (level === 'kondisi') fillSelect(document.getElementById('kondisi'), json.data, '-- Pilih Kondisi --');
        if (level === 'storage') fillSelect(document.getElementById('internal'), json.data, '-- Pilih Storage --');
        if (level === 'warna') fillSelect(document.getElementById('warna'), json.data, '-- Pilih Warna --');
        checkMergeIndicator();
    }
    
    document.getElementById('merk').addEventListener('change', function() { loadMasterBarang(this.value); });
    document.getElementById('namaBarang').addEventListener('change', async function() {
        const selected = this.options[this.selectedIndex];
        this.dataset.barangId = selected?.dataset?.barangId || '';
        resetSelect(document.getElementById('kondisi'), '-- Pilih Kondisi --');
        resetSelect(document.getElementById('internal'), '-- Pilih Storage --');
        resetSelect(document.getElementById('warna'), '-- Pilih Warna --');
        if (!this.dataset.barangId) return;
        await loadVarianLevel('kondisi');
    });
    document.getElementById('kondisi').addEventListener('change', () => { loadVarianLevel('storage'); checkMergeIndicator(); });
    document.getElementById('internal').addEventListener('change', () => { loadVarianLevel('warna'); checkMergeIndicator(); });
    document.getElementById('warna').addEventListener('change', () => checkMergeIndicator());
    document.getElementById('supplier').addEventListener('change', () => checkMergeIndicator());
    document.getElementById('keterangan').addEventListener('input', () => checkMergeIndicator());

    // ===== NEW: AUTO-MERGE INDICATOR =====
    function checkMergeIndicator() {
        const indicator = document.getElementById('mergeIndicator');
        const textEl = document.getElementById('mergeIndicatorText');
        const subtextEl = document.getElementById('mergeIndicatorSubtext');
        
        const currentSpec = getCurrentSpec();
        if (!currentSpec) {
            indicator.classList.remove('show');
            return;
        }
        
        const existingIndex = findExistingGroup(currentSpec);
        if (existingIndex !== -1) {
            const existingGroup = batchGroups[existingIndex];
            const imeiCount = existingGroup.imei_list.length;
            textEl.innerHTML = `<i class="fas fa-compress-arrows-alt me-1"></i> Spesifikasi cocok dengan Grup #${existingIndex + 1}`;
            subtextEl.innerHTML = `${existingGroup.spesifikasi.nama} · ${imeiCount} IMEI sudah ada · IMEI baru akan digabungkan`;
            indicator.classList.add('show');
        } else {
            indicator.classList.remove('show');
        }
    }
    
    function getCurrentSpec() {
        const merk = document.getElementById('merk').value;
        const namaBarang = document.getElementById('namaBarang').value;
        const kondisi = document.getElementById('kondisi').value;
        const internal = document.getElementById('internal').value;
        const warna = document.getElementById('warna').value;
        const supplier = document.getElementById('supplier').value.trim();
        const keterangan = document.getElementById('keterangan').value.trim();
        const modal = parseRp(document.getElementById('modal').value);
        const harga_jual = parseRp(document.getElementById('harga_jual').value);
        
        if (!merk || !namaBarang || !kondisi || !internal || !warna || !supplier || modal === 0) {
            return null;
        }
        
        return {
            nama: buildNamaBarang(),
            tipe: kondisi.toUpperCase(),
            warna: warna.toUpperCase(),
            internal: internal.toUpperCase(),
            supplier: supplier.toUpperCase(),
            keterangan,
            modal,
            harga_jual
        };
    }
    
    function findExistingGroup(spec) {
        return batchGroups.findIndex(g => {
            const s = g.spesifikasi;
            return s.nama === spec.nama &&
                   s.tipe === spec.tipe &&
                   s.warna === spec.warna &&
                   s.internal === spec.internal &&
                   s.supplier === spec.supplier &&
                   s.keterangan === spec.keterangan &&
                   s.modal === spec.modal &&
                   s.harga_jual === spec.harga_jual;
        });
    }

    // ===== FUNGSI GRUP IMEI =====
    function tambahImeiKeGrup() {
        const imeiInput = document.getElementById('scanInput');
        let imei = imeiInput.value.trim();
        if (!imei || !/^[0-9]{15}$/.test(imei)) {
            return toast('IMEI harus 15 digit angka', 'danger');
        }
        if (currentImeis.includes(imei)) {
            return toast('IMEI sudah ada di grup ini', 'warning');
        }
        
        fetch(`../api/cek_imei.php?imei=${imei}`).then(res=>res.json()).then(data=>{
            if(data.exists) toast(`⚠️ IMEI ${imei} sudah ada di database!`, 'warning');
        }).catch(()=>{});
        
        currentImeis.push(imei);
        renderCurrentImeis();
        imeiInput.value = '';
        imeiInput.focus();
        checkMergeIndicator();
    }

    function renderCurrentImeis() {
        const container = document.getElementById('currentImeiList');
        if (!currentImeis.length) {
            container.innerHTML = '<div class="text-center text-muted py-2 small"><i class="fas fa-box-open fs-5 mb-1" style="opacity:0.5;"></i><br>Belum ada IMEI</div>';
            return;
        }
        container.innerHTML = currentImeis.map((v,i) => 
            `<span class="imei-chip"><i class="fas fa-barcode"></i> ${v} <i class="fas fa-times-circle btn-del" onclick="hapusImeiDariGrup(${i})"></i></span>`
        ).join('');
    }

    window.hapusImeiDariGrup = (index) => {
        currentImeis.splice(index,1);
        renderCurrentImeis();
    };

    function resetCurrentGroup() {
        currentImeis = [];
        renderCurrentImeis();
        document.getElementById('scanInput').value = '';
        document.getElementById('scanInput').focus();
        document.getElementById('mergeIndicator').classList.remove('show');
    }
    
    function resetAllInputs() {
        resetCurrentGroup();
        document.getElementById('merk').value = '';
        resetSelect(document.getElementById('namaBarang'), '-- Pilih Nama Barang --');
        resetSelect(document.getElementById('kondisi'), '-- Pilih Kondisi --');
        resetSelect(document.getElementById('internal'), '-- Pilih Storage --');
        resetSelect(document.getElementById('warna'), '-- Pilih Warna --');
        document.getElementById('supplier').value = '';
        document.getElementById('modal').value = '';
        document.getElementById('harga_jual').value = '';
        document.getElementById('keterangan').value = '';
        document.getElementById('tanggal').value = new Date().toISOString().split('T')[0];
        document.getElementById('profitWrap').style.display = 'none';
        document.getElementById('mergeIndicator').classList.remove('show');
        toast('Semua input telah direset', 'warning');
    }

    // ===== MODIFIED: ADD GROUP TO BATCH WITH AUTO-MERGE =====
    function addGroupToBatch() {
        if (!currentImeis.length) {
            return toast('Tambahkan minimal 1 IMEI ke grup', 'danger');
        }

        const currentSpec = getCurrentSpec();
        if (!currentSpec) {
            return toast('Lengkapi semua spesifikasi grup!', 'danger');
        }

        const tanggal = document.getElementById('tanggal').value;
        currentSpec.tanggal = tanggal;

        const existingGroupIndex = findExistingGroup(currentSpec);

        if (existingGroupIndex !== -1) {
            const existingGroup = batchGroups[existingGroupIndex];
            let addedCount = 0;
            let skippedCount = 0;
            
            currentImeis.forEach(imei => {
                if (!existingGroup.imei_list.includes(imei)) {
                    existingGroup.imei_list.push(imei);
                    addedCount++;
                } else {
                    skippedCount++;
                }
            });
            
            renderBatchGroups();
            resetCurrentGroup();
            
            let message = `${addedCount} IMEI digabungkan ke Grup #${existingGroupIndex + 1} (${currentSpec.nama})`;
            if (skippedCount > 0) message += ` · ${skippedCount} IMEI duplikat dilewati`;
            
            toast(message, 'merge');
        } else {
            const group = {
                spesifikasi: currentSpec,
                imei_list: [...currentImeis]
            };
            batchGroups.push(group);
            renderBatchGroups();
            resetCurrentGroup();
            toast(`Grup baru dibuat: ${currentSpec.nama} (${group.imei_list.length} IMEI)`, 'success');
        }
    }

    function renderBatchGroups() {
        const container = document.getElementById('batchGroupsContainer');
        if (!batchGroups.length) {
            container.innerHTML = '<div class="text-center text-muted py-3 small"><i class="fas fa-folder-open fs-4 mb-1" style="opacity:0.5;"></i><br>Belum ada grup ditambahkan</div>';
            return;
        }
        container.innerHTML = batchGroups.map((g, idx) => {
            const s = g.spesifikasi;
            const imeiCount = g.imei_list.length;
            return `
            <div class="border rounded-3 p-3 mb-2 bg-white shadow-sm">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-primary rounded-pill">#${idx + 1}</span>
                            <strong style="font-size:0.9rem;">${s.nama}</strong>
                        </div>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="badge bg-secondary">${s.tipe}</span>
                            <span class="badge bg-info text-dark">${s.internal}</span>
                            <span class="badge bg-light text-dark border">${s.warna}</span>
                        </div>
                        <div class="small text-muted mb-2">
                            <i class="fas fa-truck me-1"></i>${s.supplier} · 
                            <i class="fas fa-money-bill ms-2 me-1"></i>Modal: Rp ${s.modal.toLocaleString('id-ID')} · 
                            <i class="fas fa-tag ms-2 me-1"></i>Jual: Rp ${s.harga_jual.toLocaleString('id-ID')}
                        </div>
                        <div class="small text-muted">
                            <i class="fas fa-barcode me-1" style="color:#10B981;"></i>
                            <strong style="color:#10B981;">${imeiCount} IMEI</strong>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-danger rounded-pill ms-2" onclick="hapusGrup(${idx})" title="Hapus grup"><i class="fas fa-trash"></i></button>
                </div>
                <div class="d-flex flex-wrap gap-1 pt-2 border-top">
                    ${g.imei_list.map(imei => `<span class="imei-chip small" style="font-size:0.7rem; padding:4px 8px;">${imei}</span>`).join('')}
                </div>
            </div>`;
        }).join('');
    }

    window.hapusGrup = (index) => {
        if (confirm('Hapus grup ini?')) {
            batchGroups.splice(index, 1);
            renderBatchGroups();
            checkMergeIndicator();
        }
    };

    function clearAllGroups() {
        if (batchGroups.length && confirm('Hapus semua grup?')) {
            batchGroups = [];
            renderBatchGroups();
            checkMergeIndicator();
        }
    }

    // ===== SIMPAN SEMUA GRUP =====
    async function simpanSemuaGroup() {
        if (!batchGroups.length) {
            return toast('Belum ada grup untuk disimpan', 'danger');
        }

        const payload = {
            groups: batchGroups,
            keterangan_global: ''
        };

        const fd = new FormData();
        fd.append('payload', JSON.stringify(payload));
        if (imageFile) fd.append('gambar', imageFile);

        const totalImei = batchGroups.reduce((sum, g) => sum + g.imei_list.length, 0);
        if (!confirm(`Simpan ${totalImei} barang dari ${batchGroups.length} grup?`)) return;

        document.getElementById('loadingOverlay').style.display = 'flex';
        try {
            let res = await fetch('../api/insert_barang_multi_group.php', { method: 'POST', body: fd });
            let data = await res.json();
            document.getElementById('loadingOverlay').style.display = 'none';
            if (data.status === 'success') {
                toast(data.msg || 'Semua data berhasil disimpan!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                toast(data.msg || 'Gagal menyimpan', 'danger');
            }
        } catch (e) {
            document.getElementById('loadingOverlay').style.display = 'none';
            toast('Koneksi error: ' + e.message, 'danger');
        }
    }

    // ===== SCANNER LOGIC =====
    function canUseBarcodeDetector() { return ('BarcodeDetector' in window) && ('getUserMedia' in navigator.mediaDevices); }
    async function updateTorchAvailability() {
        let videoTrack = null;
        if (scannerMode === 'native' && nativeStream) videoTrack = nativeStream.getVideoTracks()[0];
        else if (scannerMode === 'quagga' && quaggaInitialized) {
            const stream = Quagga.CameraAccess.getActiveStream();
            if (stream) videoTrack = stream.getVideoTracks()[0];
        }
        const torchBtn = document.getElementById('torchBtn');
        if (videoTrack) {
            track = videoTrack;
            try { const capabilities = track.getCapabilities(); torchBtn.style.display = capabilities.torch ? 'flex' : 'none'; } catch(e) { torchBtn.style.display = 'none'; }
        } else { torchBtn.style.display = 'none'; }
    }
    function toggleTorch() {
        if (!track) return;
        isTorchOn = !isTorchOn;
        track.applyConstraints({ advanced: [{ torch: isTorchOn }] }).catch(err => { toast('Gagal senter', 'danger'); isTorchOn = false; });
        const icon = document.querySelector('#torchBtn i');
        if (isTorchOn) { icon.classList.replace('fa-bolt', 'fa-bolt-slash'); document.getElementById('torchBtn').style.background = 'rgba(255,255,255,0.5)'; }
        else { icon.classList.replace('fa-bolt-slash', 'fa-bolt'); document.getElementById('torchBtn').style.background = 'rgba(255,255,255,0.2)'; }
    }
    async function startNativeScanner(facing) {
        const readerElement = document.getElementById('reader');
        readerElement.innerHTML = '';
        const video = document.createElement('video');
        video.setAttribute('autoplay', ''); video.setAttribute('playsinline', ''); video.setAttribute('muted', '');
        video.style.width = '100%'; video.style.height = '100%'; video.style.objectFit = 'cover';
        readerElement.appendChild(video);
        const constraints = { video: { facingMode: facing, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false };
        nativeStream = await navigator.mediaDevices.getUserMedia(constraints);
        video.srcObject = nativeStream; await video.play();
        const barcodeDetector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'code_39', 'upc_a'] });
        const detectLoop = async () => {
            if (!nativeStream || !video.readyState) return;
            try {
                const barcodes = await barcodeDetector.detect(video);
                if (barcodes.length > 0 && !scanCooldown) {
                    const code = barcodes[0].rawValue;
                    if (code !== lastScan) {
                        scanCooldown = true; lastScan = code;
                        beep.play().catch(() => {});
                        document.getElementById('scannerModal').classList.add('scanner-success');
                        setTimeout(() => {
                            document.getElementById('scanInput').value = code;
                            tambahImeiKeGrup();
                            closeScanner();
                            setTimeout(() => { scanCooldown = false; lastScan = ""; }, 2000);
                        }, 400);
                        return;
                    }
                }
            } catch (err) {}
            if (nativeStream) requestAnimationFrame(detectLoop);
        };
        detectLoop();
    }
    function stopNativeScanner() {
        if (nativeStream) { nativeStream.getTracks().forEach(t => t.stop()); nativeStream = null; }
        document.getElementById('reader').innerHTML = '';
    }
    function startQuagga(facing) {
        if (quaggaInitialized) { Quagga.stop(); Quagga.offDetected(); quaggaInitialized = false; }
        Quagga.init({
            inputStream: { name: "Live", type: "LiveStream", target: document.querySelector('#reader'), constraints: { facingMode: facing }, area: { top: "25%", right: "15%", left: "15%", bottom: "30%" } },
            decoder: { readers: ["code_128_reader", "ean_reader", "ean_8_reader", "code_39_reader", "upc_reader"] },
            locator: { patchSize: "medium", halfSample: false }, numOfWorkers: navigator.hardwareConcurrency || 4, frequency: 10, locate: true
        }, (err) => {
            if (err) { toast('Kamera tidak diizinkan', 'danger'); closeScanner(); return; }
            quaggaInitialized = true; Quagga.start();
        });
        Quagga.offDetected();
        Quagga.onDetected((result) => {
            let code = result.codeResult.code;
            if (!scanCooldown && code !== lastScan) {
                scanCooldown = true; lastScan = code;
                beep.play().catch(() => {});
                document.getElementById('scannerModal').classList.add('scanner-success');
                setTimeout(() => {
                    document.getElementById('scanInput').value = code;
                    tambahImeiKeGrup();
                    closeScanner();
                    setTimeout(() => { scanCooldown = false; lastScan = ""; }, 2000);
                }, 400);
            }
        });
    }
    async function startScanner(facing) {
        if (scannerMode === 'native' && nativeStream) stopNativeScanner();
        else if (scannerMode === 'quagga' && quaggaInitialized) { Quagga.stop(); Quagga.offDetected(); quaggaInitialized = false; }
        if (canUseBarcodeDetector()) {
            try { await startNativeScanner(facing); scannerMode = 'native'; } catch (e) { startQuagga(facing); scannerMode = 'quagga'; }
        } else { startQuagga(facing); scannerMode = 'quagga'; }
        await updateTorchAvailability();
    }
    function openScanner() {
        document.getElementById('scannerModal').style.display = 'block';
        document.getElementById('scannerModal').classList.remove('scanner-success');
        scanCooldown = false; lastScan = ""; isTorchOn = false;
        const torchIcon = document.querySelector('#torchBtn i');
        if(torchIcon) { torchIcon.classList.remove('fa-bolt-slash'); torchIcon.classList.add('fa-bolt'); document.getElementById('torchBtn').style.background = 'rgba(255,255,255,0.2)'; }
        startScanner(currentFacing);
    }
    function closeScanner() {
        document.getElementById('scannerModal').style.display = 'none';
        if (track && isTorchOn) track.applyConstraints({ advanced: [{ torch: false }] }).catch(() => {});
        if (scannerMode === 'native') stopNativeScanner();
        else if (scannerMode === 'quagga' && quaggaInitialized) { Quagga.stop(); Quagga.offDetected(); quaggaInitialized = false; }
        track = null; isTorchOn = false;
    }
    function switchCamera() {
        currentFacing = (currentFacing === 'environment') ? 'user' : 'environment';
        if (track && isTorchOn) { track.applyConstraints({ advanced: [{ torch: false }] }).catch(() => {}); isTorchOn = false; document.getElementById('torchBtn').style.background = 'rgba(255,255,255,0.2)'; }
        startScanner(currentFacing);
    }

    // Bottom nav popup
    const moreBtn = document.getElementById('moreMenuBtn'), popup = document.getElementById('moreMenuPopup'), overlay = document.getElementById('popupOverlay');
    if(moreBtn) { moreBtn.addEventListener('click', function(e) { e.stopPropagation(); popup.classList.toggle('show'); overlay.classList.toggle('show'); }); }
    if(overlay) { overlay.addEventListener('click', function() { popup.classList.remove('show'); overlay.classList.remove('show'); }); }
    document.addEventListener('click', function(e) { if (popup && !popup.contains(e.target) && e.target !== moreBtn && !moreBtn.contains(e.target)) { popup.classList.remove('show'); overlay.classList.remove('show'); }});

    // Enter di scan input
    document.getElementById('scanInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); tambahImeiKeGrup(); }
    });

    window.onload = () => { document.getElementById('scanInput').focus(); };
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>