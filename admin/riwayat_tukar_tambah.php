<?php
require_once '../api/auth_helper.php';
restrictTo(['admin']);
include '../connection.php';

// Definisikan filter di awal agar bisa digunakan di redirect
$filter_dari  = $_GET['dari']  ?? date('Y-m-01');
$filter_sampai = $_GET['sampai'] ?? date('Y-m-d');

/* ─── PROSES UPDATE DATA ─── */
if (isset($_POST['edit_tukar_tambah'])) {
    $id = intval($_POST['id']);
    $nama_lama = trim($_POST['nama_lama']);
    $kondisi_lama = trim($_POST['kondisi_lama']);
    $harga_lama = intval(str_replace('.', '', $_POST['harga_lama']));
    $nama_baru = trim($_POST['nama_baru']);
    $imei_baru = trim($_POST['imei_baru']);
    $harga_baru = intval(str_replace('.', '', $_POST['harga_baru']));
    $selisih = intval(str_replace('.', '', $_POST['selisih']));
    $laba = intval(str_replace('.', '', $_POST['laba']));
    $catatan = trim($_POST['catatan']);
    
    $stmt = $koneksi->prepare("UPDATE tukar_tambah SET nama_lama=?, kondisi_lama=?, harga_lama=?, nama_baru=?, imei_baru=?, harga_baru=?, selisih=?, laba=?, catatan=? WHERE id=?");
    $stmt->bind_param("ssisisiiisi", $nama_lama, $kondisi_lama, $harga_lama, $nama_baru, $imei_baru, $harga_baru, $selisih, $laba, $catatan, $id);
    $stmt->execute();
    
    header("Location: riwayat_tukar_tambah.php?dari=$filter_dari&sampai=$filter_sampai&status=updated");
    exit();
}

