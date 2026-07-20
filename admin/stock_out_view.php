<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Stock Out · Happy Cellular</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="asset/css/admin_stock_out.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/@ericblade/quagga2/dist/quagga.min.js"></script>
</head>
<body>

<div class="desktop-sidebar">
    <div class="sidebar-brand">
        <img src="asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
        <div class="logo-text">Happy Cell<small>Stock Out</small></div>
    </div>
    <div class="sidebar-menu">
        <a href="index.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
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
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" style="border-radius: 12px; padding: 8px;">
                <li><a class="dropdown-item fw-semibold" href="kelola_user.php"><i class="fas fa-users-cog me-2"></i> Kelola User</a></li>
                <li><a class="dropdown-item fw-semibold" href="ganti_password.php"><i class="fas fa-key me-2"></i> Ganti Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger fw-bold" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Keluar</a></li>
            </ul>
        </div>
    </div>

    <div class="main-wrapper">
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

<nav class="bottom-nav">
    <a href="home_admin.php" class="nav-item">
        <i class="fas fa-th-large"></i><span>Dashboard</span>
    </a>
    <a href="stock_in.php" class="nav-item">
        <i class="fas fa-arrow-circle-down" style="color: #10B981;"></i><span>Stock In</span>
    </a>
    <a href="admin_stock_out.php" class="nav-item active">
        <i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i><span>Stock Out</span>
    </a>
    <a href="return.php" class="nav-item">
        <i class="fas fa-rotate-left" style="color: #F59E0B;"></i><span>Retur</span>
    </a>
    <div class="nav-item" id="moreMenuBtn" role="button">
        <i class="fas fa-bars"></i><span>Menu</span>
    </div>
</nav>

<div class="more-menu-popup" id="moreMenuPopup">
    <a href="data_master.php"><i class="fas fa-database"></i> Data Master</a>
    <a href="data_barang.php"><i class="fas fa-box"></i> Data Produk</a>
    <a href="penjualan.php"><i class="fas fa-shopping-cart"></i> Transaksi Jual</a>
    <a href="keuangan.php"><i class="fas fa-chart-pie"></i> Analisis Laba</a>
    <a href="manajemen_label.php"><i class="fas fa-print"></i> Cetak Label</a>
    <a href="tukar_tambah.php"><i class="fas fa-exchange-alt"></i> Tukar Tambah</a>
    <a href="service_hp.php"><i class="fas fa-tools"></i> Service HP</a>
    <a href="beli_customer.php"><i class="fas fa-tools"></i> Buy Out</a>
</div>
<div class="popup-overlay" id="popupOverlay"></div>

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="asset/js/admin_stock_out.js"></script>
</body>
</html>