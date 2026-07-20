<?php
session_start();
require_once '../api/auth_helper.php';
restrictTo(['frontliner']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard Frontliner · Happy Cellular</title>
    
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
            --card-bg: #ffffff;
            --card-border: rgba(30, 74, 122, 0.08);
            --card-shadow: 0 15px 35px rgba(10, 25, 47, 0.05);
            --navbar-bg: var(--primary-dark);
            --btn-logout-bg: #fff;
            --btn-logout-color: #ef4444;
            --btn-logout-border: #fee2e2;
            --footer-color: var(--text-muted);
        }

        /* Dark mode overrides */
        body.dark-mode {
            --bg-body: #0d1117;
            --text-dark: #e6edf3;
            --text-muted: #8b949e;
            --card-bg: #161b22;
            --card-border: rgba(48, 54, 61, 0.5);
            --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            --navbar-bg: #0a0e14;
            --btn-logout-bg: #21262d;
            --btn-logout-color: #f85149;
            --btn-logout-border: #2d1b1b;
            --footer-color: #484f58;
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
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* NAVBAR - Konsisten dengan Admin */
        .navbar-custom {
            background: var(--navbar-bg);
            padding: 0.9rem 2rem;
            box-shadow: 0 10px 30px -10px rgba(0,20,40,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: background 0.3s ease;
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
        }

        /* Dark mode toggle button */
        .dark-toggle {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 40px;
            color: white;
            padding: 6px 14px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .dark-toggle:hover {
            background: rgba(255,255,255,0.2);
        }

        .dark-toggle i {
            font-size: 1.1rem;
        }

        /* MAIN WRAPPER */
        .main-wrapper {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* HEADER */
        .page-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .page-header h2 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            color: var(--text-dark);
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            transition: color 0.3s ease;
        }

        .page-header p {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 1rem;
            transition: color 0.3s ease;
        }

        /* MENU GRID */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .menu-card {
            background: var(--card-bg);
            border-radius: 28px;
            padding: 35px 20px;
            text-decoration: none;
            color: var(--text-dark);
            transition: all 0.35s cubic-bezier(0.2, 0.9, 0.4, 1);
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .menu-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(10, 25, 47, 0.12);
            border-color: var(--accent-blue);
        }

        body.dark-mode .menu-card:hover {
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            border-color: var(--accent-blue);
        }

        .menu-icon {
            width: 75px;
            height: 75px;
            background: linear-gradient(145deg, #f1f5ff, #e2eafc);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            color: var(--primary-blue);
            margin-bottom: 20px;
            transition: all 0.4s ease;
        }

        body.dark-mode .menu-icon {
            background: #1c2333;
            color: #58a6ff;
        }

        .menu-card:hover .menu-icon {
            background: var(--primary-blue);
            color: white;
            transform: scale(1.1) rotate(-5deg);
        }

        body.dark-mode .menu-card:hover .menu-icon {
            background: var(--primary-blue);
            color: white;
        }

        .menu-card h4 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: var(--text-dark);
            transition: color 0.3s ease;
        }

        .menu-card p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin: 0;
            line-height: 1.5;
            transition: color 0.3s ease;
        }

        /* LOGOUT BUTTON */
        .logout-wrapper {
            margin-top: 4rem;
            text-align: center;
        }

        .btn-logout {
            background: var(--btn-logout-bg);
            color: var(--btn-logout-color);
            padding: 12px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
            border: 1px solid var(--btn-logout-border);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.08);
        }

        .btn-logout:hover {
            background: #ef4444;
            color: white;
            transform: translateY(-2px);
            border-color: #ef4444;
        }

        body.dark-mode .btn-logout:hover {
            background: #f85149;
            color: #0d1117;
            border-color: #f85149;
        }

        /* FOOTER */
        .footer-note {
            margin-top: 4rem;
            text-align: center;
            font-size: 0.75rem;
            color: var(--footer-color);
            letter-spacing: 1px;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        /* MOBILE ADJUSTMENTS */
        @media (max-width: 768px) {
            .navbar-custom { padding: 0.8rem 1rem; }
            .navbar-brand span { font-size: 1rem; }
            .user-badge { display: none; } /* Ringkas nav di HP */
            
            .main-wrapper { margin-top: 1.5rem; padding: 0 1rem; }
            .page-header h2 { font-size: 1.8rem; }
            .page-header p { font-size: 0.9rem; }

            .menu-grid {
                grid-template-columns: repeat(2, 1fr); /* 2 Kolom di HP agar akses cepat */
                gap: 12px;
            }

            .menu-card {
                padding: 25px 10px;
                border-radius: 22px;
            }

            .menu-icon {
                width: 60px;
                height: 60px;
                font-size: 1.6rem;
                margin-bottom: 12px;
                border-radius: 18px;
            }

            .menu-card h4 { font-size: 1rem; margin-bottom: 0; }
            .menu-card p { display: none; } /* Sembunyikan p di HP agar kartu tetap kotak */
            
            .btn-logout { width: 100%; justify-content: center; } /* Logout full width di HP */
            
            .dark-toggle span { display: none; }
            .dark-toggle { padding: 6px 12px; }
        }
    </style>
</head>
<body>

<nav class="navbar-custom">
    <a href="#" class="navbar-brand">
        <img src="/../asset/happy.png" alt="Happy Cellular Logo" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=0A192F&color=fff'">
        <span>Happy Cellular · Frontliner</span>
    </a>
    <div class="d-flex align-items-center gap-3">
        <button class="dark-toggle" id="darkModeToggle" aria-label="Toggle Dark Mode">
            <i class="fas fa-moon"></i>
            <span>Mode</span>
        </button>
        <div class="user-badge">
            <i class="fas fa-user-shield text-info"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Frontliner'); ?>
        </div>
    </div>
</nav>

<div class="main-wrapper">
    <div class="page-header">
        <h2>Dashboard</h2>
        <p>Akses cepat layanan dan inventaris toko</p>
    </div>

    <div class="menu-grid">

        <a href="view_stock.php" class="menu-card">
            <div class="menu-icon">
                <i class="fas fa-boxes-stacked"></i>
            </div>
            <h4>Stok Barang</h4>
            <p>Cek ketersediaan produk & IMEI</p>
        </a>

        <a href="view_penjualan.php" class="menu-card">
            <div class="menu-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <h4>Riwayat Trx</h4>
            <p>Lihat data penjualan yang diproses</p>
        </a>

        <a href="frontliner_stock_out.php" class="menu-card">
            <div class="menu-icon">
                <i class="fas fa-qrcode"></i>
            </div>
            <h4>Stock Out</h4>
            <p>Keluarkan barang dengan scan IMEI</p>
        </a>
    </div>

    <div class="logout-wrapper">
        <a href="../logout.php" class="btn-logout">
            <i class="fas fa-power-off"></i> Keluar Aplikasi
        </a>
    </div>

    <div class="footer-note">
        <i class="fas fa-info-circle me-1"></i> HAPPY CELLULAR  V2.0 POS SYSTEM • 2026
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function() {
        // Dark mode toggle
        const toggle = document.getElementById('darkModeToggle');
        const icon = toggle.querySelector('i');
        const label = toggle.querySelector('span');

        // Cek preferensi tersimpan
        let darkMode = localStorage.getItem('frontlinerDarkMode') === 'true';
        if (darkMode) {
            document.body.classList.add('dark-mode');
            icon.className = 'fas fa-sun';
            label.textContent = 'Terang';
        }

        toggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('frontlinerDarkMode', isDark);
            if (isDark) {
                icon.className = 'fas fa-sun';
                label.textContent = 'Terang';
            } else {
                icon.className = 'fas fa-moon';
                label.textContent = 'Mode';
            }
        });
    })();
</script>
</body>
</html>