<?php
session_start();
require_once '../api/auth_helper.php';
restrictTo(['admin']);
include '../connection.php';

$today = date('Y-m-d');
$month_start = date('Y-m-01');

// ==========================================
// KPI SUMMARY
// ==========================================

// 1. Total Stok Ready & Nilai Modal
$q_stok = mysqli_query($koneksi, "SELECT COUNT(*) as total_unit, COALESCE(SUM(modal), 0) as total_modal FROM barang WHERE status = 'READY'");
$stok = mysqli_fetch_assoc($q_stok);
$total_unit = $stok['total_unit'] ?? 0;
$total_modal_stok = $stok['total_modal'] ?? 0;

// 2. Total Nilai Jual Stok
$q_nilai_jual = mysqli_query($koneksi, "SELECT COALESCE(SUM(harga_jual), 0) as total FROM barang WHERE status = 'READY'");
$total_nilai_jual = mysqli_fetch_assoc($q_nilai_jual)['total'] ?? 0;

// 3. Potensi Profit Stok
$potensi_profit = $total_nilai_jual - $total_modal_stok;

// 4. Penjualan Hari Ini
$q_penjualan_hari = mysqli_query($koneksi, "SELECT COUNT(*) as total_trx, COALESCE(SUM(harga_jual), 0) as total_omzet FROM penjualan WHERE DATE(tanggal) = '$today'");
$penjualan_hari = mysqli_fetch_assoc($q_penjualan_hari);
$trx_hari_ini = $penjualan_hari['total_trx'] ?? 0;
$omzet_hari_ini = $penjualan_hari['total_omzet'] ?? 0;

// 5. Penjualan Bulan Ini
$q_penjualan_bulan = mysqli_query($koneksi, "SELECT COUNT(*) as total_trx, COALESCE(SUM(harga_jual), 0) as total_omzet FROM penjualan WHERE tanggal >= '$month_start'");
$penjualan_bulan = mysqli_fetch_assoc($q_penjualan_bulan);
$trx_bulan_ini = $penjualan_bulan['total_trx'] ?? 0;
$omzet_bulan_ini = $penjualan_bulan['total_omzet'] ?? 0;

