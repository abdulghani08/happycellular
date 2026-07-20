<?php
session_start();
require_once '../api/auth_helper.php';
restrictTo(['admin']);
require_once '../connection.php';

// ── Master lists ──────────────────────────────────────────────
$merkList = [];
$qMerk = mysqli_query($koneksi, "SELECT * FROM master_merk ORDER BY nama_merk ASC");
while ($r = mysqli_fetch_assoc($qMerk)) $merkList[] = $r;

$storageList = [];
$qStorage = mysqli_query($koneksi, "SELECT id, nilai, urutan FROM master_storage WHERE status='aktif' ORDER BY urutan ASC, nilai ASC");
while ($r = mysqli_fetch_assoc($qStorage)) $storageList[] = $r;

$warnaList = [];
$qWarna = mysqli_query($koneksi, "SELECT id, nama_warna FROM master_warna WHERE aktif=1 ORDER BY nama_warna ASC");
while ($r = mysqli_fetch_assoc($qWarna)) $warnaList[] = $r;

// Semua storage & warna (termasuk nonaktif) untuk tab kelola
$allStorageList = [];
$qAS = mysqli_query($koneksi, "SELECT * FROM master_storage ORDER BY urutan ASC, nilai ASC");
while ($r = mysqli_fetch_assoc($qAS)) $allStorageList[] = $r;

$allWarnaList = [];
$qAW = mysqli_query($koneksi, "SELECT * FROM master_warna ORDER BY nama_warna ASC");
while ($r = mysqli_fetch_assoc($qAW)) $allWarnaList[] = $r;

// Supplier list
$supplierList = [];
$qSupp = mysqli_query($koneksi, "SELECT * FROM master_supplier ORDER BY nama_supplier ASC");
while ($r = mysqli_fetch_assoc($qSupp)) $supplierList[] = $r;

$tab = $_GET['tab'] ?? 'merk';
$selectedMerk   = (int)($_GET['merk_id']   ?? 0);
$selectedBarang = (int)($_GET['barang_id'] ?? 0);

// Barang list jika ada filter merk
$barangList = [];
if ($selectedMerk > 0) {
    $stmt = $koneksi->prepare("SELECT mb.*, mm.nama_merk FROM master_barang mb JOIN master_merk mm ON mb.merk_id=mm.id WHERE mb.merk_id=? ORDER BY mb.nama_barang ASC");
    $stmt->bind_param('i', $selectedMerk);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $barangList[] = $r;
    $stmt->close();
}

// Varian list jika ada filter barang
$varianList = [];
if ($selectedBarang > 0) {
    $stmt = $koneksi->prepare("SELECT mv.*, mb.nama_barang FROM master_varian_barang mv JOIN master_barang mb ON mv.barang_id=mb.id WHERE mv.barang_id=? ORDER BY mv.kondisi, mv.internal_storage, mv.warna ASC");
    $stmt->bind_param('i', $selectedBarang);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $varianList[] = $r;
    $stmt->close();
}

$kondisiOptions = ['New', 'Second', 'Like New', 'Ex Inter', 'Beacukai'];