/* ─── PROSES HAPUS DATA ─── */
if (isset($_POST['hapus_tukar_tambah'])) {
    $id = intval($_POST['id']);
    $stmt = $koneksi->prepare("DELETE FROM tukar_tambah WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    header("Location: riwayat_tukar_tambah.php?dari=$filter_dari&sampai=$filter_sampai&status=deleted");
    exit();
}

$query = "
    SELECT tt.*, b.nama AS nama_baru 
    FROM tukar_tambah tt
    LEFT JOIN barang b ON tt.imei_baru = b.imei
    WHERE DATE(tt.tanggal) BETWEEN '$filter_dari' AND '$filter_sampai'
    ORDER BY tt.tanggal DESC
";
$result = mysqli_query($koneksi, $query);

$q_sum = mysqli_query($koneksi, "
    SELECT COUNT(*) as total_tt, SUM(harga_baru) as total_omzet, SUM(laba) as total_laba
    FROM tukar_tambah
    WHERE DATE(tanggal) BETWEEN '$filter_dari' AND '$filter_sampai'
");
$sum = mysqli_fetch_assoc($q_sum);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Riwayat Tukar Tambah · Happy Cellular</title>
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
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .navbar-logo { width: 32px; height: 32px; object-fit: contain; border-radius: 8px; }
        .logo-text { font-weight: 800; font-size: 1.3rem; color: var(--primary-dongker); letter-spacing: -0.5px; }
        .logo-text small { font-size: 0.75rem; font-weight: 500; color: var(--text-muted); display: block; margin-top: -2px; }
        
        .user-badge {
            background: var(--primary-dongker-soft); padding: 8px 16px; border-radius: 20px;
            color: var(--primary-dongker); font-weight: 700; font-size: 0.8rem;
            display: flex; align-items: center; gap: 6px; border: none; cursor: pointer;
        }
        .user-badge:after {
            display: inline-block; margin-left: 0.255em; vertical-align: 0.255em;
            content: ""; border-top: 0.3em solid; border-right: 0.3em solid transparent;
            border-bottom: 0; border-left: 0.3em solid transparent;
        }
        .dropdown-menu-custom {
            border-radius: 16px; box-shadow: 0 10px 25px rgba(30,41,59,0.1);
            border: 1px solid rgba(0,0,0,0.05); padding: 8px; z-index: 1020 !important;
        }
        .dropdown-menu-custom .dropdown-item {
            font-size: 0.85rem; font-weight: 600; color: var(--text-main);
            padding: 10px 14px; border-radius: 10px; display: flex; align-items: center; gap: 10px;
        }
        .dropdown-menu-custom .dropdown-item i { color: var(--primary-dongker); font-size: 0.95rem; width: 16px; text-align: center; }
        .dropdown-menu-custom .dropdown-item:hover { background: var(--primary-dongker-soft); color: var(--primary-dongker); }

        .main-wrapper { max-width: 1400px; margin: 0 auto; padding: 1rem 1.2rem; }

        .section-title {
            font-size: 1.1rem; font-weight: 800; color: var(--text-main);
            margin: 2rem 0 1rem; display: flex; align-items: center; gap: 10px;
        }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px; margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--surface-card); border-radius: 16px; padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02); border: 1px solid rgba(0, 0, 0, 0.01);
            transition: all 0.3s ease; position: relative; overflow: hidden;
        }

        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); }

        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
        .stat-card:nth-child(1)::before { background: var(--primary-dongker); }
        .stat-card:nth-child(2)::before { background: var(--accent-blue); }
        .stat-card:nth-child(3)::before { background: var(--success-green); }

        .stat-icon-box { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 12px; }
        .stat-value { font-size: 1.4rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; margin-bottom: 4px; }
        .stat-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); }

        .filter-card {
            background: var(--surface-card); border-radius: 16px; padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02); border: 1px solid rgba(0, 0, 0, 0.01);
            margin-bottom: 1.5rem; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;
        }

        .filter-card label { font-weight: 600; font-size: 0.82rem; color: var(--primary-dongker); display: block; margin-bottom: 4px; }
        .filter-card input { border-radius: 10px; border: 1px solid #E2E8F0; padding: 0.55rem 0.9rem; font-size: 0.9rem; font-weight: 600; }
        .filter-card input:focus { border-color: var(--primary-dongker); box-shadow: 0 0 0 3px var(--primary-dongker-soft); outline: none; }

        .btn-filter { background: var(--primary-dongker); color: #fff; border: none; border-radius: 10px; padding: 0.55rem 1.2rem; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s; }
        .btn-filter:hover { background: #163d6a; transform: translateY(-1px); }

        .table-card { background: var(--surface-card); border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02); border: 1px solid rgba(0, 0, 0, 0.01); overflow: hidden; }

        .table-responsive-stack { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; margin-bottom: 0; }
        .table-responsive-stack table { min-width: 1000px; width: max-content; }

        .table thead th {
            background: #F8FAFC; color: var(--text-muted); font-weight: 700; font-size: 0.75rem;
            text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #E2E8F0; padding: 0.9rem 1rem;
        }

        .table tbody td { padding: 0.85rem 1rem; font-weight: 500; color: var(--text-main); vertical-align: middle; border-bottom: 1px solid #F1F5F9; }
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:hover { background: #F8FAFC; }

        .badge-kondisi { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .badge-mulus { background: rgba(16,185,129,0.1); color: var(--success-green); }
        .badge-normal { background: rgba(245,158,11,0.1); color: var(--warning-orange); }
        .badge-cacat { background: rgba(239,68,68,0.1); color: var(--danger-red); }

        .imei-code {
            font-family: monospace; background: var(--primary-dongker-soft);
            padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; color: var(--primary-dongker);
        }

        .laba-pos { color: var(--success-green); font-weight: 700; }
        .laba-neg { color: var(--danger-red); font-weight: 700; }

        .btn-action {
            border-radius: 8px; padding: 0.4rem 0.7rem; font-size: 0.75rem;
            font-weight: 600; border: none; cursor: pointer; transition: all 0.2s;
        }
        .btn-edit { background: rgba(59,130,246,0.1); color: var(--accent-blue); }
        .btn-edit:hover { background: rgba(59,130,246,0.2); }
        .btn-delete { background: rgba(239,68,68,0.1); color: var(--danger-red); }
        .btn-delete:hover { background: rgba(239,68,68,0.2); }

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

        .swal-wide { width: 90% !important; max-width: 600px !important; }

        @media (max-width: 768px) {
            .main-wrapper { padding: 0.8rem; }
            .table-card { padding: 0.8rem; }
            .table thead th, .table tbody td { font-size: 0.75rem; padding: 0.65rem 0.5rem; }
            .btn-cetak-col { display: none; }
            .filter-card > div { flex: 1 1 100%; }
            .filter-card input { width: 100%; }
            .btn-action { padding: 0.5rem 0.8rem; font-size: 0.8rem; }
            .stat-value { font-size: 1.2rem; }
            .section-title { font-size: 1rem; }
        }

        @media (min-width: 992px) {
            body { padding-bottom: 0; display: flex; background: #F8FAFC; }
            .bottom-nav, .popup-overlay, .more-menu-popup { display: none !important; }
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

            .content-wrapper { flex-grow: 1; display: flex; flex-direction: column; min-width: 0; }
            .main-wrapper { max-width: 1400px; width: 100%; padding: 1.5rem 2rem; margin: 0; }
        }
    </style>
</head>
<body>

<!-- DESKTOP SIDEBAR (SUDAH DISESUAIKAN) -->
<div class="desktop-sidebar">
    <div class="sidebar-brand">
        <img src="asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
        <div class="logo-text">Happy Cell<small>Admin Panel</small></div>
    </div>
    <div class="sidebar-menu">
        <a href="home_admin.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="stock_in.php" class="sidebar-link"><i class="fas fa-arrow-circle-down" style="color: #10B981;"></i> Stock In</a>
        <a href="admin_stock_out.php" class="sidebar-link"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i> Stock Out</a>
        <a href="return.php" class="sidebar-link"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i> Retur</a>
        <div class="sidebar-divider"></div>
        <a href="data_master.php" class="sidebar-link"><i class="fas fa-database"></i> Data Master</a>
        <a href="data_barang.php" class="sidebar-link"><i class="fas fa-box"></i> Data Produk</a>
        <a href="penjualan.php" class="sidebar-link"><i class="fas fa-shopping-cart"></i> Transaksi Jual</a>
        <a href="keuangan.php" class="sidebar-link"><i class="fas fa-chart-pie"></i> Analisis Laba</a>
        <a href="manajemen_label.php" class="sidebar-link"><i class="fas fa-print"></i> Cetak Label</a>
        <a href="tukar_tambah.php" class="sidebar-link active"><i class="fas fa-exchange-alt"></i> Tukar Tambah</a>
        <a href="beli_customer.php" class="sidebar-link"><i class="fas fa-hand-holding-usd" style="color:#8B5CF6;"></i> Beli dari Customer</a>
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
        
        <h2 style="font-weight: 800; margin-bottom: 1.5rem;">
            <i class="fas fa-history me-2" style="color:var(--accent-blue)"></i>Riwayat Tukar Tambah
        </h2>

        <?php if(isset($_GET['status'])): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: '<?= $_GET['status'] == 'updated' ? 'Data Berhasil Diperbarui!' : 'Data Berhasil Dihapus!' ?>',
                timer: 2000,
                showConfirmButton: false
            });
        </script>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon-box" style="background: rgba(30,74,122,0.1); color: var(--primary-dongker);">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-value"><?= $sum['total_tt'] ?? 0 ?></div>
                <div class="stat-label">Total Transaksi</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-box" style="background: rgba(59,130,246,0.1); color: var(--accent-blue);">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value" style="font-size:1.2rem">Rp <?= number_format($sum['total_omzet'] ?? 0, 0, ',', '.') ?></div>
                <div class="stat-label">Total Omzet</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-box" style="background: rgba(16,185,129,0.1); color: var(--success-green);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value" style="font-size:1.2rem;color:var(--success-green)">Rp <?= number_format($sum['total_laba'] ?? 0, 0, ',', '.') ?></div>
                <div class="stat-label">Total Laba</div>
            </div>
        </div>

        <div class="section-title"><i class="fas fa-filter"></i> Filter</div>
        <form method="GET" class="filter-card">
            <div>
                <label>Dari Tanggal</label>
                <input type="date" name="dari" value="<?= $filter_dari ?>">
            </div>
            <div>
                <label>Sampai Tanggal</label>
                <input type="date" name="sampai" value="<?= $filter_sampai ?>">
            </div>
            <button type="submit" class="btn-filter"><i class="fas fa-filter me-1"></i> Filter</button>
            <a href="riwayat_tukar_tambah.php" class="btn-filter" style="background:var(--text-muted);text-decoration:none">Reset</a>
            <button type="button" onclick="window.print()" class="btn-filter btn-cetak-col" style="background:#475569;">
                <i class="fas fa-print me-1"></i> Cetak
            </button>
        </form>

        <div class="section-title"><i class="fas fa-table"></i> Data Transaksi</div>
        <div class="table-card">
            <div class="table-responsive-stack">
                <table class="table table-borderless align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>HP Lama</th>
                            <th>Kondisi</th>
                            <th>HP Baru</th>
                            <th>IMEI Baru</th>
                            <th class="text-end">Harga Lama</th>
                            <th class="text-end">Harga Baru</th>
                            <th class="text-end">Bayar</th>
                            <th class="text-end">Laba</th>
                            <th>Kasir</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): $no = 1; ?>
                            <?php while($r = mysqli_fetch_assoc($result)): ?>
                                <tr id="row-<?= $r['id'] ?>">
                                    <td style="color:var(--text-muted)"><?= $no++ ?></td>
                                    <td>
                                        <div style="font-weight:600"><?= date('d/m/Y', strtotime($r['tanggal'])) ?></div>
                                        <div style="font-size:.75rem;color:var(--text-muted)"><?= date('H:i', strtotime($r['tanggal'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($r['nama_lama']) ?></div>
                                        <?php if($r['imei_lama']): ?>
                                            <div style="font-size:.75rem;color:var(--text-muted)"><?= $r['imei_lama'] ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-kondisi badge-<?= strtolower($r['kondisi_lama']) ?>">
                                            <?= $r['kondisi_lama'] ?>
                                        </span>
                                    </td>
                                    <td class="fw-semibold"><?= htmlspecialchars($r['nama_baru'] ?? '—') ?></td>
                                    <td><span class="imei-code"><?= $r['imei_baru'] ?></span></td>
                                    <td class="text-end" style="color:var(--danger-red);font-weight:600">Rp <?= number_format($r['harga_lama'], 0, ',', '.') ?></td>
                                    <td class="text-end fw-semibold">Rp <?= number_format($r['harga_baru'], 0, ',', '.') ?></td>
                                    <td class="text-end fw-bold" style="color:var(--primary-dongker)">Rp <?= number_format($r['selisih'], 0, ',', '.') ?></td>
                                    <td class="text-end <?= ($r['laba'] >= 0) ? 'laba-pos' : 'laba-neg' ?>">
                                        Rp <?= number_format($r['laba'], 0, ',', '.') ?>
                                    </td>
                                    <td style="font-size:.82rem;color:var(--text-muted)"><?= htmlspecialchars($r['user'] ?? '-') ?></td>
                                    <td>
                                        <button class="btn-action btn-edit me-1" onclick='editTukar(<?= json_encode($r) ?>)'><i class="fas fa-edit"></i></button>
                                        <button class="btn-action btn-delete" onclick="hapusTukar(<?= $r['id'] ?>)"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php if($r['catatan']): ?>
                                <tr style="background:#F8FAFC">
                                    <td colspan="12" style="font-size:.8rem;color:var(--text-muted);padding:.4rem 1rem">
                                        <i class="fas fa-comment-alt me-1"></i> <?= htmlspecialchars($r['catatan']) ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="text-center py-5" style="color:var(--text-muted)">
                                    <i class="fas fa-exchange-alt fa-2x mb-2 d-block"></i>
                                    Belum ada transaksi tukar tambah
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="text-align:center;margin-top:1.5rem">
            <a href="tukar_tambah.php" style="color:var(--primary-dongker);font-weight:600;text-decoration:none">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Tukar Tambah
            </a>
        </div>
    </div>
</div>

<!-- BOTTOM NAV (Mobile) -->
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Popup Menu
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

function formatRibuan(input) {
    let value = input.value.replace(/[^,\d]/g, '').toString();
    let split = value.split(',');
    let sisa  = split[0].length % 3;
    let rupiah  = split[0].substr(0, sisa);
    let ribuan  = split[0].substr(sisa).match(/\d{3}/gi);
    if(ribuan){
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }
    input.value = rupiah != '' ? rupiah : '';
}

function parseRibuan(val) {
    if(!val) return 0;
    return parseInt(val.replace(/[^0-9]/g, '')) || 0;
}

function editTukar(data) {
    Swal.fire({
        title: 'Edit Transaksi Tukar Tambah',
        customClass: { popup: 'swal-wide' },
        html: `
            <div class="row g-2 text-start">
                <div class="col-12">
                    <label class="form-label fw-bold">Nama HP Lama</label>
                    <input id="edit_nama_lama" class="swal2-input" value="${data.nama_lama}">
                </div>
                <div class="col-6">
                    <label class="form-label fw-bold">Kondisi Lama</label>
                    <select id="edit_kondisi_lama" class="swal2-select" style="width:100%">
                        <option value="Mulus" ${data.kondisi_lama == 'Mulus' ? 'selected' : ''}>Mulus</option>
                        <option value="Normal" ${data.kondisi_lama == 'Normal' ? 'selected' : ''}>Normal</option>
                        <option value="Cacat" ${data.kondisi_lama == 'Cacat' ? 'selected' : ''}>Cacat</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label fw-bold">Harga Lama</label>
                    <input id="edit_harga_lama" class="swal2-input" value="${data.harga_lama}" oninput="formatRibuan(this)">
                </div>
                <div class="col-6">
                    <label class="form-label fw-bold">Nama HP Baru</label>
                    <input id="edit_nama_baru" class="swal2-input" value="${data.nama_baru}">
                </div>
                <div class="col-6">
                    <label class="form-label fw-bold">IMEI Baru</label>
                    <input id="edit_imei_baru" class="swal2-input" value="${data.imei_baru}">
                </div>
                <div class="col-6">
                    <label class="form-label fw-bold">Harga Baru</label>
                    <input id="edit_harga_baru" class="swal2-input" value="${data.harga_baru}" oninput="formatRibuan(this)">
                </div>
                <div class="col-6">
                    <label class="form-label fw-bold">Selisih (Bayar)</label>
                    <input id="edit_selisih" class="swal2-input" value="${data.selisih}" oninput="formatRibuan(this)">
                </div>
                <div class="col-6">
                    <label class="form-label fw-bold">Laba</label>
                    <input id="edit_laba" class="swal2-input" value="${data.laba}" oninput="formatRibuan(this)">
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Catatan</label>
                    <textarea id="edit_catatan" class="swal2-textarea" style="width:100%">${data.catatan || ''}</textarea>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#1E4A7A',
        preConfirm: () => {
            const formData = new FormData();
            formData.append('id', data.id);
            formData.append('edit_tukar_tambah', '1');
            formData.append('nama_lama', document.getElementById('edit_nama_lama').value);
            formData.append('kondisi_lama', document.getElementById('edit_kondisi_lama').value);
            formData.append('harga_lama', parseRibuan(document.getElementById('edit_harga_lama').value));
            formData.append('nama_baru', document.getElementById('edit_nama_baru').value);
            formData.append('imei_baru', document.getElementById('edit_imei_baru').value);
            formData.append('harga_baru', parseRibuan(document.getElementById('edit_harga_baru').value));
            formData.append('selisih', parseRibuan(document.getElementById('edit_selisih').value));
            formData.append('laba', parseRibuan(document.getElementById('edit_laba').value));
            formData.append('catatan', document.getElementById('edit_catatan').value);
            
            return fetch('riwayat_tukar_tambah.php', { method: 'POST', body: formData })
                .then(response => { window.location.reload(); })
                .catch(error => { Swal.showValidationMessage('Error: ' + error); });
        }
    });
}

function hapusTukar(id) {
    Swal.fire({
        title: 'Yakin ingin menghapus?',
        text: "Data transaksi tukar tambah akan dihapus permanen.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#64748B',
        confirmButtonText: 'Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('hapus_tukar_tambah', '1');
            
            fetch('riwayat_tukar_tambah.php', { method: 'POST', body: formData })
                .then(response => { window.location.reload(); });
        }
    });
}
</script>

</body>
</html>