// 6. Laba Hari Ini
$q_laba_hari = mysqli_query($koneksi, "SELECT 
    COALESCE(SUM(
        CASE 
            WHEN p.jenis_transaksi = 'SERVICE' THEN p.harga_jual
            WHEN tt.id IS NOT NULL THEN tt.laba
            ELSE p.harga_jual - COALESCE(p.modal, 0)
        END
    ), 0) as total_laba
    FROM penjualan p
    LEFT JOIN tukar_tambah tt ON p.imei = tt.imei_baru
    WHERE DATE(p.tanggal) = '$today'");
$laba_hari_ini = mysqli_fetch_assoc($q_laba_hari)['total_laba'] ?? 0;

// 7. Laba Bulan Ini
$q_laba_bulan = mysqli_query($koneksi, "SELECT 
    COALESCE(SUM(
        CASE 
            WHEN p.jenis_transaksi = 'SERVICE' THEN p.harga_jual
            WHEN tt.id IS NOT NULL THEN tt.laba
            ELSE p.harga_jual - COALESCE(p.modal, 0)
        END
    ), 0) as total_laba
    FROM penjualan p
    LEFT JOIN tukar_tambah tt ON p.imei = tt.imei_baru
    WHERE p.tanggal >= '$month_start'");
$laba_bulan_ini = mysqli_fetch_assoc($q_laba_bulan)['total_laba'] ?? 0;

// ==========================================
// GRAFIK PENJUALAN 30 HARI
// ==========================================
$q_grafik_30 = mysqli_query($koneksi, "SELECT 
    DATE(tanggal) as tanggal,
    COUNT(*) as jumlah_trx,
    COALESCE(SUM(harga_jual), 0) as omzet
    FROM penjualan 
    WHERE tanggal >= DATE_SUB('$today', INTERVAL 30 DAY)
    GROUP BY DATE(tanggal)
    ORDER BY tanggal ASC");

$grafik_30 = [];
while($row = mysqli_fetch_assoc($q_grafik_30)) {
    $grafik_30[] = $row;
}

// ==========================================
// GRAFIK PENJUALAN 12 BULAN
// ==========================================
$q_grafik_12 = mysqli_query($koneksi, "SELECT 
    DATE_FORMAT(tanggal, '%Y-%m') as bulan,
    DATE_FORMAT(tanggal, '%M %Y') as bulan_nama,
    COUNT(*) as jumlah_trx,
    COALESCE(SUM(harga_jual), 0) as omzet
    FROM penjualan 
    WHERE tanggal >= DATE_SUB('$today', INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
    ORDER BY bulan ASC");

$grafik_12 = [];
while($row = mysqli_fetch_assoc($q_grafik_12)) {
    $grafik_12[] = $row;
}

// ==========================================
// INVENTORY ANALYTICS
// ==========================================

// 1. Produk Aktif (unik berdasarkan nama)
$q_produk_aktif = mysqli_query($koneksi, "SELECT COUNT(DISTINCT nama) as total FROM barang WHERE status = 'READY'");
$total_produk_aktif = mysqli_fetch_assoc($q_produk_aktif)['total'] ?? 0;

// 2. Total IMEI Aktif
$q_imei_aktif = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM barang WHERE status = 'READY' AND imei IS NOT NULL AND imei != ''");
$total_imei_aktif = mysqli_fetch_assoc($q_imei_aktif)['total'] ?? 0;

// 3. Total IMEI Terjual
$q_imei_terjual = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM barang WHERE status IN ('SOLD', 'NO READY')");
$total_imei_terjual = mysqli_fetch_assoc($q_imei_terjual)['total'] ?? 0;

// 4. Stok Menipis (< 3 unit per produk)
$q_stok_menipis = mysqli_query($koneksi, "SELECT nama, COUNT(*) as stok 
    FROM barang 
    WHERE status = 'READY' 
    GROUP BY nama 
    HAVING stok < 3 AND stok > 0
    ORDER BY stok ASC
    LIMIT 5");

$stok_menipis = [];
while($row = mysqli_fetch_assoc($q_stok_menipis)) {
    $stok_menipis[] = $row;
}

// ==========================================
// SALES ANALYTICS
// ==========================================

// 1. Top 10 Produk Terlaris
$q_top_terlaris = mysqli_query($koneksi, "SELECT b.nama, COUNT(*) as qty_terjual, COALESCE(SUM(p.harga_jual), 0) as omzet
    FROM penjualan p
    JOIN barang b ON p.imei = b.imei
    WHERE p.tanggal >= DATE_SUB('$today', INTERVAL 30 DAY)
    GROUP BY b.nama
    ORDER BY qty_terjual DESC
    LIMIT 10");

$top_terlaris = [];
while($row = mysqli_fetch_assoc($q_top_terlaris)) {
    $top_terlaris[] = $row;
}

// 2. Top 10 Produk Profit Tertinggi
$q_top_profit = mysqli_query($koneksi, "SELECT b.nama, 
    COALESCE(SUM(
        CASE 
            WHEN p.jenis_transaksi = 'SERVICE' THEN p.harga_jual
            WHEN tt.id IS NOT NULL THEN tt.laba
            ELSE p.harga_jual - COALESCE(p.modal, 0)
        END
    ), 0) as profit
    FROM penjualan p
    JOIN barang b ON p.imei = b.imei
    LEFT JOIN tukar_tambah tt ON p.imei = tt.imei_baru
    WHERE p.tanggal >= DATE_SUB('$today', INTERVAL 30 DAY)
    GROUP BY b.nama
    ORDER BY profit DESC
    LIMIT 10");

$top_profit = [];
while($row = mysqli_fetch_assoc($q_top_profit)) {
    $top_profit[] = $row;
}

// ==========================================
// PROFIT ANALYTICS
// ==========================================

// 1. Profit per Brand
$q_profit_brand = mysqli_query($koneksi, "SELECT 
    SUBSTRING_INDEX(b.nama, ' ', 2) as brand,
    COALESCE(SUM(
        CASE 
            WHEN p.jenis_transaksi = 'SERVICE' THEN p.harga_jual
            WHEN tt.id IS NOT NULL THEN tt.laba
            ELSE p.harga_jual - COALESCE(p.modal, 0)
        END
    ), 0) as profit
    FROM penjualan p
    JOIN barang b ON p.imei = b.imei
    LEFT JOIN tukar_tambah tt ON p.imei = tt.imei_baru
    WHERE p.tanggal >= DATE_SUB('$today', INTERVAL 30 DAY)
    GROUP BY brand
    ORDER BY profit DESC");

$profit_brand = [];
while($row = mysqli_fetch_assoc($q_profit_brand)) {
    $profit_brand[] = $row;
}

// 2. Margin Profit
$q_margin = mysqli_query($koneksi, "SELECT 
    COALESCE(SUM(harga_jual), 0) as total_omzet,
    COALESCE(SUM(
        CASE 
            WHEN p.jenis_transaksi = 'SERVICE' THEN p.harga_jual
            WHEN tt.id IS NOT NULL THEN tt.laba
            ELSE p.harga_jual - COALESCE(p.modal, 0)
        END
    ), 0) as total_laba
    FROM penjualan p
    LEFT JOIN tukar_tambah tt ON p.imei = tt.imei_baru
    WHERE p.tanggal >= DATE_SUB('$today', INTERVAL 30 DAY)");

$margin = mysqli_fetch_assoc($q_margin);
$total_omzet_30 = $margin['total_omzet'] ?? 0;
$total_laba_30 = $margin['total_laba'] ?? 0;
$margin_persen = $total_omzet_30 > 0 ? round(($total_laba_30 / $total_omzet_30) * 100, 2) : 0;

// ==========================================
// CASHFLOW
// ==========================================

// 1. Uang Masuk Hari Ini
$q_kas_hari = mysqli_query($koneksi, "SELECT 
    COALESCE(SUM(
        CASE 
            WHEN p.status_pencairan = 'Cair' OR p.metode_pembayaran LIKE '%Cash%' THEN p.harga_jual - COALESCE(p.dp, 0)
            WHEN p.dp IS NOT NULL THEN p.dp
            ELSE 0
        END
    ), 0) as uang_masuk
    FROM penjualan p
    WHERE DATE(p.tanggal) = '$today'");

$uang_masuk_hari = mysqli_fetch_assoc($q_kas_hari)['uang_masuk'] ?? 0;

// 2. Uang Keluar Hari Ini
$q_kas_keluar_hari = mysqli_query($koneksi, "SELECT 
    COALESCE(SUM(b.modal), 0) as uang_keluar
    FROM stock_out so
    JOIN barang b ON so.imei = b.imei
    WHERE DATE(so.tanggal) = '$today'
    AND so.keterangan NOT LIKE '%Penjualan%'
    AND so.keterangan NOT LIKE '%Cicilan%'");

$uang_keluar_hari = mysqli_fetch_assoc($q_kas_keluar_hari)['uang_keluar'] ?? 0;

// 3. Uang Masuk Bulan Ini
$q_kas_bulan = mysqli_query($koneksi, "SELECT 
    COALESCE(SUM(
        CASE 
            WHEN p.status_pencairan = 'Cair' OR p.metode_pembayaran LIKE '%Cash%' THEN p.harga_jual - COALESCE(p.dp, 0)
            WHEN p.dp IS NOT NULL THEN p.dp
            ELSE 0
        END
    ), 0) as uang_masuk
    FROM penjualan p
    WHERE p.tanggal >= '$month_start'");

$uang_masuk_bulan = mysqli_fetch_assoc($q_kas_bulan)['uang_masuk'] ?? 0;

// 4. Uang Keluar Bulan Ini
$q_kas_keluar_bulan = mysqli_query($koneksi, "SELECT 
    COALESCE(SUM(b.modal), 0) as uang_keluar
    FROM stock_out so
    JOIN barang b ON so.imei = b.imei
    WHERE so.tanggal >= '$month_start'
    AND so.keterangan NOT LIKE '%Penjualan%'
    AND so.keterangan NOT LIKE '%Cicilan%'");

$uang_keluar_bulan = mysqli_fetch_assoc($q_kas_keluar_bulan)['uang_keluar'] ?? 0;

// 5. Cashflow Bulanan
$q_cashflow_bulanan = mysqli_query($koneksi, "SELECT 
    DATE_FORMAT(p.tanggal, '%M %Y') as bulan,
    COALESCE(SUM(
        CASE 
            WHEN p.status_pencairan = 'Cair' OR p.metode_pembayaran LIKE '%Cash%' THEN p.harga_jual - COALESCE(p.dp, 0)
            WHEN p.dp IS NOT NULL THEN p.dp
            ELSE 0
        END
    ), 0) as pemasukan
    FROM penjualan p
    WHERE p.tanggal >= DATE_SUB('$today', INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(p.tanggal, '%Y-%m')
    ORDER BY tanggal ASC");

$cashflow_bulanan = [];
while($row = mysqli_fetch_assoc($q_cashflow_bulanan)) {
    $cashflow_bulanan[] = $row;
}

// ==========================================
// ALERT CENTER
// ==========================================

// 1. IMEI Duplikat
$q_imei_duplikat = mysqli_query($koneksi, "SELECT imei, COUNT(*) as jumlah 
    FROM barang 
    WHERE imei IS NOT NULL AND imei != ''
    GROUP BY imei 
    HAVING jumlah > 1
    LIMIT 5");

$imei_duplikat = [];
while($row = mysqli_fetch_assoc($q_imei_duplikat)) {
    $imei_duplikat[] = $row;
}

// 2. Harga Modal Kosong
$q_modal_kosong = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM barang WHERE status = 'READY' AND (modal IS NULL OR modal = 0)");
$modal_kosong = mysqli_fetch_assoc($q_modal_kosong)['total'] ?? 0;

// 3. Harga Jual Kosong
$q_harga_kosong = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM barang WHERE status = 'READY' AND (harga_jual IS NULL OR harga_jual = 0)");
$harga_kosong = mysqli_fetch_assoc($q_harga_kosong)['total'] ?? 0;

// ==========================================
// ACTIVITY LOG
// ==========================================

// 1. Stock Out Terbaru
$q_activity_stock = mysqli_query($koneksi, "SELECT 
    so.tanggal,
    so.keterangan,
    'Stock Out' as aktivitas
    FROM stock_out so
    ORDER BY so.tanggal DESC
    LIMIT 10");

$activity_stock = [];
while($row = mysqli_fetch_assoc($q_activity_stock)) {
    $activity_stock[] = $row;
}

// 2. Penjualan Terbaru
$q_activity_penjualan = mysqli_query($koneksi, "SELECT 
    p.tanggal,
    CONCAT('Penjualan: ', b.nama) as keterangan,
    'Penjualan' as aktivitas
    FROM penjualan p
    JOIN barang b ON p.imei = b.imei
    ORDER BY p.tanggal DESC
    LIMIT 10");

$activity_penjualan = [];
while($row = mysqli_fetch_assoc($q_activity_penjualan)) {
    $activity_penjualan[] = $row;
}

// Gabungkan dan urutkan
$activity_log = array_merge($activity_stock, $activity_penjualan);
usort($activity_log, function($a, $b) {
    return strtotime($b['tanggal']) - strtotime($a['tanggal']);
});
$activity_log = array_slice($activity_log, 0, 10);

// ==========================================
// SMART RECOMMENDATION
// ==========================================

// 1. Rekomendasi Restock
$q_restock = mysqli_query($koneksi, "SELECT b.nama, 
    COUNT(p.id) as qty_terjual,
    (SELECT COUNT(*) FROM barang WHERE nama = b.nama AND status = 'READY') as stok_saat_ini
    FROM barang b
    JOIN penjualan p ON b.imei = p.imei
    WHERE p.tanggal >= DATE_SUB('$today', INTERVAL 30 DAY)
    GROUP BY b.nama
    HAVING stok_saat_ini < (qty_terjual / 30 * 30)
    ORDER BY qty_terjual DESC
    LIMIT 3");

$rekomendasi_restock = [];
while($row = mysqli_fetch_assoc($q_restock)) {
    $row['rekomendasi'] = max(0, round($row['qty_terjual'] / 30 * 30) - $row['stok_saat_ini']);
    $rekomendasi_restock[] = $row;
}

// 2. Rekomendasi Promo
$q_promo = mysqli_query($koneksi, "SELECT b.nama, 
    DATEDIFF('$today', MAX(p.tanggal)) as hari_tidak_terjual,
    (SELECT COUNT(*) FROM barang WHERE nama = b.nama AND status = 'READY') as stok
    FROM barang b
    LEFT JOIN penjualan p ON b.imei = p.imei
    WHERE b.status = 'READY'
    GROUP BY b.nama
    HAVING hari_tidak_terjual > 30 OR hari_tidak_terjual IS NULL
    ORDER BY hari_tidak_terjual DESC
    LIMIT 3");

$rekomendasi_promo = [];
while($row = mysqli_fetch_assoc($q_promo)) {
    $rekomendasi_promo[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard · Happy Cellular</title>
    <link rel="shortcut icon" href="../asset/Happy.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

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

        .main-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 1.2rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 2rem 0 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--surface-card);
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.01);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        .stat-icon-box {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 12px;
        }

        .stat-value {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .chart-container {
            background: var(--surface-card);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
            margin-bottom: 1.5rem;
        }

        .analytics-card {
            background: var(--surface-card);
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        }

        .analytics-title {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .analytics-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #F1F5F9;
        }

        .analytics-item:last-child { border-bottom: none; }

        .analytics-item-name {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-main);
        }

        .analytics-item-value {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--primary-dongker);
        }

        .alert-card {
            background: var(--surface-card);
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            margin-bottom: 1.5rem;
        }

        .alert-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 8px;
        }

        .alert-item:last-child { margin-bottom: 0; }

        .alert-item.critical { background: rgba(239, 68, 68, 0.05); border-left: 3px solid var(--danger-red); }
        .alert-item.warning { background: rgba(245, 158, 11, 0.05); border-left: 3px solid var(--warning-orange); }
        .alert-item.normal { background: rgba(16, 185, 129, 0.05); border-left: 3px solid var(--success-green); }

        .alert-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .alert-item.critical .alert-icon { background: rgba(239, 68, 68, 0.1); color: var(--danger-red); }
        .alert-item.warning .alert-icon { background: rgba(245, 158, 11, 0.1); color: var(--warning-orange); }
        .alert-item.normal .alert-icon { background: rgba(16, 185, 129, 0.1); color: var(--success-green); }

        .alert-content { flex: 1; }
        .alert-title { font-weight: 700; font-size: 0.85rem; margin-bottom: 2px; }
        .alert-desc { font-size: 0.75rem; color: var(--text-muted); }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px;
            border-bottom: 1px solid #F1F5F9;
        }

        .activity-item:last-child { border-bottom: none; }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--primary-dongker-soft);
            color: var(--primary-dongker);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.9rem;
        }

        .activity-content { flex: 1; }
        .activity-title { font-weight: 600; font-size: 0.85rem; }
        .activity-time { font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; }

        .recommendation-card {
            background: linear-gradient(135deg, #EEF4FA 0%, #F8FAFC 100%);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-dongker);
        }

        .recommendation-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 0;
        }

        .recommendation-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--primary-dongker);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .recommendation-content { flex: 1; }
        .recommendation-title { font-weight: 700; font-size: 0.85rem; margin-bottom: 4px; }
        .recommendation-desc { font-size: 0.75rem; color: var(--text-muted); line-height: 1.5; }

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
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.08);
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
            .main-wrapper { max-width: 1400px; width: 100%; padding: 1.5rem 2rem; margin: 0; }
        }
    </style>
</head>
<body>

<div class="desktop-sidebar">
    <div class="sidebar-brand">
        <img src="../asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
        <div class="logo-text">Happy Cellular<small>Admin Panel</small></div>
    </div>
    <div class="sidebar-menu">
        <a href="index.php" class="sidebar-link active"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="stock_in.php" class="sidebar-link"><i class="fas fa-arrow-circle-down" style="color: #10B981;"></i> Stock In</a>
        <a href="admin_stock_out.php" class="sidebar-link"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i> Stock Out</a>
        <a href="return.php" class="sidebar-link"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i> Retur</a>
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
            <img src="../asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
            <div class="logo-text">Happy Cellular<small>Admin Panel</small></div>
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
        <!-- KPI SUMMARY -->
        <div class="section-title"><i class="fas fa-chart-line"></i> Ringkasan Kinerja</div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon-box" style="background: rgba(59,130,246,0.1); color: #3B82F6;"><i class="fas fa-boxes-stacked"></i></div>
                <div class="stat-value"><?= number_format($total_unit) ?></div>
                <div class="stat-label">Total Stok Ready</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-box" style="background: rgba(16,185,129,0.1); color: #10B981;"><i class="fas fa-coins"></i></div>
                <div class="stat-value">Rp <?= number_format($potensi_profit, 0, ',', '.') ?></div>
                <div class="stat-label">Potensi Profit Stok</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-box" style="background: rgba(245,158,11,0.1); color: #F59E0B;"><i class="fas fa-shopping-cart"></i></div>
                <div class="stat-value"><?= $trx_hari_ini ?></div>
                <div class="stat-label">Transaksi Hari Ini</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-box" style="background: rgba(139,92,246,0.1); color: #8B5CF6;"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-value">Rp <?= number_format($omzet_hari_ini, 0, ',', '.') ?></div>
                <div class="stat-label">Omzet Hari Ini</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-box" style="background: rgba(16,185,129,0.1); color: #10B981;"><i class="fas fa-chart-line"></i></div>
                <div class="stat-value">Rp <?= number_format($laba_hari_ini, 0, ',', '.') ?></div>
                <div class="stat-label">Laba Hari Ini</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-box" style="background: rgba(59,130,246,0.1); color: #3B82F6;"><i class="fas fa-calendar"></i></div>
                <div class="stat-value"><?= $trx_bulan_ini ?></div>
                <div class="stat-label">Transaksi Bulan Ini</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-box" style="background: rgba(139,92,246,0.1); color: #8B5CF6;"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-value">Rp <?= number_format($omzet_bulan_ini, 0, ',', '.') ?></div>
                <div class="stat-label">Omzet Bulan Ini</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-box" style="background: rgba(16,185,129,0.1); color: #10B981;"><i class="fas fa-chart-line"></i></div>
                <div class="stat-value">Rp <?= number_format($laba_bulan_ini, 0, ',', '.') ?></div>
                <div class="stat-label">Laba Bulan Ini</div>
            </div>
        </div>

        <!-- GRAFIK PENJUALAN -->
        <div class="section-title"><i class="fas fa-chart-area"></i> Grafik Penjualan</div>
        <div class="chart-container">
            <div class="chart-title"><i class="fas fa-chart-line"></i> Penjualan 30 Hari Terakhir</div>
            <canvas id="chart30Hari" height="100"></canvas>
        </div>

        <div class="chart-container">
            <div class="chart-title"><i class="fas fa-chart-bar"></i> Penjualan 12 Bulan Terakhir</div>
            <canvas id="chart12Bulan" height="80"></canvas>
        </div>

        <!-- INVENTORY ANALYTICS -->
        <div class="section-title"><i class="fas fa-boxes"></i> Inventory Analytics</div>
        <div class="analytics-grid">
            <div class="analytics-card">
                <div class="analytics-title"><i class="fas fa-cube"></i> Stok Overview</div>
                <div class="analytics-item">
                    <span class="analytics-item-name">Total Produk Aktif</span>
                    <span class="analytics-item-value"><?= $total_produk_aktif ?></span>
                </div>
                <div class="analytics-item">
                    <span class="analytics-item-name">Total Unit Stok</span>
                    <span class="analytics-item-value"><?= $total_unit ?></span>
                </div>
                <div class="analytics-item">
                    <span class="analytics-item-name">Total IMEI Aktif</span>
                    <span class="analytics-item-value"><?= $total_imei_aktif ?></span>
                </div>
                <div class="analytics-item">
                    <span class="analytics-item-name">Total IMEI Terjual</span>
                    <span class="analytics-item-value"><?= $total_imei_terjual ?></span>
                </div>
            </div>

            <div class="analytics-card">
                <div class="analytics-title"><i class="fas fa-exclamation-triangle" style="color: var(--warning-orange);"></i> Stok Menipis</div>
                <?php if (count($stok_menipis) > 0): ?>
                    <?php foreach ($stok_menipis as $item): ?>
                    <div class="analytics-item">
                        <span class="analytics-item-name"><?= htmlspecialchars($item['nama']) ?></span>
                        <span class="analytics-item-value"><?= $item['stok'] ?> unit</span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="analytics-item"><span class="text-muted">Tidak ada</span></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SALES ANALYTICS -->
        <div class="section-title"><i class="fas fa-trophy"></i> Sales Analytics</div>
        <div class="analytics-grid">
            <div class="analytics-card">
                <div class="analytics-title"><i class="fas fa-fire" style="color: var(--danger-red);"></i> Top 10 Produk Terlaris</div>
                <?php if (count($top_terlaris) > 0): ?>
                    <?php foreach ($top_terlaris as $item): ?>
                    <div class="analytics-item">
                        <span class="analytics-item-name"><?= htmlspecialchars($item['nama']) ?></span>
                        <span class="analytics-item-value"><?= $item['qty_terjual'] ?> terjual</span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="analytics-item"><span class="text-muted">Belum ada data</span></div>
                <?php endif; ?>
            </div>

            <div class="analytics-card">
                <div class="analytics-title"><i class="fas fa-gem" style="color: var(--success-green);"></i> Top 10 Profit Tertinggi</div>
                <?php if (count($top_profit) > 0): ?>
                    <?php foreach ($top_profit as $item): ?>
                    <div class="analytics-item">
                        <span class="analytics-item-name"><?= htmlspecialchars($item['nama']) ?></span>
                        <span class="analytics-item-value">Rp <?= number_format($item['profit'], 0, ',', '.') ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="analytics-item"><span class="text-muted">Belum ada data</span></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PROFIT ANALYTICS -->
        <div class="section-title"><i class="fas fa-chart-pie"></i> Profit Analytics</div>
        <div class="analytics-grid">
            <div class="analytics-card">
                <div class="analytics-title"><i class="fas fa-chart-line"></i> Profit per Brand</div>
                <?php if (count($profit_brand) > 0): ?>
                    <?php foreach ($profit_brand as $item): ?>
                    <div class="analytics-item">
                        <span class="analytics-item-name"><?= htmlspecialchars($item['brand']) ?></span>
                        <span class="analytics-item-value">Rp <?= number_format($item['profit'], 0, ',', '.') ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="analytics-item"><span class="text-muted">Belum ada data</span></div>
                <?php endif; ?>
            </div>

            <div class="analytics-card">
                <div class="analytics-title"><i class="fas fa-percentage"></i> Margin Profit</div>
                <div class="analytics-item">
                    <span class="analytics-item-name">Total Omzet 30 Hari</span>
                    <span class="analytics-item-value">Rp <?= number_format($total_omzet_30, 0, ',', '.') ?></span>
                </div>
                <div class="analytics-item">
                    <span class="analytics-item-name">Total Laba 30 Hari</span>
                    <span class="analytics-item-value">Rp <?= number_format($total_laba_30, 0, ',', '.') ?></span>
                </div>
                <div class="analytics-item">
                    <span class="analytics-item-name">Margin Persentase</span>
                    <span class="analytics-item-value"><?= $margin_persen ?>%</span>
                </div>
            </div>
        </div>

        <!-- CASHFLOW -->
        <div class="section-title"><i class="fas fa-money-bill-trend-up"></i> Cashflow</div>
        <div class="analytics-grid">
            <div class="analytics-card">
                <div class="analytics-title"><i class="fas fa-arrow-down" style="color: var(--success-green);"></i> Kas Hari Ini</div>
                <div class="analytics-item">
                    <span class="analytics-item-name">Uang Masuk</span>
                    <span class="analytics-item-value" style="color: var(--success-green);">Rp <?= number_format($uang_masuk_hari, 0, ',', '.') ?></span>
                </div>
                <div class="analytics-item">
                    <span class="analytics-item-name">Uang Keluar</span>
                    <span class="analytics-item-value" style="color: var(--danger-red);">Rp <?= number_format($uang_keluar_hari, 0, ',', '.') ?></span>
                </div>
            </div>

            <div class="analytics-card">
                <div class="analytics-title"><i class="fas fa-calendar" style="color: var(--accent-blue);"></i> Kas Bulan Ini</div>
                <div class="analytics-item">
                    <span class="analytics-item-name">Uang Masuk</span>
                    <span class="analytics-item-value" style="color: var(--success-green);">Rp <?= number_format($uang_masuk_bulan, 0, ',', '.') ?></span>
                </div>
                <div class="analytics-item">
                    <span class="analytics-item-name">Uang Keluar</span>
                    <span class="analytics-item-value" style="color: var(--danger-red);">Rp <?= number_format($uang_keluar_bulan, 0, ',', '.') ?></span>
                </div>
            </div>
        </div>

        <div class="chart-container">
            <div class="chart-title"><i class="fas fa-chart-area"></i> Cashflow 12 Bulan Terakhir</div>
            <canvas id="chartCashflow" height="80"></canvas>
        </div>

        <!-- ALERT CENTER -->
        <div class="section-title"><i class="fas fa-bell"></i> Alert Center</div>
        <div class="alert-card">
            <?php if (count($imei_duplikat) > 0): ?>
                <?php foreach ($imei_duplikat as $item): ?>
                <div class="alert-item critical">
                    <div class="alert-icon"><i class="fas fa-copy"></i></div>
                    <div class="alert-content">
                        <div class="alert-title">IMEI Duplikat</div>
                        <div class="alert-desc">IMEI <?= htmlspecialchars($item['imei']) ?> terdaftar <?= $item['jumlah'] ?> kali</div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($modal_kosong > 0): ?>
            <div class="alert-item warning">
                <div class="alert-icon"><i class="fas fa-money-bill"></i></div>
                <div class="alert-content">
                    <div class="alert-title">Harga Modal Kosong</div>
                    <div class="alert-desc"><?= $modal_kosong ?> produk tidak memiliki harga modal</div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($harga_kosong > 0): ?>
            <div class="alert-item warning">
                <div class="alert-icon"><i class="fas fa-tag"></i></div>
                <div class="alert-content">
                    <div class="alert-title">Harga Jual Kosong</div>
                    <div class="alert-desc"><?= $harga_kosong ?> produk tidak memiliki harga jual</div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (count($stok_menipis) > 0): ?>
            <div class="alert-item warning">
                <div class="alert-icon"><i class="fas fa-box-open"></i></div>
                <div class="alert-content">
                    <div class="alert-title">Stok Menipis</div>
                    <div class="alert-desc"><?= count($stok_menipis) ?> produk stoknya < 3 unit</div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (count($imei_duplikat) == 0 && $modal_kosong == 0 && $harga_kosong == 0 && count($stok_menipis) == 0): ?>
            <div class="alert-item normal">
                <div class="alert-icon"><i class="fas fa-check-circle"></i></div>
                <div class="alert-content">
                    <div class="alert-title">Semua Normal</div>
                    <div class="alert-desc">Tidak ada peringatan saat ini</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ACTIVITY LOG -->
        <div class="section-title"><i class="fas fa-history"></i> Activity Log</div>
        <div class="alert-card">
            <?php if (count($activity_log) > 0): ?>
                <?php foreach ($activity_log as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-<?= $activity['aktivitas'] == 'Penjualan' ? 'shopping-cart' : 'arrow-right' ?>"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title"><?= htmlspecialchars($activity['aktivitas']) ?></div>
                        <div class="activity-time"><?= date('d/m/Y H:i', strtotime($activity['tanggal'])) ?> · <?= htmlspecialchars($activity['keterangan']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-3"><span class="text-muted">Belum ada aktivitas</span></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<nav class="bottom-nav">
    <a href="index.php" class="nav-item active"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a href="stock_in.php" class="nav-item"><i class="fas fa-arrow-circle-down" style="color: #10B981;"></i><span>Stock In</span></a>
    <a href="admin_stock_out.php" class="nav-item"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i><span>Stock Out</span></a>
    <a href="return.php" class="nav-item"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i><span>Retur</span></a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ==========================================
// FUNGSI HELPER: FORMAT RUPIAH ADAPTIF
// ==========================================
/**
 * Format angka untuk sumbu Y (axis) pada grafik
 * Otomatis menyesuaikan satuan: rb, jt, M, T
 */
function formatRupiahAxis(value) {
    const absValue = Math.abs(value);
    if (absValue >= 1_000_000_000_000) {
        return 'Rp ' + (value / 1_000_000_000_000).toFixed(1) + 'T';
    } else if (absValue >= 1_000_000_000) {
        return 'Rp ' + (value / 1_000_000_000).toFixed(1) + 'M';
    } else if (absValue >= 1_000_000) {
        return 'Rp ' + (value / 1_000_000).toFixed(1) + 'jt';
    } else if (absValue >= 1_000) {
        return 'Rp ' + (value / 1_000).toFixed(0) + 'rb';
    } else {
        return 'Rp ' + value;
    }
}

/**
 * Format angka untuk tooltip (lebih detail, dengan pemisah ribuan)
 */
function formatRupiahFull(value) {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
}

// ==========================================
// CHART: PENJUALAN 30 HARI TERAKHIR
// ==========================================
const ctx30 = document.getElementById('chart30Hari').getContext('2d');
const chart30Data = <?= json_encode($grafik_30) ?>;

new Chart(ctx30, {
    type: 'line',
    data: {
        labels: chart30Data.map(d => new Date(d.tanggal).toLocaleDateString('id-ID', {day:'2-digit', month:'short'})),
        datasets: [
            {
                label: 'Omzet',
                data: chart30Data.map(d => d.omzet),
                borderColor: '#3B82F6',
                backgroundColor: function(context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return 'rgba(59,130,246,0.1)';
                    const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                    gradient.addColorStop(0, 'rgba(59, 130, 246, 0.05)');
                    gradient.addColorStop(1, 'rgba(59, 130, 246, 0.4)');
                    return gradient;
                },
                borderWidth: 2.5,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#3B82F6',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: { 
                position: 'top',
                labels: { font: { weight: '600', size: 12 }, usePointStyle: true, pointStyle: 'circle' }
            },
            tooltip: {
                backgroundColor: 'rgba(30, 41, 59, 0.95)',
                titleFont: { size: 13, weight: '700' },
                bodyFont: { size: 12, weight: '600' },
                padding: 12,
                cornerRadius: 10,
                displayColors: false,
                callbacks: {
                    title: function(items) {
                        return items[0].label;
                    },
                    label: function(context) {
                        const idx = context.dataIndex;
                        const trx = chart30Data[idx] ? chart30Data[idx].jumlah_trx : 0;
                        return [
                            'Omzet: ' + formatRupiahFull(context.parsed.y),
                            'Transaksi: ' + trx + 'x'
                        ];
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                ticks: {
                    font: { size: 11, weight: '600' },
                    color: '#64748B',
                    callback: function(value) {
                        return formatRupiahAxis(value);
                    },
                    maxTicksLimit: 6
                }
            },
            x: {
                grid: { display: false },
                ticks: {
                    font: { size: 10, weight: '600' },
                    color: '#64748B',
                    maxRotation: 45,
                    minRotation: 0,
                    autoSkip: true,
                    maxTicksLimit: 10
                }
            }
        }
    }
});

// ==========================================
// CHART: PENJUALAN 12 BULAN TERAKHIR
// ==========================================
const ctx12 = document.getElementById('chart12Bulan').getContext('2d');
const chart12Data = <?= json_encode($grafik_12) ?>;

new Chart(ctx12, {
    type: 'bar',
    data: {
        labels: chart12Data.map(d => d.bulan_nama),
        datasets: [
            {
                label: 'Omzet',
                data: chart12Data.map(d => d.omzet),
                backgroundColor: function(context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return '#8B5CF6';
                    const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                    gradient.addColorStop(0, 'rgba(139, 92, 246, 0.4)');
                    gradient.addColorStop(1, 'rgba(139, 92, 246, 1)');
                    return gradient;
                },
                borderRadius: 8,
                borderSkipped: false,
                barPercentage: 0.6,
                categoryPercentage: 0.7
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(30, 41, 59, 0.95)',
                titleFont: { size: 13, weight: '700' },
                bodyFont: { size: 12, weight: '600' },
                padding: 12,
                cornerRadius: 10,
                displayColors: false,
                callbacks: {
                    title: function(items) {
                        return items[0].label;
                    },
                    label: function(context) {
                        const idx = context.dataIndex;
                        const trx = chart12Data[idx] ? chart12Data[idx].jumlah_trx : 0;
                        return [
                            'Omzet: ' + formatRupiahFull(context.parsed.y),
                            'Transaksi: ' + trx + 'x'
                        ];
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                ticks: {
                    font: { size: 11, weight: '600' },
                    color: '#64748B',
                    callback: function(value) {
                        return formatRupiahAxis(value);
                    },
                    maxTicksLimit: 6
                }
            },
            x: {
                grid: { display: false },
                ticks: {
                    font: { size: 10, weight: '600' },
                    color: '#64748B',
                    maxRotation: 45,
                    minRotation: 0
                }
            }
        }
    }
});

// ==========================================
// CHART: CASHFLOW 12 BULAN TERAKHIR
// ==========================================
const ctxCash = document.getElementById('chartCashflow').getContext('2d');
const cashflowData = <?= json_encode($cashflow_bulanan) ?>;

new Chart(ctxCash, {
    type: 'line',
    data: {
        labels: cashflowData.map(d => d.bulan),
        datasets: [
            {
                label: 'Pemasukan',
                data: cashflowData.map(d => d.pemasukan),
                borderColor: '#10B981',
                backgroundColor: function(context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return 'rgba(16,185,129,0.1)';
                    const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.05)');
                    gradient.addColorStop(1, 'rgba(16, 185, 129, 0.4)');
                    return gradient;
                },
                borderWidth: 2.5,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#10B981',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: { 
                position: 'top',
                labels: { font: { weight: '600', size: 12 }, usePointStyle: true, pointStyle: 'circle' }
            },
            tooltip: {
                backgroundColor: 'rgba(30, 41, 59, 0.95)',
                titleFont: { size: 13, weight: '700' },
                bodyFont: { size: 12, weight: '600' },
                padding: 12,
                cornerRadius: 10,
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        return 'Pemasukan: ' + formatRupiahFull(context.parsed.y);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                ticks: {
                    font: { size: 11, weight: '600' },
                    color: '#64748B',
                    callback: function(value) {
                        return formatRupiahAxis(value);
                    },
                    maxTicksLimit: 6
                }
            },
            x: {
                grid: { display: false },
                ticks: {
                    font: { size: 10, weight: '600' },
                    color: '#64748B',
                    maxRotation: 45,
                    minRotation: 0
                }
            }
        }
    }
});

// ==========================================
// POPUP MENU MOBILE
// ==========================================
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