<?php
session_start();
if(!isset($_SESSION['login']) || $_SESSION['level'] != 'frontliner'){
    header("Location: ../login.php");
    exit();
}

include '../connection.php';

// ==========================================
// LOGIKA NAVIGASI TANGGAL
// ==========================================
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$date_obj = new DateTime($selected_date);
$date_prev = (clone $date_obj)->modify('-1 day')->format('Y-m-d');
$date_next = (clone $date_obj)->modify('+1 day')->format('Y-m-d');

// Format Tanggal Indonesia
$hari_array = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$bulan_array = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$wd = $date_obj->format('w');
$j = $date_obj->format('j');
$n = $date_obj->format('n');
$Y = $date_obj->format('Y');
$formatted_date_text = $hari_array[$wd] . ", " . $j . " " . $bulan_array[$n] . " " . $Y;

// ==========================================
// QUERY DATA PENJUALAN
// ==========================================
$query_list = "SELECT p.*, b.nama AS nama_barang_asli, b.tipe, b.warna, b.internal
               FROM penjualan p 
               LEFT JOIN barang b ON p.imei = b.imei 
               WHERE DATE(p.tanggal) = '$selected_date' 
               ORDER BY p.tanggal DESC, p.id DESC";
$result_list = mysqli_query($koneksi, $query_list);
$total_rows = mysqli_num_rows($result_list);

$total_transaksi = $total_rows;
$q_pendapatan = mysqli_query($koneksi, "SELECT SUM(harga_jual) AS total FROM penjualan WHERE DATE(tanggal) = '$selected_date'");
$total_pendapatan = mysqli_fetch_assoc($q_pendapatan)['total'] ?? 0;

$page_title = "Riwayat Penjualan · Happy Cellular";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $page_title ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-dark: #0A192F;
            --primary-blue: #1E4A7A;
            --accent-blue: #5D9BEC;
            --bg-body: #f4f7fb;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --success-green: #10B981;
            --danger-red: #EF4444;
            --purple: #8B5CF6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            min-height: 100vh;
            color: var(--text-dark);
            padding-bottom: 2rem;
        }

        /* NAVBAR */
        .navbar-custom {
            background: var(--primary-dark);
            padding: 0.9rem 2rem;
            box-shadow: 0 10px 30px -10px rgba(0,20,40,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.3rem;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        
        .navbar-logo {
            width: 32px;
            height: 32px;
            object-fit: contain;
            border-radius: 6px;
        }

        .user-badge {
            background: rgba(255,255,255,0.1);
            padding: 8px 18px;
            border-radius: 40px;
            color: white;
            font-weight: 500;
            font-size: 0.85rem;
            border: 1px solid rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .dropdown-menu-custom {
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(30, 41, 59, 0.15);
            border: none;
            padding: 8px;
        }

        .dropdown-menu-custom .dropdown-item {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-dark);
            padding: 10px 14px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dropdown-menu-custom .dropdown-item i {
            color: var(--primary-blue);
            font-size: 0.95rem;
            width: 16px;
            text-align: center;
        }

        .dropdown-menu-custom .dropdown-item:hover {
            background: rgba(30, 74, 122, 0.05);
            color: var(--primary-blue);
        }

        /* MAIN WRAPPER */
        .main-wrapper {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* NAVIGASI TANGGAL */
        .date-navigation-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border-radius: 20px;
            padding: 12px 16px;
            margin-bottom: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.03);
        }

        .btn-date-nav {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: #F1F5F9;
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 1.2rem;
        }

        .btn-date-nav:hover {
            background: var(--primary-blue);
            color: #fff;
        }

        .current-date-display {
            text-align: center;
            flex-grow: 1;
        }

        .current-date-display h4 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-dark);
            margin: 0;
            text-transform: capitalize;
        }

        .current-date-display small {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* METRICS */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .metric-mini-card {
            background: #fff;
            padding: 14px 12px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.03);
            box-shadow: 0 8px 20px rgba(0,0,0,0.02);
        }

        .metric-mini-card .m-title {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            display: block;
            margin-bottom: 6px;
        }

        .metric-mini-card .m-value {
            font-family: 'Poppins', sans-serif;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* LIST TRANSAKSI */
        .section-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }

        .transaction-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .transaction-card-row {
            display: flex;
            align-items: center;
            padding: 14px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }

        .transaction-card-row:hover {
            border-color: rgba(30, 74, 122, 0.15);
            box-shadow: 0 8px 25px rgba(0,0,0,0.05);
            transform: translateY(-1px);
        }

        .trans-icon-box {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 14px;
            flex-shrink: 0;
        }

        .trans-info-area {
            flex-grow: 1;
            min-width: 0;
        }

        .trans-product-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-dark);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .trans-meta-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 2px;
        }

        .trans-imei-code {
            font-family: monospace;
            background: #F1F5F9;
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--text-dark);
            font-weight: 600;
        }

        .trans-payment-badge {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            background: rgba(30, 74, 122, 0.1);
            color: var(--primary-blue);
            padding: 3px 8px;
            border-radius: 6px;
            margin-right: 4px;
        }

        .trans-tt-badge {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            background: rgba(139, 92, 246, 0.1);
            color: var(--purple);
            padding: 3px 8px;
            border-radius: 6px;
        }

        .trans-service-badge {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            background: #DBEAFE;
            color: #1E40AF;
            padding: 3px 8px;
            border-radius: 6px;
        }

        .trans-amount-side {
            text-align: right;
            flex-shrink: 0;
            margin-left: 12px;
        }

        .trans-price-val {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-dark);
        }

        /* FOOTER NOTE */
        .footer-note {
            margin-top: 3rem;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-muted);
            letter-spacing: 1px;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .navbar-custom { padding: 0.8rem 1rem; }
            .navbar-brand span { font-size: 1rem; }
            .user-badge { display: none; }
            .main-wrapper { padding: 0 1rem; margin-top: 1.5rem; }
            .metrics-grid { grid-template-columns: 1fr 1fr; }
            .transaction-container { gap: 10px; }
        }
    </style>