// JSON untuk JS
$merkJson    = json_encode($merkList);
$storageJson = json_encode($storageList);
$warnaJson   = json_encode($warnaList);
$supplierJson = json_encode($supplierList);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Master · Happy Cellular</title>
    <link rel="shortcut icon" href="../asset/Happy.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F4F7FA;
            --surface-card: #FFFFFF;
            --primary: #1E4A7A;
            --primary-soft: #EEF4FA;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --text-main: #1E293B;
            --text-muted: #64748B;
            --bottom-nav-height: 72px;
            --sidebar-width: 260px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg-body); min-height:100vh; color:var(--text-main); padding-bottom:calc(var(--bottom-nav-height) + 2rem); }
        /* Navbar */
        .navbar-top { background:var(--surface-card); padding:1.2rem 1.5rem 0.8rem; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:1000; box-shadow:0 2px 10px rgba(0,0,0,0.04); }
        .logo-text { font-weight:800; font-size:1.3rem; color:var(--primary); letter-spacing:-0.5px; }
        .logo-text small { font-size:0.75rem; font-weight:500; color:var(--text-muted); display:block; margin-top:-2px; }
        .navbar-logo { width:32px; height:32px; object-fit:contain; border-radius:8px; }
        .user-badge { background:var(--primary-soft); padding:8px 16px; border-radius:20px; color:var(--primary); font-weight:700; font-size:0.8rem; display:flex; align-items:center; gap:6px; border:none; cursor:pointer; }
        .dropdown-menu-custom { border-radius:16px; box-shadow:0 10px 25px rgba(30,41,59,0.1); border:1px solid rgba(0,0,0,0.05); padding:8px; }
        .dropdown-menu-custom .dropdown-item { font-size:0.85rem; font-weight:600; color:var(--text-main); padding:10px 14px; border-radius:10px; display:flex; align-items:center; gap:10px; }
        .dropdown-menu-custom .dropdown-item i { color:var(--primary); font-size:0.95rem; width:16px; text-align:center; }
        .dropdown-menu-custom .dropdown-item:hover { background:var(--primary-soft); color:var(--primary); }
        /* Layout */
        .main-wrapper { max-width:1100px; margin:0 auto; padding:1rem 1.2rem; }
        .card-custom { background:var(--surface-card); border-radius:20px; padding:1.5rem; margin-bottom:1.2rem; box-shadow:0 4px 18px rgba(0,0,0,0.03); }
        .card-title-custom { font-size:0.9rem; font-weight:800; color:var(--primary); letter-spacing:.5px; text-transform:uppercase; margin-bottom:1rem; border-bottom:1px solid #F1F5F9; padding-bottom:12px; display:flex; justify-content:space-between; align-items:center; }
        /* Tabs */
        .nav-tabs-custom { border:none; gap:6px; margin-bottom:1.2rem; flex-wrap:wrap; }
        .nav-tabs-custom .nav-link { border:none; border-radius:12px; font-weight:700; font-size:0.82rem; color:var(--text-muted); padding:9px 16px; background:#F1F5F9; transition:.15s; }
        .nav-tabs-custom .nav-link.active { background:var(--primary); color:#fff; }
        .nav-tabs-custom .nav-link:hover:not(.active) { background:#E2E8F0; }
        /* Table */
        .table-responsive-stack { width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; }
        .table-responsive-stack table { min-width:700px; width:max-content; }
        .table-custom { font-size:0.85rem; width:100%; border-collapse:separate; border-spacing:0; }
        .table-custom thead th { background:var(--primary); color:white; font-weight:600; font-size:0.73rem; text-transform:uppercase; letter-spacing:0.5px; padding:0.8rem 0.8rem; border:none; }
        .table-custom thead th:first-child { border-radius:12px 0 0 0; }
        .table-custom thead th:last-child { border-radius:0 12px 0 0; }
        .table-custom tbody td { padding:0.8rem 0.8rem; vertical-align:middle; font-size:0.85rem; border-bottom:1px solid #F1F5F9; background:white; }
        .table-custom tbody tr:last-child td:first-child { border-radius:0 0 0 12px; }
        .table-custom tbody tr:last-child td:last-child { border-radius:0 0 12px 0; }
        .badge-aktif { background:#DCFCE7; color:#166534; }
        .badge-nonaktif { background:#FEE2E2; color:#991B1B; }
        .badge-status { cursor:pointer; transition:.15s; }
        .badge-status:hover { opacity:.8; }
        .btn-action { border-radius:8px; font-size:0.75rem; font-weight:700; padding:4px 10px; }
        /* Filter */
        .filter-bar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:1rem; align-items:center; }
        .filter-bar select, .filter-bar input { border-radius:12px; font-weight:600; font-size:0.85rem; max-width:300px; }
        /* Chip selector */
        .chip-selector { display:flex; flex-wrap:wrap; gap:8px; }
        .chip-item { display:none; }
        .chip-label { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:30px; font-size:0.8rem; font-weight:700; background:#F1F5F9; color:var(--text-muted); cursor:pointer; border:2px solid transparent; transition:.15s; user-select:none; }
        .chip-item:checked + .chip-label { background:var(--primary-soft); color:var(--primary); border-color:var(--primary); }
        /* Storage & Warna cards */
        .option-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:10px; }
        .option-card { background:#F8FAFC; border:1.5px solid #E2E8F0; border-radius:14px; padding:14px 12px; display:flex; flex-direction:column; align-items:center; gap:8px; transition:.15s; }
        .option-card:hover { border-color:var(--primary); background:var(--primary-soft); }
        .option-card .opt-label { font-weight:800; font-size:0.95rem; color:var(--text-main); }
        .option-card .opt-actions { display:flex; gap:6px; }
        .color-dot { width:28px; height:28px; border-radius:50%; border:2px solid #E2E8F0; margin-bottom:2px; }
        /* Toast */
        .toast-container { position:fixed; top:20px; left:50%; transform:translateX(-50%); z-index:9999; }
        .toast-mini { background:var(--primary); color:#fff; border-radius:30px; padding:12px 24px; font-weight:600; font-size:.9rem; display:flex; align-items:center; gap:10px; margin-bottom:10px; box-shadow:0 10px 25px rgba(0,0,0,.2); animation:slideDown .3s ease; }
        @keyframes slideDown { from{transform:translateY(-20px);opacity:0} to{transform:translateY(0);opacity:1} }
        /* Bottom Nav */
        .bottom-nav { position:fixed; bottom:0; left:0; right:0; height:var(--bottom-nav-height); background:#fff; display:flex; justify-content:space-around; align-items:center; box-shadow:0 -4px 20px rgba(0,0,0,.05); z-index:1000; }
        .nav-item { display:flex; flex-direction:column; align-items:center; gap:4px; text-decoration:none; color:var(--text-muted); font-size:.65rem; font-weight:700; flex:1; }
        .nav-item i { font-size:1.2rem; }
        .nav-item.active { color:var(--primary); }
        .more-menu-popup { position:fixed; bottom:calc(var(--bottom-nav-height)+12px); left:50%; transform:translateX(-50%) scale(.95); width:calc(100% - 2.4rem); max-width:500px; background:#fff; border-radius:20px; box-shadow:0 15px 35px rgba(30,41,59,.15); padding:10px; display:none; grid-template-columns:repeat(2,1fr); gap:8px; z-index:1100; opacity:0; transition:all .2s; }
        .more-menu-popup.show { display:grid; transform:translateX(-50%) scale(1); opacity:1; }
        .more-menu-popup a { padding:12px; text-decoration:none; color:var(--text-main); font-weight:600; font-size:.8rem; border-radius:12px; display:flex; align-items:center; gap:10px; background:#F8FAFC; }
        .more-menu-popup a i { color:var(--primary); }
        .popup-overlay { position:fixed; inset:0; background:rgba(0,0,0,.3); z-index:1050; display:none; }
        .popup-overlay.show { display:block; }
        /* Desktop Sidebar */
        .desktop-sidebar { display:none; }
        @media (min-width:992px) {
            body { display:flex; padding-bottom:0; }
            .bottom-nav,.popup-overlay,.more-menu-popup { display:none !important; }
            .desktop-sidebar { display:flex; flex-direction:column; width:var(--sidebar-width); background:#fff; height:100vh; position:sticky; top:0; border-right:1px solid #E2E8F0; padding:1.5rem; z-index:1010; }
            .sidebar-brand { display:flex; align-items:center; gap:10px; margin-bottom:2rem; padding-bottom:1rem; border-bottom:1px solid #F1F5F9; }
            .sidebar-menu { display:flex; flex-direction:column; gap:5px; flex-grow:1; }
            .sidebar-link { display:flex; align-items:center; gap:12px; padding:12px 16px; color:var(--text-muted); text-decoration:none; font-weight:600; font-size:.85rem; border-radius:12px; }
            .sidebar-link i { font-size:1.1rem; width:20px; text-align:center; }
            .sidebar-link:hover,.sidebar-link.active { background:var(--primary-soft); color:var(--primary); }
            .sidebar-divider { height:1px; background:#F1F5F9; margin:10px 0; }
            .content-wrapper { flex-grow:1; min-width:0; }
            .main-wrapper { max-width:1100px; padding:1.5rem 2rem; margin:0; }
        }
        .total-bubble { background:var(--primary); color:#fff; border-radius:12px; padding:10px 16px; font-size:.85rem; font-weight:700; display:inline-flex; align-items:center; gap:8px; }
    </style>
</head>
<body>

<!-- Desktop Sidebar -->
<div class="desktop-sidebar">
    <div class="sidebar-brand">
        <img src="asset/Happy.png" alt="HC" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
        <div class="logo-text">Happy Cell<small>Admin Panel</small></div>
    </div>
    <div class="sidebar-menu">
        <a href="index.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="stock_in.php" class="sidebar-link"><i class="fas fa-arrow-circle-down" style="color:#10B981;"></i> Stock In</a>
        <a href="admin_stock_out.php" class="sidebar-link"><i class="fas fa-arrow-circle-up" style="color:#EF4444;"></i> Stock Out</a>
        <a href="return.php" class="sidebar-link"><i class="fas fa-rotate-left" style="color:#F59E0B;"></i> Retur</a>
        <div class="sidebar-divider"></div>
        <a href="data_master.php" class="sidebar-link active"><i class="fas fa-database"></i> Data Master</a>
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
        <div class="logo-area d-flex align-items-center gap-2">
            <img src="asset/Happy.png" alt="HC" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
            <div class="logo-text">Happy Cell<small>Data Master</small></div>
        </div>
        <div class="dropdown">
            <button class="user-badge" type="button" data-bs-toggle="dropdown"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom mt-2">
                <li><a class="dropdown-item" href="kelola_user.php"><i class="fas fa-users-cog"></i> Kelola User</a></li>
                <li><a class="dropdown-item" href="ganti_password.php"><i class="fas fa-key"></i> Ganti Password</a></li>
                <li><hr class="dropdown-divider" style="border-color:#EDF2F7;"></li>
                <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
            </ul>
        </div>
    </div>

    <div class="main-wrapper">
        <!-- Tabs -->
        <ul class="nav nav-tabs-custom d-flex">
            <li class="nav-item"><a class="nav-link <?= $tab==='merk'?'active':'' ?>" href="?tab=merk"><i class="fas fa-tags me-1"></i>Merk</a></li>
            <li class="nav-item"><a class="nav-link <?= $tab==='barang'?'active':'' ?>" href="?tab=barang"><i class="fas fa-mobile-alt me-1"></i>Barang</a></li>
            <li class="nav-item"><a class="nav-link <?= $tab==='varian'?'active':'' ?>" href="?tab=varian"><i class="fas fa-cubes me-1"></i>Varian</a></li>
            <li class="nav-item"><a class="nav-link <?= $tab==='opsi'?'active':'' ?>" href="?tab=opsi"><i class="fas fa-sliders-h me-1"></i>Storage & Warna</a></li>
            <li class="nav-item"><a class="nav-link <?= $tab==='supplier'?'active':'' ?>" href="?tab=supplier"><i class="fas fa-truck me-1"></i>Supplier</a></li>
        </ul>

        <?php if ($tab === 'merk'): ?>
        <!-- TAB MERK -->
        <div class="card-custom">
            <div class="card-title-custom">
                <span><i class="fas fa-tag me-2"></i>Daftar Merk</span>
                <button class="btn btn-sm btn-primary rounded-pill fw-bold px-3" style="background:var(--primary);border:none;" onclick="showAddMerk()"><i class="fas fa-plus me-1"></i>Tambah</button>
            </div>
            <div class="table-responsive-stack">
                <table class="table table-custom align-middle">
                    <thead><tr><th>Merk</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach ($merkList as $m): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($m['nama_merk']) ?></td>
                            <td><span class="badge rounded-pill badge-status <?= $m['status']==='aktif'?'badge-aktif':'badge-nonaktif' ?>" onclick="toggleStatus('merk',<?= $m['id'] ?>,'<?= $m['status']==='aktif'?'nonaktif':'aktif' ?>')"><?= $m['status'] ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-action btn-outline-primary" onclick="editMerk(<?= $m['id'] ?>,'<?= htmlspecialchars($m['nama_merk'],ENT_QUOTES) ?>')"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-action btn-outline-danger" onclick="deleteMerk(<?= $m['id'] ?>,'<?= htmlspecialchars($m['nama_merk'],ENT_QUOTES) ?>')"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'barang'): ?>
        <!-- TAB BARANG -->
        <div class="card-custom">
            <div class="card-title-custom">
                <span><i class="fas fa-box me-2"></i>Daftar Barang</span>
                <button class="btn btn-sm btn-primary rounded-pill fw-bold px-3" style="background:var(--primary);border:none;" onclick="showAddBarang()"><i class="fas fa-plus me-1"></i>Tambah</button>
            </div>
            <div class="filter-bar">
                <select class="form-select" id="filterMerkBarang" onchange="location.href='?tab=barang&merk_id='+this.value">
                    <option value="">-- Filter Merk --</option>
                    <?php foreach ($merkList as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $selectedMerk===$m['id']?'selected':'' ?>><?= htmlspecialchars($m['nama_merk']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($selectedMerk > 0): ?>
                <span class="badge bg-secondary rounded-pill"><?= count($barangList) ?> barang</span>
                <?php endif; ?>
            </div>
            <div class="table-responsive-stack">
                <table class="table table-custom align-middle">
                    <thead><tr><th>Merk</th><th>Nama Barang</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php if (empty($barangList)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block opacity-25"></i>Pilih merk untuk menampilkan barang.</td></tr>
                        <?php else: foreach ($barangList as $b): ?>
                        <tr>
                            <td><span class="badge rounded-pill" style="background:#EEF4FA;color:var(--primary);font-weight:700;"><?= htmlspecialchars($b['nama_merk']) ?></span></td>
                            <td class="fw-bold"><?= htmlspecialchars($b['nama_barang']) ?></td>
                            <td><span class="badge rounded-pill badge-status <?= $b['status']==='aktif'?'badge-aktif':'badge-nonaktif' ?>" onclick="toggleStatus('barang',<?= $b['id'] ?>,'<?= $b['status']==='aktif'?'nonaktif':'aktif' ?>')"><?= $b['status'] ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-action btn-outline-primary" onclick="editBarang(<?= $b['id'] ?>,'<?= htmlspecialchars($b['nama_barang'],ENT_QUOTES) ?>',<?= $b['merk_id'] ?>)"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-action btn-outline-danger" onclick="deleteBarang(<?= $b['id'] ?>,'<?= htmlspecialchars($b['nama_barang'],ENT_QUOTES) ?>')"><i class="fas fa-trash"></i></button>
                                <a href="?tab=varian&merk_id=<?= $b['merk_id'] ?>&barang_id=<?= $b['id'] ?>" class="btn btn-sm btn-action btn-outline-secondary" title="Lihat Varian"><i class="fas fa-cubes"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'varian'): ?>
        <!-- TAB VARIAN -->
        <div class="card-custom">
            <div class="card-title-custom">
                <span><i class="fas fa-cubes me-2"></i>Daftar Varian</span>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#massVarianModal"><i class="fas fa-layer-group me-1"></i>Massal</button>
                    <button class="btn btn-sm btn-primary rounded-pill fw-bold px-3" style="background:var(--primary);border:none;" onclick="showAddVarian()"><i class="fas fa-plus me-1"></i>Tambah</button>
                </div>
            </div>
            <div class="filter-bar">
                <select class="form-select" id="filterMerkVarian" onchange="loadBarangForFilter(this.value)">
                    <option value="">-- Pilih Merk --</option>
                    <?php foreach ($merkList as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $selectedMerk===$m['id']?'selected':'' ?>><?= htmlspecialchars($m['nama_merk']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select" id="filterBarangVarian" onchange="location.href='?tab=varian&merk_id=<?= $selectedMerk ?>&barang_id='+this.value">
                    <option value="">-- Pilih Barang --</option>
                    <?php foreach ($barangList as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $selectedBarang===$b['id']?'selected':'' ?>><?= htmlspecialchars($b['nama_barang']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($selectedBarang > 0): ?>
                <span class="badge bg-secondary rounded-pill"><?= count($varianList) ?> varian</span>
                <?php endif; ?>
            </div>
            <div class="table-responsive-stack">
                <table class="table table-custom align-middle">
                    <thead><tr><th>Barang</th><th>Kondisi</th><th>Storage</th><th>Warna</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php if (empty($varianList)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-box-open fa-2x mb-2 d-block opacity-25"></i>Pilih barang untuk menampilkan varian.</td></tr>
                        <?php else: foreach ($varianList as $v): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($v['nama_barang']) ?></td>
                            <td><span class="badge rounded-pill" style="background:#FEF9C3;color:#854D0E;font-weight:700;"><?= htmlspecialchars($v['kondisi']) ?></span></td>
                            <td><span class="badge rounded-pill" style="background:#EFF6FF;color:#1D4ED8;font-weight:700;"><?= htmlspecialchars($v['internal_storage']) ?></span></td>
                            <td><?= htmlspecialchars($v['warna']) ?></td>
                            <td><span class="badge rounded-pill badge-status <?= $v['status']==='aktif'?'badge-aktif':'badge-nonaktif' ?>" onclick="toggleStatus('varian',<?= $v['id'] ?>,'<?= $v['status']==='aktif'?'nonaktif':'aktif' ?>')"><?= $v['status'] ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-action btn-outline-primary" onclick="editVarian(<?= $v['id'] ?>,'<?= htmlspecialchars($v['kondisi'],ENT_QUOTES) ?>','<?= htmlspecialchars($v['internal_storage'],ENT_QUOTES) ?>','<?= htmlspecialchars($v['warna'],ENT_QUOTES) ?>',<?= $v['barang_id'] ?>)"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-action btn-outline-danger" onclick="deleteVarian(<?= $v['id'] ?>)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'opsi'): ?>
        <!-- TAB STORAGE & WARNA -->
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card-custom h-100">
                    <div class="card-title-custom">
                        <span><i class="fas fa-hdd me-2"></i>Master Storage</span>
                        <button class="btn btn-sm btn-primary rounded-pill fw-bold px-3" style="background:var(--primary);border:none;" onclick="showAddStorage()"><i class="fas fa-plus me-1"></i>Tambah</button>
                    </div>
                    <div class="option-grid">
                        <?php foreach ($allStorageList as $s): ?>
                        <div class="option-card <?= $s['status']!=='aktif'?'opacity-50':'' ?>">
                            <div class="opt-label"><?= htmlspecialchars($s['nilai']) ?></div>
                            <small class="text-muted">Urutan: <?= $s['urutan'] ?></small>
                            <div class="opt-actions">
                                <button class="btn btn-sm btn-outline-primary btn-action" onclick="editStorage(<?= $s['id'] ?>,'<?= htmlspecialchars($s['nilai'],ENT_QUOTES) ?>',<?= $s['urutan'] ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-danger btn-action" onclick="deleteStorage(<?= $s['id'] ?>,'<?= htmlspecialchars($s['nilai'],ENT_QUOTES) ?>')" title="Hapus"><i class="fas fa-trash"></i></button>
                            </div>
                            <span class="badge rounded-pill badge-status <?= $s['status']==='aktif'?'badge-aktif':'badge-nonaktif' ?>" onclick="toggleStorage(<?= $s['id'] ?>,'<?= $s['status']==='aktif'?'nonaktif':'aktif' ?>')"><?= $s['status'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card-custom h-100">
                    <div class="card-title-custom">
                        <span><i class="fas fa-palette me-2"></i>Master Warna</span>
                        <button class="btn btn-sm btn-primary rounded-pill fw-bold px-3" style="background:var(--primary);border:none;" onclick="showAddWarna()"><i class="fas fa-plus me-1"></i>Tambah</button>
                    </div>
                    <div class="option-grid">
                        <?php foreach ($allWarnaList as $w): ?>
                        <div class="option-card <?= !$w['aktif']?'opacity-50':'' ?>">
                            <div class="color-dot" style="background:<?= generateColorFromName($w['nama_warna']) ?>"></div>
                            <div class="opt-label" style="font-size:.8rem;text-align:center;"><?= htmlspecialchars($w['nama_warna']) ?></div>
                            <div class="opt-actions">
                                <button class="btn btn-sm btn-outline-primary btn-action" onclick="editWarna(<?= $w['id'] ?>,'<?= htmlspecialchars($w['nama_warna'],ENT_QUOTES) ?>')" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-danger btn-action" onclick="deleteWarna(<?= $w['id'] ?>,'<?= htmlspecialchars($w['nama_warna'],ENT_QUOTES) ?>')" title="Hapus"><i class="fas fa-trash"></i></button>
                            </div>
                            <span class="badge rounded-pill badge-status <?= $w['aktif']?'badge-aktif':'badge-nonaktif' ?>" onclick="toggleWarna(<?= $w['id'] ?>,<?= $w['aktif']?0:1 ?>)"><?= $w['aktif']?'aktif':'nonaktif' ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($tab === 'supplier'): ?>
        <!-- TAB SUPPLIER -->
        <div class="card-custom">
            <div class="card-title-custom">
                <span><i class="fas fa-truck me-2"></i>Daftar Supplier</span>
                <button class="btn btn-sm btn-primary rounded-pill fw-bold px-3" style="background:var(--primary);border:none;" onclick="showAddSupplier()"><i class="fas fa-plus me-1"></i>Tambah</button>
            </div>
            <div class="table-responsive-stack">
                <table class="table table-custom align-middle">
                    <thead><tr><th>Nama</th><th>Alamat</th><th>Telepon</th><th>Email</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach ($supplierList as $s): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($s['nama_supplier']) ?></td>
                            <td><?= htmlspecialchars($s['alamat'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($s['telepon'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($s['email'] ?: '-') ?></td>
                            <td><span class="badge rounded-pill badge-status <?= $s['status']==='aktif'?'badge-aktif':'badge-nonaktif' ?>" onclick="toggleSupplier(<?= $s['id'] ?>,'<?= $s['status']==='aktif'?'nonaktif':'aktif' ?>')"><?= $s['status'] ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-action btn-outline-primary" onclick="editSupplier(<?= $s['id'] ?>,'<?= htmlspecialchars($s['nama_supplier'],ENT_QUOTES) ?>','<?= htmlspecialchars($s['alamat'],ENT_QUOTES) ?>','<?= htmlspecialchars($s['telepon'],ENT_QUOTES) ?>','<?= htmlspecialchars($s['email'],ENT_QUOTES) ?>')"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-action btn-outline-danger" onclick="deleteSupplier(<?= $s['id'] ?>,'<?= htmlspecialchars($s['nama_supplier'],ENT_QUOTES) ?>')"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bottom Nav -->
<nav class="bottom-nav">
    <a href="index.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a href="stock_in.php" class="nav-item"><i class="fas fa-arrow-circle-down" style="color:#10B981;"></i><span>Stock In</span></a>
    <a href="admin_stock_out.php" class="nav-item"><i class="fas fa-arrow-circle-up" style="color:#EF4444;"></i><span>Stock Out</span></a>
    <a href="return.php" class="nav-item"><i class="fas fa-rotate-left" style="color:#F59E0B;"></i><span>Retur</span></a>
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

<!-- MODAL Tambah/Edit Umum -->
<div class="modal fade" id="masterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="masterModalLabel">Tambah</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="masterForm" onsubmit="return submitMasterForm(event)">
                <div class="modal-body pt-3">
                    <input type="hidden" name="id" id="formId">
                    <input type="hidden" name="action" id="formAction">
                    <div id="formFields"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 fw-bold px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 fw-bold px-4" style="background:var(--primary);border:none;">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL Tambah Varian Massal -->
<div class="modal fade" id="massVarianModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-layer-group me-2"></i>Tambah Varian Massal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4 p-3 rounded-3" style="background:#F8FAFC;border:1.5px solid #E2E8F0;">
                    <div class="fw-bold mb-2" style="color:var(--primary);"><i class="fas fa-mobile-alt me-2"></i>Langkah 1 — Pilih Barang</div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted mb-1">Merk</label>
                            <select class="form-select rounded-3 fw-semibold" id="massMerkSelect" onchange="loadBarangForMassal(this.value)">
                                <option value="">-- Pilih Merk --</option>
                                <?php foreach ($merkList as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_merk']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted mb-1">Nama Barang</label>
                            <select class="form-select rounded-3 fw-semibold" id="massBarangSelect" disabled>
                                <option value="">-- Pilih Barang --</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mb-4">
                    <div class="fw-bold mb-2" style="color:var(--primary);"><i class="fas fa-tag me-2"></i>Langkah 2 — Kondisi</div>
                    <div class="chip-selector">
                        <?php foreach ($kondisiOptions as $k): ?>
                        <input type="checkbox" class="chip-item mass-kondisi" id="mk_<?= str_replace(' ','_',$k) ?>" value="<?= $k ?>">
                        <label class="chip-label" for="mk_<?= str_replace(' ','_',$k) ?>"><i class="fas fa-check fa-xs"></i><?= $k ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mb-4">
                    <div class="fw-bold mb-2" style="color:var(--primary);"><i class="fas fa-hdd me-2"></i>Langkah 3 — Storage</div>
                    <div class="chip-selector">
                        <?php foreach ($storageList as $s): ?>
                        <input type="checkbox" class="chip-item mass-storage" id="ms_<?= $s['id'] ?>" value="<?= htmlspecialchars($s['nilai']) ?>">
                        <label class="chip-label" for="ms_<?= $s['id'] ?>"><i class="fas fa-check fa-xs"></i><?= htmlspecialchars($s['nilai']) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2"><small class="text-muted fw-semibold">Storage tidak ada di daftar? </small><input type="text" id="massStorageCustom" class="form-control form-control-sm d-inline-block mt-1 rounded-3 fw-semibold" style="max-width:250px;" placeholder="Ketik manual, pisah koma"></div>
                </div>
                <div class="mb-4">
                    <div class="fw-bold mb-2" style="color:var(--primary);"><i class="fas fa-palette me-2"></i>Langkah 4 — Warna</div>
                    <div class="chip-selector">
                        <?php foreach ($warnaList as $w): ?>
                        <input type="checkbox" class="chip-item mass-warna" id="mw_<?= $w['id'] ?>" value="<?= htmlspecialchars($w['nama_warna']) ?>">
                        <label class="chip-label" for="mw_<?= $w['id'] ?>"><i class="fas fa-check fa-xs"></i><?= htmlspecialchars($w['nama_warna']) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2"><small class="text-muted fw-semibold">Warna tidak ada di daftar? </small><input type="text" id="massWarnaCustom" class="form-control form-control-sm d-inline-block mt-1 rounded-3 fw-semibold" style="max-width:250px;" placeholder="Ketik manual, pisah koma"></div>
                </div>
                <div class="p-3 rounded-3" style="background:#F0FDF4;border:1.5px solid #BBF7D0;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="total-bubble"><i class="fas fa-cubes"></i><span id="totalVarianMassal">0</span> varian</div>
                        <div class="small text-muted" id="totalVarianDetail">Pilih kondisi, storage, dan warna terlebih dahulu.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-3 fw-bold px-4" data-bs-dismiss="modal">Batal</button>
                <button type="button" id="btnSimpanMassal" class="btn btn-primary rounded-3 fw-bold px-4" style="background:var(--primary);border:none;" onclick="simpanMassal()"><i class="fas fa-save me-1"></i>Simpan Semua</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const merkList    = <?= $merkJson ?>;
const storageList = <?= $storageJson ?>;
const warnaList   = <?= $warnaJson ?>;
const supplierList = <?= $supplierJson ?>;

function toast(msg, type='success') {
    let c = document.getElementById('toastContainer') || (() => { let d = document.createElement('div'); d.id = 'toastContainer'; d.className = 'toast-container'; document.body.appendChild(d); return d; })();
    let icon = type === 'success' ? '<i class="fas fa-check-circle text-success fs-5"></i>' : type === 'danger' ? '<i class="fas fa-times-circle text-danger fs-5"></i>' : '<i class="fas fa-info-circle text-warning fs-5"></i>';
    let t = document.createElement('div'); t.className = 'toast-mini'; t.innerHTML = `${icon} <span>${msg}</span>`; c.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3000);
}

async function apiPost(payload) {
    const res = await fetch('../api/master_data_action.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    return res.json();
}

async function submitMasterForm(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('masterForm'));
    const data = Object.fromEntries(fd.entries());
    try {
        const result = await apiPost(data);
        if (result.status === 'success') { toast(result.msg || 'Berhasil'); setTimeout(() => location.reload(), 500); }
        else toast(result.msg || 'Gagal', 'danger');
    } catch { toast('Error koneksi', 'danger'); }
    return false;
}

async function toggleStatus(type, id, status) {
    if (!confirm(`Ubah status data ini menjadi "${status}"?`)) return;
    try {
        const result = await apiPost({ action: `toggle_${type}`, id, status });
        if (result.status === 'success') { toast('Status diubah'); setTimeout(() => location.reload(), 500); }
        else toast(result.msg || 'Gagal', 'danger');
    } catch { toast('Error koneksi', 'danger'); }
}

async function deleteItem(type, id, nama) {
    if (!confirm(`Hapus "${nama}"? Tindakan ini tidak bisa dibatalkan.`)) return;
    try {
        const result = await apiPost({ action: `delete_${type}`, id });
        if (result.status === 'success') { toast(result.msg || 'Berhasil dihapus'); setTimeout(() => location.reload(), 500); }
        else toast(result.msg || 'Gagal menghapus', 'danger');
    } catch { toast('Error koneksi', 'danger'); }
}
const deleteMerk   = (id, n) => deleteItem('merk',   id, n);
const deleteBarang = (id, n) => deleteItem('barang', id, n);
const deleteVarian = (id)    => deleteItem('varian', id, 'varian ini');
const deleteSupplier = (id, n) => deleteItem('supplier', id, n);

function showModal(title, action, id, fields) {
    document.getElementById('masterModalLabel').innerText = title;
    document.getElementById('formAction').value = action;
    document.getElementById('formId').value = id || '';
    document.getElementById('formFields').innerHTML = fields;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('masterModal')).show();
}

function merkOptions(selected = '') { return merkList.map(m => `<option value="${m.id}" ${selected==m.id?'selected':''}>${m.nama_merk}</option>`).join(''); }
function storageOptions(selected = '') { return storageList.map(s => `<option value="${s.nilai}" ${selected===s.nilai?'selected':''}>${s.nilai}</option>`).join(''); }
function warnaOptions(selected = '') { return warnaList.map(w => `<option value="${w.nama_warna}" ${selected===w.nama_warna?'selected':''}>${w.nama_warna}</option>`).join(''); }
function kondisiOptions(selected = '') { return ['New','Second','Like New','Ex Inter','Beacukai'].map(k => `<option value="${k}" ${selected===k?'selected':''}>${k}</option>`).join(''); }

// Merk
function showAddMerk() { showModal('Tambah Merk', 'add_merk', '', `<div class="mb-3"><label class="form-label fw-semibold">Nama Merk</label><input type="text" name="nama_merk" class="form-control rounded-3 fw-semibold text-uppercase" placeholder="Cth: APPLE, SAMSUNG..." required autofocus></div>`); }
function editMerk(id, nama) { showModal('Edit Merk', 'update_merk', id, `<div class="mb-3"><label class="form-label fw-semibold">Nama Merk</label><input type="text" name="nama_merk" class="form-control rounded-3 fw-semibold text-uppercase" value="${nama}" required autofocus></div>`); }

// Barang
function showAddBarang() {
    const selMerk = document.getElementById('filterMerkBarang')?.value || '';
    showModal('Tambah Barang', 'add_barang', '', `<div class="mb-3"><label class="form-label fw-semibold">Merk</label><select name="merk_id" class="form-select rounded-3 fw-semibold" required><option value="">-- Pilih Merk --</option>${merkOptions(selMerk)}</select></div><div class="mb-3"><label class="form-label fw-semibold">Nama Barang</label><input type="text" name="nama_barang" class="form-control rounded-3 fw-semibold text-uppercase" placeholder="Cth: IPHONE 15, GALAXY A55..." required></div>`);
}
function editBarang(id, nama, merkId) { showModal('Edit Barang', 'update_barang', id, `<div class="mb-3"><label class="form-label fw-semibold">Merk</label><select name="merk_id" class="form-select rounded-3 fw-semibold" required><option value="">-- Pilih Merk --</option>${merkOptions(merkId)}</select></div><div class="mb-3"><label class="form-label fw-semibold">Nama Barang</label><input type="text" name="nama_barang" class="form-control rounded-3 fw-semibold text-uppercase" value="${nama}" required></div>`); }

// Varian
function showAddVarian() {
    const selBarang = document.getElementById('filterBarangVarian')?.value || '';
    const selMerk   = document.getElementById('filterMerkVarian')?.value   || '';
    showModal('Tambah Varian', 'add_varian', '', `<div class="alert alert-info py-2 small fw-semibold mb-3"><i class="fas fa-info-circle me-1"></i> Storage dan warna dipilih dari master.</div><div class="mb-3"><label class="form-label fw-semibold">Merk</label><select id="varianMerkSel" class="form-select rounded-3 fw-semibold" onchange="loadBarangForVarian(this.value)"><option value="">-- Pilih Merk --</option>${merkOptions(selMerk)}</select></div><div class="mb-3"><label class="form-label fw-semibold">Barang</label><select name="barang_id" id="varianBarangSel" class="form-select rounded-3 fw-semibold" required ${!selBarang?'disabled':''}><option value="">-- Pilih Barang (pilih merk dulu) --</option>${selBarang ? `<option value="${selBarang}" selected>${document.getElementById('filterBarangVarian')?.options[document.getElementById('filterBarangVarian').selectedIndex]?.text || ''}</option>` : ''}</select></div><div class="mb-3"><label class="form-label fw-semibold">Kondisi</label><select name="kondisi" class="form-select rounded-3 fw-semibold" required><option value="">-- Pilih Kondisi --</option>${kondisiOptions()}</select></div><div class="mb-3"><label class="form-label fw-semibold">Internal Storage</label><select name="internal_storage" class="form-select rounded-3 fw-semibold" required><option value="">-- Pilih Storage --</option>${storageOptions()}</select></div><div class="mb-3"><label class="form-label fw-semibold">Warna</label><select name="warna" class="form-select rounded-3 fw-semibold" required><option value="">-- Pilih Warna --</option>${warnaOptions()}</select></div>`);
}
function editVarian(id, kondisi, storage, warna, barangId) { showModal('Edit Varian', 'update_varian', id, `<div class="mb-3"><label class="form-label fw-semibold">Barang</label><select name="barang_id" class="form-select rounded-3 fw-semibold" required><option value="${barangId}" selected>ID: ${barangId} (sedang dipilih)</option></select><small class="text-muted">Untuk ganti barang, hapus lalu buat baru.</small></div><div class="mb-3"><label class="form-label fw-semibold">Kondisi</label><select name="kondisi" class="form-select rounded-3 fw-semibold" required><option value="">-- Pilih Kondisi --</option>${kondisiOptions(kondisi)}</select></div><div class="mb-3"><label class="form-label fw-semibold">Internal Storage</label><select name="internal_storage" class="form-select rounded-3 fw-semibold" required><option value="">-- Pilih Storage --</option>${storageOptions(storage)}</select></div><div class="mb-3"><label class="form-label fw-semibold">Warna</label><select name="warna" class="form-select rounded-3 fw-semibold" required><option value="">-- Pilih Warna --</option>${warnaOptions(warna)}</select></div>`); }

async function loadBarangForVarian(merkId) {
    const sel = document.getElementById('varianBarangSel');
    sel.disabled = true; sel.innerHTML = '<option value="">Memuat...</option>';
    if (!merkId) { sel.innerHTML = '<option value="">-- Pilih Barang --</option>'; return; }
    const res = await fetch(`../api/get_master_barang.php?merk_id=${merkId}`);
    const json = await res.json();
    sel.innerHTML = '<option value="">-- Pilih Barang --</option>';
    if (json.status === 'success') json.data.forEach(item => sel.innerHTML += `<option value="${item.id}">${item.nama_barang}</option>`);
    sel.disabled = false;
}

// Supplier
function showAddSupplier() {
    showModal('Tambah Supplier', 'add_supplier', '', `
        <div class="mb-3"><label class="form-label fw-semibold">Nama Supplier</label><input type="text" name="nama_supplier" class="form-control rounded-3 fw-semibold text-uppercase" required autofocus></div>
        <div class="mb-3"><label class="form-label fw-semibold">Alamat</label><textarea name="alamat" class="form-control rounded-3 fw-semibold" rows="2"></textarea></div>
        <div class="mb-3"><label class="form-label fw-semibold">Telepon</label><input type="text" name="telepon" class="form-control rounded-3 fw-semibold"></div>
        <div class="mb-3"><label class="form-label fw-semibold">Email</label><input type="email" name="email" class="form-control rounded-3 fw-semibold"></div>
    `);
}
function editSupplier(id, nama, alamat, telepon, email) {
    showModal('Edit Supplier', 'update_supplier', id, `
        <div class="mb-3"><label class="form-label fw-semibold">Nama Supplier</label><input type="text" name="nama_supplier" class="form-control rounded-3 fw-semibold text-uppercase" value="${nama}" required autofocus></div>
        <div class="mb-3"><label class="form-label fw-semibold">Alamat</label><textarea name="alamat" class="form-control rounded-3 fw-semibold" rows="2">${alamat}</textarea></div>
        <div class="mb-3"><label class="form-label fw-semibold">Telepon</label><input type="text" name="telepon" class="form-control rounded-3 fw-semibold" value="${telepon}"></div>
        <div class="mb-3"><label class="form-label fw-semibold">Email</label><input type="email" name="email" class="form-control rounded-3 fw-semibold" value="${email}"></div>
    `);
}
async function toggleSupplier(id, status) {
    if (!confirm(`Ubah status supplier menjadi "${status}"?`)) return;
    try {
        const result = await apiPost({ action: 'toggle_supplier', id, status });
        if (result.status === 'success') { toast('Status diubah'); setTimeout(() => location.reload(), 500); }
        else toast(result.msg || 'Gagal', 'danger');
    } catch { toast('Error koneksi', 'danger'); }
}

// Storage
function showAddStorage() { showModal('Tambah Storage', 'add_storage', '', `<div class="mb-3"><label class="form-label fw-semibold">Nilai Storage</label><input type="text" name="nilai" class="form-control rounded-3 fw-semibold text-uppercase" placeholder="Cth: 128GB, 1TB" required autofocus><small class="text-muted">Format: 64GB, 128GB, 256GB, 512GB, 1TB, 2TB</small></div><div class="mb-3"><label class="form-label fw-semibold">Urutan Tampil</label><input type="number" name="urutan" class="form-control rounded-3 fw-semibold" value="0" min="0"><small class="text-muted">Angka kecil tampil lebih dulu.</small></div>`); }
function editStorage(id, nilai, urutan) { showModal('Edit Storage', 'update_storage', id, `<div class="mb-3"><label class="form-label fw-semibold">Nilai Storage</label><input type="text" name="nilai" class="form-control rounded-3 fw-semibold text-uppercase" value="${nilai}" required autofocus></div><div class="mb-3"><label class="form-label fw-semibold">Urutan Tampil</label><input type="number" name="urutan" class="form-control rounded-3 fw-semibold" value="${urutan}" min="0"></div>`); }
async function deleteStorage(id, nilai) { if (!confirm(`Hapus storage "${nilai}"?`)) return; const r = await apiPost({ action: 'delete_storage', id }); toast(r.msg || (r.status === 'success' ? 'Berhasil' : 'Gagal'), r.status === 'success' ? 'success' : 'danger'); if (r.status === 'success') setTimeout(() => location.reload(), 500); }
async function toggleStorage(id, status) { const r = await apiPost({ action: 'toggle_storage', id, status }); if (r.status === 'success') { toast('Status diubah'); setTimeout(() => location.reload(), 500); } else toast(r.msg || 'Gagal', 'danger'); }

// Warna
function showAddWarna() { showModal('Tambah Warna', 'add_warna', '', `<div class="mb-3"><label class="form-label fw-semibold">Nama Warna</label><input type="text" name="nama_warna" class="form-control rounded-3 fw-semibold text-uppercase" placeholder="Cth: MIDNIGHT, STARLIGHT" required autofocus><small class="text-muted">Otomatis kapital.</small></div>`); }
function editWarna(id, nama) { showModal('Edit Warna', 'update_warna', id, `<div class="mb-3"><label class="form-label fw-semibold">Nama Warna</label><input type="text" name="nama_warna" class="form-control rounded-3 fw-semibold text-uppercase" value="${nama}" required autofocus></div>`); }
async function deleteWarna(id, nama) { if (!confirm(`Hapus warna "${nama}"?`)) return; const r = await apiPost({ action: 'delete_warna', id }); toast(r.msg || (r.status === 'success' ? 'Berhasil' : 'Gagal'), r.status === 'success' ? 'success' : 'danger'); if (r.status === 'success') setTimeout(() => location.reload(), 500); }
async function toggleWarna(id, aktif) { const r = await apiPost({ action: 'toggle_warna', id, aktif }); if (r.status === 'success') { toast('Status diubah'); setTimeout(() => location.reload(), 500); } else toast(r.msg || 'Gagal', 'danger'); }

// Filter & Massal
async function loadBarangForFilter(merkId) {
    const sel = document.getElementById('filterBarangVarian');
    sel.innerHTML = '<option value="">Memuat...</option>';
    if (!merkId) { sel.innerHTML = '<option value="">-- Pilih Barang --</option>'; location.href = '?tab=varian'; return; }
    const res = await fetch(`../api/get_master_barang.php?merk_id=${merkId}`);
    const json = await res.json();
    sel.innerHTML = '<option value="">-- Pilih Barang --</option>';
    if (json.status === 'success') json.data.forEach(item => sel.innerHTML += `<option value="${item.id}">${item.nama_barang}</option>`);
}
async function loadBarangForMassal(merkId) {
    const sel = document.getElementById('massBarangSelect');
    sel.disabled = true; sel.innerHTML = '<option value="">Memuat...</option>';
    if (!merkId) { sel.innerHTML = '<option value="">-- Pilih Barang --</option>'; return; }
    const res = await fetch(`../api/get_master_barang.php?merk_id=${merkId}`);
    const json = await res.json();
    sel.innerHTML = '<option value="">-- Pilih Barang --</option>';
    if (json.status === 'success') json.data.forEach(item => sel.innerHTML += `<option value="${item.id}">${item.nama_barang}</option>`);
    sel.disabled = false;
    hitungTotal();
}
function hitungTotal() {
    const kondisis = [...document.querySelectorAll('.mass-kondisi:checked')].map(el => el.value);
    const storages = [...document.querySelectorAll('.mass-storage:checked')].map(el => el.value).concat(document.getElementById('massStorageCustom').value.split(',').map(s => s.trim().toUpperCase()).filter(s => s));
    const warnas = [...document.querySelectorAll('.mass-warna:checked')].map(el => el.value).concat(document.getElementById('massWarnaCustom').value.split(',').map(w => w.trim().toUpperCase()).filter(w => w));
    const total = kondisis.length * storages.length * warnas.length;
    document.getElementById('totalVarianMassal').textContent = total;
    document.getElementById('totalVarianDetail').textContent = total > 0 ? `${kondisis.length} kondisi × ${storages.length} storage × ${warnas.length} warna` : 'Pilih kondisi, storage, dan warna.';
}
async function simpanMassal() {
    const barangId = document.getElementById('massBarangSelect').value;
    if (!barangId) return toast('Pilih barang terlebih dahulu.', 'danger');
    const kondisis = [...document.querySelectorAll('.mass-kondisi:checked')].map(el => el.value);
    const storages = [...document.querySelectorAll('.mass-storage:checked')].map(el => el.value).concat(document.getElementById('massStorageCustom').value.split(',').map(s => s.trim().toUpperCase()).filter(s => s));
    const warnas = [...document.querySelectorAll('.mass-warna:checked')].map(el => el.value).concat(document.getElementById('massWarnaCustom').value.split(',').map(w => w.trim().toUpperCase()).filter(w => w));
    if (!kondisis.length) return toast('Pilih minimal 1 kondisi.', 'danger');
    if (!storages.length) return toast('Pilih minimal 1 storage.', 'danger');
    if (!warnas.length) return toast('Pilih minimal 1 warna.', 'danger');
    const total = kondisis.length * storages.length * warnas.length;
    if (!confirm(`Buat ${total} varian baru? Duplikat dilewati otomatis.`)) return;
    const varian = [];
    for (const k of kondisis) for (const s of storages) for (const w of warnas) varian.push({ kondisi: k, internal_storage: s, warna: w });
    document.getElementById('btnSimpanMassal').disabled = true;
    document.getElementById('btnSimpanMassal').innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Menyimpan...';
    const result = await apiPost({ action: 'add_varian_batch', barang_id: barangId, varian });
    toast(result.msg || 'Berhasil', result.status === 'success' ? 'success' : 'danger');
    if (result.status === 'success') setTimeout(() => location.reload(), 1000);
    document.getElementById('btnSimpanMassal').disabled = false;
    document.getElementById('btnSimpanMassal').innerHTML = '<i class="fas fa-save me-1"></i>Simpan Semua';
}

document.querySelectorAll('.mass-kondisi, .mass-storage, .mass-warna').forEach(el => el.addEventListener('change', hitungTotal));
['massStorageCustom','massWarnaCustom'].forEach(id => document.getElementById(id)?.addEventListener('input', hitungTotal));

// ── Popup Menu Mobile ────────────────────────────────────────
const moreBtn = document.getElementById('moreMenuBtn');
const popup   = document.getElementById('moreMenuPopup');
const overlay = document.getElementById('popupOverlay');

function closePopup() {
    popup.classList.remove('show');
    overlay.classList.remove('show');
}

if (moreBtn) {
    moreBtn.addEventListener('click', function(e) {
        e.stopPropagation(); // Mencegah event menyebar ke document
        // Toggle popup
        if (popup.classList.contains('show')) {
            closePopup();
        } else {
            popup.classList.add('show');
            overlay.classList.add('show');
        }
    });
}

if (overlay) {
    overlay.addEventListener('click', closePopup);
}

// Tutup popup saat klik di luar elemen popup (tetapi bukan pada moreBtn)
document.addEventListener('click', function(e) {
    // Jika popup sedang terbuka dan target klik bukan di dalam popup dan bukan moreBtn
    if (popup.classList.contains('show')) {
        if (!popup.contains(e.target) && e.target !== moreBtn && !moreBtn.contains(e.target)) {
            closePopup();
        }
    }
});
</script>
</body>
</html>
<?php
function generateColorFromName($name) {
    $colorMap = [
        'BLACK' => '#1a1a1a', 'WHITE' => '#f5f5f5', 'SILVER' => '#C0C0C0',
        'GOLD' => '#FFD700', 'BLUE' => '#3B82F6', 'RED' => '#EF4444',
        'GREEN' => '#10B981', 'PURPLE' => '#8B5CF6', 'PINK' => '#EC4899',
        'YELLOW' => '#F59E0B', 'GRAY' => '#6B7280', 'GRAPHITE' => '#4B5563',
        'MIDNIGHT' => '#1e3a5f', 'STARLIGHT' => '#f0e6d3', 'TEAL' => '#14B8A6',
        'LAVENDER' => '#c4b5fd', 'CORAL' => '#fb7185', 'SAGE' => '#86efac',
        'SPACE GRAY' => '#4B5563', 'SPACE BLACK' => '#1F2937',
        'DEEP PURPLE' => '#5B21B6', 'ALPINE GREEN' => '#2d6a4f',
        'SIERRA BLUE' => '#60a5fa', 'PACIFIC BLUE' => '#0284c7',
        'ULTRAMARINE' => '#1e40af', 'NATURAL TITANIUM' => '#d4c5a9',
        'BLACK TITANIUM' => '#2D2D2D', 'WHITE TITANIUM' => '#f0ede8',
        'BLUE TITANIUM' => '#1e6091', 'DESERT TITANIUM' => '#c9b18f',
        'MIDNIGHT GREEN' => '#1a3d2b', 'DEEP BLUE' => '#1e3a8a',
        'DARK BLUE' => '#1d3461', 'MIST BLUE' => '#93c5fd',
        'AWESOME BLACK' => '#111827', 'COSMIC ORANGE' => '#f97316',
    ];
    $key = strtoupper(trim($name));
    if (isset($colorMap[$key])) return $colorMap[$key];
    $hash = crc32($key);
    $r = ($hash >> 16) & 0xFF; $g = ($hash >> 8) & 0xFF; $b = $hash & 0xFF;
    return sprintf('#%02x%02x%02x', ($r+150)%256, ($g+150)%256, ($b+150)%256);
}
?>