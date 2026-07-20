<?php
session_start();
if(!isset($_SESSION['login']) || $_SESSION['level'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

include '../connection.php';

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success']);
unset($_SESSION['flash_error']);

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$jenis_transaksi  = $_GET['jenis'] ?? '';
$metode_pembayaran = $_GET['metode'] ?? '';

function cleanMetode($metode) {
    if (empty($metode)) return 'Lainnya';
    return trim(str_replace('(TT)', '', $metode));
}

function isCicilan($metode) {
    if (empty($metode)) return false;
    $clean = cleanMetode($metode);
    return (stripos($clean, 'Akulaku') !== false || 
            stripos($clean, 'Kredivo') !== false || 
            stripos($clean, 'Shopee') !== false);
}

function isTT($metode) {
    if (empty($metode)) return false;
    return stripos($metode, '(TT)') !== false;
}

function isCashTransfer($metode) {
    if (empty($metode)) return true;
    $clean = cleanMetode($metode);
    return in_array($clean, ['Cash', 'Transfer']);
}

$q_metode = mysqli_query($koneksi, "SELECT DISTINCT REPLACE(metode_pembayaran, ' (TT)', '') as metode_bersih FROM penjualan WHERE metode_pembayaran IS NOT NULL AND metode_pembayaran != '' ORDER BY metode_bersih");
$metodeList = [];
while ($m = mysqli_fetch_assoc($q_metode)) {
    if (!empty(trim($m['metode_bersih']))) {
        $metodeList[] = trim($m['metode_bersih']);
    }
}

$where_tambahan = "";
if (!empty($jenis_transaksi)) {
    $where_tambahan .= " AND p.jenis_transaksi = '".mysqli_real_escape_string($koneksi, $jenis_transaksi)."'";
}
if (!empty($metode_pembayaran)) {
    $where_tambahan .= " AND REPLACE(p.metode_pembayaran, ' (TT)', '') = '".mysqli_real_escape_string($koneksi, $metode_pembayaran)."'";
}

$q_stok_ready = "SELECT COUNT(*) as jumlah, SUM(modal) as nilai_modal FROM barang WHERE status = 'READY'";
$res_stok = mysqli_query($koneksi, $q_stok_ready);
$data_stok = mysqli_fetch_assoc($res_stok);
$stok_jumlah = $data_stok['jumlah'] ?? 0;
$stok_nilai  = $data_stok['nilai_modal'] ?? 0;

$q_stats_periode = "SELECT 
    COUNT(p.id) as total_trx,
    SUM(p.modal) as total_modal_terjual,
    SUM(p.harga_jual) as total_omzet,
    
    SUM(
        CASE 
            WHEN p.metode_pembayaran LIKE '%(TT)%' 
                 AND (REPLACE(p.metode_pembayaran, ' (TT)', '') LIKE '%Akulaku%' OR REPLACE(p.metode_pembayaran, ' (TT)', '') LIKE '%Kredivo%' OR REPLACE(p.metode_pembayaran, ' (TT)', '') LIKE '%Shopee%')
                 AND p.status_pencairan = 'Pending' 
            THEN COALESCE(p.dp, 0)
            WHEN p.metode_pembayaran LIKE '%(TT)%' AND COALESCE(tt.selisih, 0) < 0
            THEN COALESCE(tt.selisih, 0)
            WHEN p.metode_pembayaran LIKE '%(TT)%' 
            THEN COALESCE(p.dp, 0) + COALESCE(tt.selisih, 0)
            WHEN REPLACE(p.metode_pembayaran, ' (TT)', '') IN ('Cash', 'Transfer') OR p.metode_pembayaran IS NULL
            THEN COALESCE(p.harga_jual, 0)
            WHEN p.status_pencairan = 'Cair'
            THEN COALESCE(p.harga_jual, 0)
            ELSE COALESCE(p.dp, 0)
        END
    ) as total_kas_riil,
    SUM(
        CASE 
            WHEN p.metode_pembayaran LIKE '%(TT)%' THEN COALESCE(tt.harga_lama, 0)
            ELSE 0
        END
    ) as total_aset_tt,
    SUM(
        CASE 
            WHEN p.metode_pembayaran LIKE '%(TT)%' 
                 AND (REPLACE(p.metode_pembayaran, ' (TT)', '') LIKE '%Akulaku%' OR REPLACE(p.metode_pembayaran, ' (TT)', '') LIKE '%Kredivo%' OR REPLACE(p.metode_pembayaran, ' (TT)', '') LIKE '%Shopee%')
                 AND p.status_pencairan = 'Pending'
            THEN COALESCE(tt.selisih, 0)
            WHEN (REPLACE(p.metode_pembayaran, ' (TT)', '') LIKE '%Akulaku%' OR REPLACE(p.metode_pembayaran, ' (TT)', '') LIKE '%Kredivo%' OR REPLACE(p.metode_pembayaran, ' (TT)', '') LIKE '%Shopee%')
                 AND p.status_pencairan = 'Pending'
            THEN (COALESCE(p.harga_jual, 0) - COALESCE(p.dp, 0))
            ELSE 0
        END
    ) as total_piutang_pending,
    SUM(
        CASE 
            WHEN p.metode_pembayaran LIKE '%(TT)%' THEN COALESCE(tt.laba, 0)
            WHEN p.jenis_transaksi = 'SERVICE' THEN COALESCE(p.harga_jual, 0)
            ELSE (COALESCE(p.harga_jual, 0) - COALESCE(p.modal, 0))
        END
    ) as total_laba
    FROM penjualan p
    LEFT JOIN tukar_tambah tt ON p.imei = tt.imei_baru
    WHERE DATE(p.tanggal) BETWEEN '$start_date' AND '$end_date'
    $where_tambahan";

$res_stats = mysqli_query($koneksi, $q_stats_periode);
$stats = mysqli_fetch_assoc($res_stats);

$total_transaksi  = $stats['total_trx'] ?? 0;
$modal_terjual    = $stats['total_modal_terjual'] ?? 0;
$total_omzet      = $stats['total_omzet'] ?? 0;
$kas_riil_toko    = $stats['total_kas_riil'] ?? 0;       
$aset_tt_masuk    = $stats['total_aset_tt'] ?? 0;
$piutang_pending  = $stats['total_piutang_pending'] ?? 0; 
$total_laba       = $stats['total_laba'] ?? 0;

$q_list = "SELECT p.*, b.nama, b.tipe, b.warna, b.internal,
                  tt.harga_lama as tt_harga_lama, tt.selisih as tt_selisih, tt.laba as tt_laba,
                  s.merk as sv_merk, s.model as sv_model, s.kerusakan as sv_kerusakan
           FROM penjualan p
           LEFT JOIN barang b ON p.imei = b.imei
           LEFT JOIN tukar_tambah tt ON p.imei = tt.imei_baru
           LEFT JOIN service_hp s ON (p.jenis_transaksi = 'SERVICE' AND p.imei = CONCAT('SVC-', s.id))
           WHERE DATE(p.tanggal) BETWEEN '$start_date' AND '$end_date'
           $where_tambahan
           ORDER BY p.tanggal DESC";
$res_list = mysqli_query($koneksi, $q_list);

$grouped_data = [];
if ($res_list && mysqli_num_rows($res_list) > 0) {
    while($p = mysqli_fetch_assoc($res_list)) {
        $tanggal = date('Y-m-d', strtotime($p['tanggal']));
        $grouped_data[$tanggal][] = $p;
    }
}

$q_metode_summary = "SELECT 
    REPLACE(COALESCE(p.metode_pembayaran, 'Lainnya'), ' (TT)', '') as metode,
    COUNT(*) as total_trx,
    SUM(p.harga_jual) as total_omzet,
    SUM(
        CASE 
            WHEN p.metode_pembayaran LIKE '%(TT)%' 
                 AND (REPLACE(p.metode_pembayaran, ' (TT)', '') LIKE '%Akulaku%' OR REPLACE(p.metode_pembayaran, ' (TT)', '') LIKE '%Kredivo%' OR REPLACE(p.metode_pembayaran, ' (TT)', '') LIKE '%Shopee%')
                 AND p.status_pencairan = 'Pending' 
            THEN COALESCE(p.dp, 0)
            WHEN p.metode_pembayaran LIKE '%(TT)%' AND COALESCE(tt.selisih, 0) < 0
            THEN COALESCE(tt.selisih, 0)
            WHEN p.metode_pembayaran LIKE '%(TT)%' 
            THEN COALESCE(p.dp, 0) + COALESCE(tt.selisih, 0)
            WHEN REPLACE(p.metode_pembayaran, ' (TT)', '') IN ('Cash', 'Transfer') OR p.metode_pembayaran IS NULL
            THEN COALESCE(p.harga_jual, 0)
            WHEN p.status_pencairan = 'Cair'
            THEN COALESCE(p.harga_jual, 0)
            ELSE COALESCE(p.dp, 0)
        END
    ) as total_kas
FROM penjualan p
LEFT JOIN tukar_tambah tt ON p.imei = tt.imei_baru
WHERE DATE(p.tanggal) BETWEEN '$start_date' AND '$end_date'
$where_tambahan
GROUP BY REPLACE(COALESCE(p.metode_pembayaran, 'Lainnya'), ' (TT)', '')
ORDER BY total_omzet DESC";
$res_metode = mysqli_query($koneksi, $q_metode_summary);
$metode_summary = [];
if ($res_metode && mysqli_num_rows($res_metode) > 0) {
    while($m = mysqli_fetch_assoc($res_metode)) {
        $metode_summary[] = $m;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Analisis Keuangan · Happy Cellular</title>
    <link rel="shortcut icon" href="../asset/Happy.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F4F7FA;
            --surface-card: #FFFFFF;
            --primary-dongker: #1E4A7A;
            --primary-dongker-soft: #EEF4FA;
            --text-main: #1E293B;
            --text-muted: #64748B;
            --success-green: #10B981;
            --warning-orange: #F59E0B;
            --purple-asset: #8B5CF6;
            --danger-red: #EF4444;
            --bottom-nav-height: 72px;
            --sidebar-width: 260px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg-body); 
            min-height: 100vh; 
            color: var(--text-main); 
            padding-bottom: calc(var(--bottom-nav-height) + 2rem);
            -webkit-font-smoothing: antialiased;
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
            display: flex; align-items: center; gap: 6px; 
            border: none; cursor: pointer;
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
            position: absolute;
        }
        .dropdown-menu-custom .dropdown-item {
            font-size: 0.85rem; font-weight: 600; color: var(--text-main);
            padding: 10px 14px; border-radius: 10px;
            display: flex; align-items: center; gap: 10px;
        }
        .dropdown-menu-custom .dropdown-item i { color: var(--primary-dongker); font-size: 0.95rem; width: 16px; text-align: center; }
        .dropdown-menu-custom .dropdown-item:hover { background: var(--primary-dongker-soft); color: var(--primary-dongker); }
        .dropdown-menu-custom .dropdown-item.text-danger { color: #EF4444; }
        .dropdown-menu-custom .dropdown-item.text-danger i { color: #EF4444; }
        
        .main-wrapper { max-width: 540px; margin: 0 auto; padding: 1rem 1.2rem; }
        .section-heading { display: flex; justify-content: space-between; align-items: center; margin: 1.5rem 0 1rem; }
        .section-heading h3 { font-weight: 800; font-size: 1.1rem; color: var(--text-main); margin-bottom: 0; }
        .section-heading span { font-size: 0.75rem; font-weight: 600; color: var(--primary-dongker); background: var(--primary-dongker-soft); padding: 4px 12px; border-radius: 20px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 1.5rem; }
        .stat-card { 
            background: var(--surface-card); border-radius: 16px; padding: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.01);
            display: flex; flex-direction: column; justify-content: space-between;
            position: relative; overflow: hidden;
        }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
        .stat-card.trx::before { background: var(--primary-dongker); }
        .stat-card.kas::before { background: var(--success-green); }
        .stat-card.aset::before { background: var(--purple-asset); }
        .stat-card.piutang::before { background: var(--warning-orange); }
        .stat-card.laba::before { background: #10B981; }
        .stat-icon-box { 
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; margin-bottom: 10px;
        }
        .stat-value { font-size: 1.15rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; margin-bottom: 2px; }
        .stat-label { font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card-full { grid-column: span 2; }
        
        .filter-card { 
            background: var(--surface-card); border-radius: 16px; padding: 1.2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.01);
            margin-bottom: 1.5rem; 
        }
        
        .metode-summary-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-bottom: 1.5rem; }
        .metode-card { 
            background: var(--surface-card); border-radius: 14px; padding: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.03);
            border-left: 4px solid #3B82F6;
        }
        .metode-card.cash { border-left-color: var(--success-green); }
        .metode-card.cicilan { border-left-color: var(--warning-orange); }
        .metode-card.tt { border-left-color: var(--purple-asset); }
        .metode-name { font-size: 0.75rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; text-transform: uppercase; }
        .metode-stats { font-size: 0.65rem; color: var(--text-muted); }
        .metode-stats strong { color: var(--text-main); font-size: 0.85rem; display: block; margin-bottom: 2px; }
        
        .date-group-header { 
            display: flex; justify-content: space-between; align-items: center;
            margin: 20px 0 10px 0; padding: 10px 14px;
            background: var(--primary-dongker-soft); border-radius: 12px;
            border-left: 4px solid var(--primary-dongker);
        }
        .date-group-header h6 { font-weight: 800; font-size: 0.85rem; color: var(--primary-dongker); margin: 0; }
        .date-group-header .badge { background: var(--primary-dongker); color: white; font-weight: 700; font-size: 0.7rem; padding: 5px 10px; border-radius: 20px; }
        
        .biz-card { 
            background: var(--surface-card); border-radius: 16px; padding: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.03);
            margin-bottom: 10px;
            position: relative;
        }
        .biz-card.downgrade-card { border-left: 3px solid var(--danger-red); }
        .biz-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; cursor: pointer; }
        .biz-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
        .biz-icon-box { 
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .biz-info { min-width: 0; flex: 1; }
        .biz-title { font-weight: 700; font-size: 0.85rem; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px; }
        .biz-meta { font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .biz-right { text-align: right; flex-shrink: 0; }
        .biz-price { font-weight: 800; font-size: 0.9rem; color: var(--text-main); margin-bottom: 2px; }
        .biz-laba { font-size: 0.7rem; font-weight: 700; }
        .biz-laba.profit { color: var(--success-green); }
        .biz-laba.loss { color: var(--danger-red); }
        .biz-breakdown { 
            margin-top: 12px; padding: 12px; background: #F8FAFC;
            border-radius: 10px; border: 1px dashed #E2E8F0; font-size: 0.7rem;
            position: relative; z-index: 5;
        }
        .biz-breakdown .row-item { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; padding: 4px 0; }
        .biz-breakdown .row-item:last-child { margin-bottom: 0; }

        .btn-konfirmasi-cair { 
            font-size: 0.7rem; 
            padding: 6px 12px; 
            border-radius: 8px;
            background: var(--warning-orange); 
            color: white; 
            border: none;
            font-weight: 700; 
            cursor: pointer; 
            margin-top: 10px;
            display: inline-flex; 
            align-items: center; 
            gap: 6px;
            text-decoration: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.2s;
            position: relative;
            z-index: 100;
            pointer-events: auto !important;
        }
        .btn-konfirmasi-cair:hover { 
            background: #D97706; 
            color: white; 
            transform: translateY(-1px); 
        }
        .btn-konfirmasi-cair:active { 
            transform: translateY(0); 
        }
        
        .badge-cicilan-pending { background: rgba(245,158,11,0.1); color: var(--warning-orange); font-size: 0.6rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 3px; }
        .badge-cicilan-cair { background: rgba(16,185,129,0.1); color: var(--success-green); font-size: 0.6rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 3px; }
        .badge-tt { background: rgba(139,92,246,0.1); color: var(--purple-asset); font-size: 0.6rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 3px; }
        .badge-tt.downgrade { background: rgba(239,68,68,0.1); color: var(--danger-red); }
        .badge-downgrade { background: rgba(239,68,68,0.1); color: var(--danger-red); font-size: 0.6rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 3px; }
        
        .modal-content { border-radius: 20px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
        .modal-header { border-bottom: 1px solid #F1F5F9; padding: 1.2rem 1.5rem; background: linear-gradient(135deg, var(--primary-dongker-soft) 0%, #fff 100%); }
        .modal-body { padding: 1.5rem; }
        .detail-section { margin-bottom: 20px; }
        .detail-section-title { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .detail-card { background: #F8FAFC; border-radius: 12px; padding: 14px; border: 1px solid #E2E8F0; }
        .detail-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; font-size: 0.8rem; border-bottom: 1px dashed #E2E8F0; }
        .detail-row:last-child { border-bottom: none; }
        
        .flash-alert {
            background: rgba(16,185,129,0.1); border: 1px solid var(--success-green);
            border-radius: 12px; padding: 12px 16px; margin-bottom: 16px;
            display: flex; align-items: center; gap: 10px;
            font-size: 0.85rem; font-weight: 600; color: var(--success-green);
        }
        
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0; height: var(--bottom-nav-height);
            background: var(--surface-card); display: flex; justify-content: space-around; align-items: center;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.08); border-top: 1px solid #EDF2F7; z-index: 1050;
            padding-bottom: env(safe-area-inset-bottom, 0);
        }
        .nav-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; text-decoration: none; color: #A0AEC0; font-size: 0.7rem; font-weight: 600; flex: 1; height: 100%; }
        .nav-item i { font-size: 1.25rem; }
        .nav-item.active { color: var(--primary-dongker); }
        
        .more-menu-popup {
            position: fixed; bottom: calc(var(--bottom-nav-height) + 10px); left: 50%;
            transform: translateX(-50%) scale(0.9); width: calc(100% - 2rem); max-width: 400px;
            background: var(--surface-card); border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15); padding: 12px;
            display: none; grid-template-columns: repeat(2, 1fr); gap: 8px; z-index: 1100;
        }
        .more-menu-popup.show { display: grid; transform: translateX(-50%) scale(1); }
        .more-menu-popup a { padding: 12px; text-decoration: none; color: var(--text-main); font-weight: 600; font-size: 0.8rem; border-radius: 12px; display: flex; align-items: center; gap: 10px; background: #F8FAFC; }
        .more-menu-popup a i { color: var(--primary-dongker); font-size: 1rem; width: 20px; text-align: center; }
        
        .popup-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.3); backdrop-filter: blur(4px); z-index: 1090; display: none; }
        .popup-overlay.show { display: block; }
        
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
            .main-wrapper { max-width: 1200px; width: 100%; padding: 1.5rem 2rem; margin: 0; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .stat-card-full { grid-column: span 3; }
            .metode-summary-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
        }
    </style>
</head>
<body>

<div class="desktop-sidebar">
    <div class="sidebar-brand">
        <img src="asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
        <div class="logo-text">Happy Cell<small>Admin Panel</small></div>
    </div>
    <div class="sidebar-menu">
        <a href="index.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="stock_in.php" class="sidebar-link"><i class="fas fa-arrow-circle-down" style="color: #10B981;"></i> Stock In</a>
        <a href="admin_stock_out.php" class="sidebar-link"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i> Stock Out</a>
        <a href="return.php" class="sidebar-link"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i> Retur</a>
        <div class="sidebar-divider"></div>
        <a href="data_master.php" class="sidebar-link"><i class="fas fa-database"></i> Data Master</a>
        <a href="data_barang.php" class="sidebar-link"><i class="fas fa-box"></i> Data Produk</a>
        <a href="penjualan.php" class="sidebar-link"><i class="fas fa-shopping-cart"></i> Transaksi Jual</a>
        <a href="keuangan.php" class="sidebar-link active"><i class="fas fa-chart-pie"></i> Analisis Laba</a>
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
            <div class="logo-text">Happy Cell<small>Admin Panel</small></div>
        </div>
        <div class="dropdown">
            <button class="user-badge" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom mt-2">
                <li><a class="dropdown-item" href="kelola_user.php"><i class="fas fa-users-cog me-2"></i> Kelola User</a></li>
                <li><a class="dropdown-item" href="ganti_password.php"><i class="fas fa-key me-2"></i> Ganti Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Keluar</a></li>
            </ul>
        </div>
    </div>

    <div class="main-wrapper">
        <?php if (!empty($flash_success)): ?>
    <div class="flash-alert">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($flash_success) ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($flash_error)): ?>
    <div class="flash-alert" style="background: rgba(239,68,68,0.1); border: 1px solid var(--danger-red); color: var(--danger-red);">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($flash_error) ?>
    </div>
    <?php endif; ?>

        <div class="section-heading">
            <h3>Laporan Cashflow & Laba</h3>
            <span><?= date('d M', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?></span>
        </div>

        <div class="filter-card">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-6">
                    <label class="form-label small fw-bold text-muted mb-1">Mulai</label>
                    <input type="date" name="start_date" class="form-control form-control-sm rounded-3" value="<?= $start_date ?>">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-bold text-muted mb-1">Akhir</label>
                    <input type="date" name="end_date" class="form-control form-control-sm rounded-3" value="<?= $end_date ?>">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-bold text-muted mb-1">Jenis</label>
                    <select name="jenis" class="form-select form-select-sm rounded-3">
                        <option value="">Semua</option>
                        <option value="HP" <?= $jenis_transaksi == 'HP' ? 'selected' : '' ?>>HP</option>
                        <option value="SERVICE" <?= $jenis_transaksi == 'SERVICE' ? 'selected' : '' ?>>Service</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-bold text-muted mb-1">Metode</label>
                    <select name="metode" class="form-select form-select-sm rounded-3">
                        <option value="">Semua</option>
                        <?php foreach ($metodeList as $mt): ?>
                            <option value="<?= htmlspecialchars($mt) ?>" <?= $metode_pembayaran == $mt ? 'selected' : '' ?>><?= htmlspecialchars($mt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-grid mt-2">
                    <button type="submit" class="btn btn-sm btn-primary rounded-3 fw-bold" style="background: var(--primary-dongker); border:none;">
                        <i class="fas fa-filter me-1"></i> Terapkan Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card trx">
                <div class="stat-icon-box" style="background: var(--primary-dongker-soft); color: var(--primary-dongker);"><i class="fas fa-shopping-bag"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($total_transaksi, 0, ',', '.') ?></div>
                    <div class="stat-label">Total Transaksi</div>
                </div>
            </div>
            <div class="stat-card kas">
                <div class="stat-icon-box" style="background: rgba(16,185,129,0.1); color: var(--success-green);"><i class="fas fa-wallet"></i></div>
                <div>
                    <div class="stat-value" style="color: <?= $kas_riil_toko < 0 ? 'var(--danger-red)' : 'var(--success-green)' ?>;">Rp <?= number_format($kas_riil_toko, 0, ',', '.') ?></div>
                    <div class="stat-label">Kas Riil (Uang Masuk)</div>
                </div>
            </div>
            <div class="stat-card aset">
                <div class="stat-icon-box" style="background: rgba(139,92,246,0.1); color: var(--purple-asset);"><i class="fas fa-exchange-alt"></i></div>
                <div>
                    <div class="stat-value" style="color: var(--purple-asset);">Rp <?= number_format($aset_tt_masuk, 0, ',', '.') ?></div>
                    <div class="stat-label">Aset Masuk (Nilai Tukar)</div>
                </div>
            </div>
            <div class="stat-card piutang">
                <div class="stat-icon-box" style="background: rgba(245,158,11,0.1); color: var(--warning-orange);"><i class="fas fa-hourglass-half"></i></div>
                <div>
                    <div class="stat-value" style="color: var(--warning-orange);">Rp <?= number_format($piutang_pending, 0, ',', '.') ?></div>
                    <div class="stat-label">Piutang PayLater</div>
                </div>
            </div>
            <div class="stat-card laba">
                <div class="stat-icon-box" style="background: rgba(16,185,129,0.1); color: #10B981;"><i class="fas fa-coins"></i></div>
                <div>
                    <div class="stat-value" style="color: <?= $total_laba < 0 ? 'var(--danger-red)' : '#10B981' ?>;">Rp <?= number_format($total_laba, 0, ',', '.') ?></div>
                    <div class="stat-label">Total Laba Bersih</div>
                </div>
            </div>
            <div class="stat-card stat-card-full" style="border-left: 4px solid var(--primary-dongker);">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-box" style="color: var(--primary-dongker); background: var(--primary-dongker-soft);"><i class="fas fa-boxes-stacked"></i></div>
                    <div>
                        <div class="stat-label">Aset Stok Berjalan (READY)</div>
                        <div class="stat-value" style="font-size: 1.1rem;"><?= $stok_jumlah ?> Unit <span class="text-muted" style="font-size: 0.8rem;">·</span> Rp <?= number_format($stok_nilai, 0, ',', '.') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($metode_summary)): ?>
        <div class="section-heading">
            <h3>Ringkasan per Metode</h3>
            <span><?= count($metode_summary) ?> Metode</span>
        </div>
        <div class="metode-summary-grid">
            <?php foreach ($metode_summary as $ms): 
                $metode_class = 'cash';
                if (isCicilan($ms['metode'])) $metode_class = 'cicilan';
            ?>
            <div class="metode-card <?= $metode_class ?>">
                <div class="metode-name"><?= htmlspecialchars($ms['metode']) ?></div>
                <div class="metode-stats">
                    <strong style="color: <?= $ms['total_kas'] < 0 ? 'var(--danger-red)' : 'var(--text-main)' ?>">Rp <?= number_format($ms['total_kas'], 0, ',', '.') ?></strong>
                    Kas Masuk · <?= $ms['total_trx'] ?> Trx
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="section-heading">
            <h3>Mutasi Transaksi</h3>
            <i class="fas fa-receipt text-muted"></i>
        </div>

        <div class="card-list-container">
            <?php if (!empty($grouped_data)): ?>
                <?php foreach ($grouped_data as $tanggal => $transactions): 
                    $tanggal_display = date('d M Y', strtotime($tanggal));
                    $total_harian = array_sum(array_column($transactions, 'harga_jual'));
                ?>
                    <div class="date-group-header">
                        <h6><i class="far fa-calendar-alt me-2"></i><?= $tanggal_display ?></h6>
                        <span class="badge">Omzet: Rp <?= number_format($total_harian, 0, ',', '.') ?></span>
                    </div>

                    <?php foreach($transactions as $p): 
                        $metode = $p['metode_pembayaran'] ?? '';
                        $is_tt = isTT($metode);
                        $is_cicilan = isCicilan($metode);
                        $is_cash = isCashTransfer($metode);
                        $is_service = ($p['jenis_transaksi'] ?? '') == 'SERVICE';
                        $is_pending = ($p['status_pencairan'] ?? '') == 'Pending';
                        
                        $harga_jual = $p['harga_jual'] ?? 0;
                        $modal = $p['modal'] ?? 0;
                        $dp = $p['dp'] ?? 0;
                        $tt_harga_lama = $p['tt_harga_lama'] ?? 0;
                        $tt_selisih = $p['tt_selisih'] ?? 0;
                        $tt_laba = $p['tt_laba'] ?? 0;
                        
                        $is_downgrade = $is_tt && $tt_selisih < 0;
                        
                        if ($is_tt) {
                            $aset_masuk = $tt_harga_lama;
                            if ($is_cicilan && $is_pending) {
                                $kas_masuk = $dp;
                                $piutang = $tt_selisih;
                            } elseif ($is_downgrade) {
                                $kas_masuk = $tt_selisih;
                                $piutang = 0;
                            } else {
                                $kas_masuk = $dp + $tt_selisih;
                                $piutang = 0;
                            }
                            $laba = $tt_laba;
                        } else {
                            $aset_masuk = 0;
                            if ($is_cash) {
                                $kas_masuk = $harga_jual;
                                $piutang = 0;
                            } elseif ($is_pending) {
                                $kas_masuk = $dp;
                                $piutang = $harga_jual - $dp;
                            } else {
                                $kas_masuk = $harga_jual;
                                $piutang = 0;
                            }
                            $laba = $harga_jual - $modal;
                        }

                        if ($is_service) {
                            $nama_tampilan = 'Service HP';
                            $subtitle = '';
                            if (!empty($p['sv_merk'])) $subtitle .= $p['sv_merk'] . ' ';
                            if (!empty($p['sv_model'])) $subtitle .= $p['sv_model'];
                            if (!empty($p['sv_kerusakan'])) $subtitle .= ' - ' . $p['sv_kerusakan'];
                            $icon_class = 'fa-tools';
                            $icon_bg = 'rgba(59,130,246,0.1)';
                            $icon_color = '#3B82F6';
                        } else {
                            $nama_tampilan = $p['nama'] ?? 'Produk Dihapus';
                            $subtitle = $p['tipe'] ?? '';
                            $icon_class = 'fa-mobile-alt';
                            $icon_bg = $is_downgrade ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)';
                            $icon_color = $is_downgrade ? '#EF4444' : '#10B981';
                        }

                        $detail_data = [
                            'nama' => $nama_tampilan,
                            'tipe' => $p['tipe'] ?? '-',
                            'warna' => $p['warna'] ?? '-',
                            'internal' => $p['internal'] ?? '-',
                            'imei' => $p['imei'],
                            'customer' => $p['nama_customer'] ?? '-',
                            'no_hp' => $p['no_hp'] ?? '-',
                            'metode' => cleanMetode($metode),
                            'tanggal' => date('d M Y H:i', strtotime($p['tanggal'])),
                            'harga_jual' => (int)$harga_jual,
                            'modal' => (int)$modal,
                            'dp' => (int)$dp,
                            'status' => $p['status_pencairan'] ?? '-',
                            'is_tt' => $is_tt,
                            'is_downgrade' => $is_downgrade,
                            'tt_harga_lama' => (int)$tt_harga_lama,
                            'tt_selisih' => (int)$tt_selisih,
                            'kas_masuk' => (int)$kas_masuk,
                            'piutang' => (int)$piutang,
                            'aset_masuk' => (int)$aset_masuk,
                            'laba' => (int)$laba
                        ];
                        
                        $detail_json = htmlspecialchars(json_encode($detail_data, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                    ?>
                        <div class="biz-card <?= $is_downgrade ? 'downgrade-card' : '' ?>">
                            <div class="biz-header" data-detail='<?= $detail_json ?>' onclick='showDetail(JSON.parse(this.dataset.detail))'>
                                <div class="biz-left">
                                    <div class="biz-icon-box" style="background: <?= $icon_bg ?>; color: <?= $icon_color ?>;">
                                        <i class="fas <?= $icon_class ?>"></i>
                                    </div>
                                    <div class="biz-info">
                                        <div class="biz-title"><?= htmlspecialchars($nama_tampilan) ?></div>
                                        <div class="biz-meta">
                                            <span><?= htmlspecialchars($p['nama_customer'] ?? 'Umum') ?></span>
                                            <span>•</span>
                                            <span class="badge bg-light text-dark border" style="font-size: 0.6rem;"><?= htmlspecialchars(cleanMetode($metode)) ?></span>
                                            <?php if ($is_tt): ?>
                                                <span class="badge-tt <?= $is_downgrade ? 'downgrade' : '' ?>">
                                                    <i class="fas fa-exchange-alt"></i>TT<?= $is_downgrade ? ' ↓' : '' ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($is_downgrade): ?>
                                                <span class="badge-downgrade"><i class="fas fa-arrow-circle-down"></i>DOWNGRADE</span>
                                            <?php endif; ?>
                                            <?php if ($is_cicilan): ?>
                                                <?php if ($is_pending): ?>
                                                    <span class="badge-cicilan-pending"><i class="fas fa-hourglass-half"></i>Pending</span>
                                                <?php else: ?>
                                                    <span class="badge-cicilan-cair"><i class="fas fa-check"></i>Cair</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="biz-right">
                                    <div class="biz-price">Rp <?= number_format($harga_jual, 0, ',', '.') ?></div>
                                    <div class="biz-laba <?= $laba < 0 ? 'loss' : 'profit' ?>">
                                        <i class="fas fa-arrow-trend-<?= $laba < 0 ? 'down' : 'up' ?> me-1"></i>Rp <?= number_format($laba, 0, ',', '.') ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($is_tt || $piutang > 0 || ($is_cicilan && $is_pending)): ?>
                            <div class="biz-breakdown">
                                <?php if ($is_tt): ?>
                                <div class="row-item">
                                    <span class="text-muted"><i class="fas fa-exchange-alt me-1" style="color: var(--purple-asset);"></i> Aset Masuk (Nilai Tukar)</span>
                                    <strong style="color: var(--purple-asset);">Rp <?= number_format($aset_masuk, 0, ',', '.') ?></strong>
                                </div>
                                <?php endif; ?>
                                <div class="row-item">
                                    <?php if ($kas_masuk < 0): ?>
                                        <span class="text-muted"><i class="fas fa-arrow-circle-down me-1" style="color: var(--danger-red);"></i> Kembalian (Refund)</span>
                                        <strong style="color: var(--danger-red);">- Rp <?= number_format(abs($kas_masuk), 0, ',', '.') ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted"><i class="fas fa-wallet me-1" style="color: var(--success-green);"></i> Kas Masuk</span>
                                        <strong style="color: var(--success-green);">Rp <?= number_format($kas_masuk, 0, ',', '.') ?></strong>
                                    <?php endif; ?>
                                </div>
                                <?php if ($piutang > 0): ?>
                                <div class="row-item">
                                    <span class="text-muted"><i class="fas fa-hourglass-half me-1" style="color: var(--warning-orange);"></i> Piutang</span>
                                    <strong style="color: var(--warning-orange);">Rp <?= number_format($piutang, 0, ',', '.') ?></strong>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($is_cicilan && $is_pending): ?>
                                <button type="button" class="btn-konfirmasi-cair" 
                                    data-id="<?= (int)$p['id'] ?>" 
                                    data-metode="<?= htmlspecialchars(cleanMetode($metode)) ?>" 
                                    data-piutang="<?= number_format($piutang, 0, ',', '.') ?>" 
                                    onclick="konfirmasiCair(this, event)">
                                    <i class="fas fa-money-check-alt"></i> Konfirmasi Dana Cair
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center text-muted py-5 bg-white rounded-4 border">
                    <i class="fas fa-folder-open fa-2x mb-2 d-block text-light"></i>
                    <span style="font-size: 0.85rem; font-weight: 500;">Tidak ada mutasi pada periode ini</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Detail Transaksi -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-receipt me-2 text-primary"></i>Detail Transaksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBodyContent"></div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Dana Cair -->
<div class="modal fade" id="modalKonfirmasiCair" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning text-white">
        <h5 class="modal-title"><i class="fas fa-money-check-alt"></i> Konfirmasi Pencairan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="konfirmasiText" class="mb-0"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-warning" id="btnKonfirmasiCairAction">Ya, Cairkan</button>
      </div>
    </div>
  </div>
</div>

<nav class="bottom-nav">
    <a href="index.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a href="stock_in.php" class="nav-item"><i class="fas fa-arrow-circle-down" style="color: #10B981;"></i><span>Stock In</span></a>
    <a href="admin_stock_out.php" class="nav-item"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i><span>Stock Out</span></a>
    <a href="return.php" class="nav-item"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i><span>Retur</span></a>
    <div class="nav-item active" id="moreMenuBtn" role="button"><i class="fas fa-bars"></i><span>Menu</span></div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showDetail(data) {
    const modalBody = document.getElementById('modalBodyContent');
    let html = '';

    html += `
        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-box" style="color: var(--primary-dongker);"></i> Detail Barang</div>
            <div class="detail-card">
                <div class="detail-row"><span class="text-muted">Nama</span><strong>${data.nama}</strong></div>
                <div class="detail-row"><span class="text-muted">Tipe</span><strong>${data.tipe}</strong></div>
                <div class="detail-row"><span class="text-muted">Warna</span><strong>${data.warna}</strong></div>
                <div class="detail-row"><span class="text-muted">Internal</span><strong>${data.internal}</strong></div>
                <div class="detail-row"><span class="text-muted">IMEI</span><strong style="font-family: monospace;">${data.imei}</strong></div>
            </div>
        </div>
    `;

    html += `
        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-user" style="color: var(--primary-dongker);"></i> Info Customer</div>
            <div class="detail-card">
                <div class="detail-row"><span class="text-muted">Nama</span><strong>${data.customer}</strong></div>
                <div class="detail-row"><span class="text-muted">No. HP</span><strong>${data.no_hp}</strong></div>
                <div class="detail-row"><span class="text-muted">Tanggal</span><strong>${data.tanggal}</strong></div>
                <div class="detail-row"><span class="text-muted">Metode</span><strong>${data.metode}</strong></div>
                <div class="detail-row"><span class="text-muted">Status</span><strong class="badge ${data.status == 'Cair' ? 'bg-success' : 'bg-warning'} text-white">${data.status}</strong></div>
            </div>
        </div>
    `;

    html += `
        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-chart-line" style="color: var(--primary-dongker);"></i> Rincian Cashflow</div>
            <div class="detail-card">
                <div class="detail-row"><span class="text-muted">Harga Jual</span><strong>Rp ${data.harga_jual.toLocaleString('id-ID')}</strong></div>
                <div class="detail-row"><span class="text-muted">Modal</span><strong style="color: #EF4444;">- Rp ${data.modal.toLocaleString('id-ID')}</strong></div>
    `;

    if (data.is_tt) {
        html += `
                <div class="detail-row" style="background: rgba(139,92,246,0.05); border-radius: 6px; padding: 8px;">
                    <span style="color: var(--purple-asset);"><i class="fas fa-exchange-alt me-1"></i> Aset Masuk (Nilai Tukar)</span>
                    <strong style="color: var(--purple-asset);">Rp ${data.tt_harga_lama.toLocaleString('id-ID')}</strong>
                </div>
        `;
    }

    if (data.dp > 0) {
        html += `
                <div class="detail-row"><span class="text-muted">DP Tambahan</span><strong>Rp ${data.dp.toLocaleString('id-ID')}</strong></div>
        `;
    }

    if (data.kas_masuk < 0) {
        html += `
                <div class="detail-row" style="background: rgba(239,68,68,0.05); border-radius: 6px; padding: 8px;">
                    <span style="color: #EF4444;"><i class="fas fa-arrow-circle-down me-1"></i> Kembalian (Refund)</span>
                    <strong style="color: #EF4444;">- Rp ${Math.abs(data.kas_masuk).toLocaleString('id-ID')}</strong>
                </div>
        `;
    } else {
        html += `
                <div class="detail-row" style="background: rgba(16,185,129,0.05); border-radius: 6px; padding: 8px;">
                    <span style="color: var(--success-green);"><i class="fas fa-wallet me-1"></i> Kas Masuk</span>
                    <strong style="color: var(--success-green);">Rp ${data.kas_masuk.toLocaleString('id-ID')}</strong>
                </div>
        `;
    }

    if (data.piutang > 0) {
        html += `
                <div class="detail-row" style="background: rgba(245,158,11,0.05); border-radius: 6px; padding: 8px;">
                    <span style="color: var(--warning-orange);"><i class="fas fa-hourglass-half me-1"></i> Piutang</span>
                    <strong style="color: var(--warning-orange);">Rp ${data.piutang.toLocaleString('id-ID')}</strong>
                </div>
        `;
    }

    const labaColor = data.laba < 0 ? '#EF4444' : 'var(--success-green)';
    const labaIcon = data.laba < 0 ? 'fa-arrow-trend-down' : 'fa-coins';
    const labaLabel = data.laba < 0 ? 'Rugi Bersih' : 'Laba Bersih';

    html += `
                <div class="detail-row" style="border-top: 2px solid #E2E8F0; margin-top: 8px; padding-top: 10px;">
                    <span style="font-weight: 800;"><i class="fas ${labaIcon} me-1"></i> ${labaLabel}</span>
                    <strong style="color: ${labaColor}; font-size: 1.1rem;">Rp ${data.laba.toLocaleString('id-ID')}</strong>
                </div>
            </div>
        </div>
    `;

    if (data.is_downgrade) {
        html += `
            <div style="background: rgba(239,68,68,0.05); border: 1px solid rgba(239,68,68,0.2); border-radius: 10px; padding: 12px; font-size: 0.8rem; color: #991B1B;">
                <i class="fas fa-info-circle me-1"></i>
                <strong>Transaksi Downgrade:</strong> Nilai tukar HP lama lebih besar dari harga HP baru. Toko memberikan kembalian <strong>Rp ${Math.abs(data.kas_masuk).toLocaleString('id-ID')}</strong>.
            </div>
        `;
    }

    modalBody.innerHTML = html;
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

// Data sementara untuk konfirmasi modal
let currentKonfirmasiData = {};

function konfirmasiCair(btn, e) {
    e.stopPropagation();
    currentKonfirmasiData = {
        id: btn.getAttribute('data-id'),
        metode: btn.getAttribute('data-metode'),
        piutang: btn.getAttribute('data-piutang'),
        startDate: '<?= $start_date ?>',
        endDate: '<?= $end_date ?>'
    };
    document.getElementById('konfirmasiText').innerHTML = 
        `Konfirmasi bahwa dana cicilan dari <strong>${currentKonfirmasiData.metode}</strong> sebesar <strong>Rp ${currentKonfirmasiData.piutang}</strong> sudah cair?`;
    var modal = new bootstrap.Modal(document.getElementById('modalKonfirmasiCair'));
    modal.show();
}

// Tombol aksi di dalam modal konfirmasi
document.getElementById('btnKonfirmasiCairAction').addEventListener('click', function() {
    if (currentKonfirmasiData.id) {
        const url = new URL('konfirmasi_cair.php', window.location.href);
        url.searchParams.set('id', currentKonfirmasiData.id);
        url.searchParams.set('start_date', currentKonfirmasiData.startDate);
        url.searchParams.set('end_date', currentKonfirmasiData.endDate);
        window.location.href = url.toString();
    }
});

// Mobile menu toggle
const moreBtn = document.getElementById('moreMenuBtn');
const popup = document.getElementById('moreMenuPopup');
const overlay = document.getElementById('popupOverlay');

if (moreBtn && popup && overlay) {
    moreBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        popup.classList.toggle('show');
        overlay.classList.toggle('show');
    });
    overlay.addEventListener('click', function() {
        popup.classList.remove('show');
        overlay.classList.remove('show');
    });
    document.addEventListener('click', function(e) {
        if (popup && !popup.contains(e.target) && e.target !== moreBtn && !moreBtn.contains(e.target)) {
            popup.classList.remove('show');
            overlay.classList.remove('show');
        }
    });
}
</script>
</body>
</html>