</head>
<body>

<nav class="navbar-custom">
    <a href="home_frontliner.php" class="navbar-brand">
        <img src="/../asset/happy.png" alt="Happy Cellular Logo" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=0A192F&color=fff'">
        <span>Happy Cellular · Riwayat</span>
    </a>
    <div class="dropdown">
        <button class="user-badge" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-user-shield text-info"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Frontliner') ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom mt-2">
            <li><a class="dropdown-item" href="ganti_password.php"><i class="fas fa-key"></i> Ganti Password</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
        </ul>
    </div>
</nav>

<div class="main-wrapper">
    <!-- TANGGAL NAVIGASI BAR -->
    <div class="date-navigation-bar">
        <a href="view_penjualan.php?date=<?= $date_prev ?>" class="btn-date-nav" title="Hari Sebelumnya">
            <i class="fas fa-chevron-left"></i>
        </a>
        <div class="current-date-display">
            <h4><?= $formatted_date_text ?></h4>
            <small><?= ($selected_date == date('Y-m-d')) ? 'Hari Ini' : 'Arsip Penjualan' ?></small>
        </div>
        <a href="view_penjualan.php?date=<?= $date_next ?>" class="btn-date-nav" title="Hari Berikutnya">
            <i class="fas fa-chevron-right"></i>
        </a>
    </div>

    <!-- METRICS -->
    <div class="metrics-grid">
        <div class="metric-mini-card">
            <span class="m-title">Total Transaksi</span>
            <div class="m-value"><?= number_format($total_transaksi, 0, ',', '.') ?> Item</div>
        </div>
        <div class="metric-mini-card">
            <span class="m-title">Total Pendapatan</span>
            <div class="m-value">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
        </div>
    </div>

    <div class="section-title">
        <i class="fas fa-list-ul" style="color: var(--primary-blue);"></i> Rincian Transaksi
    </div>
    
    <!-- LIST TRANSAKSI -->
    <div class="transaction-container">
        <?php if($total_rows > 0): ?>
            <?php while($row = mysqli_fetch_assoc($result_list)): 
                $nama_barang = $row['nama_barang_asli'] ?? 'Produk Terhapus';
                $tipe = $row['tipe'] ?? '';
                $warna = $row['warna'] ?? '';
                $internal = $row['internal'] ?? '';
                $spec = trim(($tipe ? $tipe . ' · ' : '') . ($warna ? $warna . ' · ' : '') . ($internal ? $internal . 'GB' : ''), ' · ');
                $imei_display = $row['imei'] ?? '-';
                $harga_jual = $row['harga_jual'] ?? 0;
                $jenis = $row['jenis_transaksi'] ?? 'HP';
                $is_service = ($jenis == 'SERVICE');
                $is_tt = ($jenis == 'TUKAR TAMBAH' || $jenis == 'TT');
            ?>
            <div class="transaction-card-row">
                <div class="trans-icon-box" style="background: <?= $is_service ? 'rgba(59,130,246,0.1)' : ($is_tt ? 'rgba(139,92,246,0.1)' : 'rgba(16, 185, 129, 0.1)') ?>; color: <?= $is_service ? '#3B82F6' : ($is_tt ? '#8B5CF6' : '#10B981') ?>;">
                    <i class="fas <?= $is_service ? 'fa-tools' : ($is_tt ? 'fa-exchange-alt' : 'fa-mobile-alt') ?>"></i>
                </div>
                
                <div class="trans-info-area">
                    <div class="trans-product-name"><?= htmlspecialchars($nama_barang) ?></div>
                    <div class="trans-meta-sub">
                        <?php if ($spec): ?>
                            <span class="text-dark fw-semibold d-block mb-1" style="font-size:0.75rem;"><?= htmlspecialchars($spec) ?></span>
                        <?php endif; ?>
                        IMEI: <span class="trans-imei-code"><?= htmlspecialchars($imei_display) ?></span>
                    </div>
                    <div class="mt-1">
                        <span class="trans-payment-badge"><?= htmlspecialchars($row['metode_pembayaran'] ?? 'Cash') ?></span>
                        <?php if ($is_service): ?>
                            <span class="trans-service-badge">SERVICE</span>
                        <?php elseif ($is_tt): ?>
                            <span class="trans-tt-badge">TUKAR TAMBAH</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="trans-amount-side">
                    <div class="trans-price-val">Rp <?= number_format($harga_jual, 0, ',', '.') ?></div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-5 bg-white rounded-4 border">
                <i class="fa-solid fa-basket-shopping fa-2x mb-2 text-muted d-block"></i>
                <h6 class="text-muted fw-bold">Tidak ada penjualan</h6>
                <p class="text-muted small mb-0">Belum ada catatan transaksi pada tanggal ini.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer-note">
        <i class="fas fa-info-circle me-1"></i> HAPPY CELLULAR V2.0 POS SYSTEM • <?= date('Y') ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>