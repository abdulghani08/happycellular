<?php
session_start();
require_once '../api/auth_helper.php';
restrictTo(['admin']);
include '../connection.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// CSRF TOKEN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =========================================================================
// ENDPOINT AJAX: AMBIL DATA MASTER UNTUK DROPDOWN & CEK IMEI HISTORIS
// =========================================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    try {
        // --- TAMBAHAN: CEK IMEI HISTORIS ---
        if ($_GET['action'] === 'get_imei_data') {
            $imei = trim($_GET['imei']);
            
            // Ambil data spesifikasi terakhir dari tabel barang berdasarkan IMEI
            $stmt = $koneksi->prepare("SELECT nama, tipe, warna, internal FROM barang WHERE imei = ? ORDER BY id DESC LIMIT 1");
            $stmt->bind_param("s", $imei);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Ekstrak merk dan nama_barang dari kolom 'nama' (format: MERK NAMA_BARANG)
                $parts = explode(' ', $row['nama'], 2);
                $merk = $parts[0] ?? '';
                $nama_barang = $parts[1] ?? '';
                
                echo json_encode([
                    'status' => 'success', 
                    'data' => [
                        'merk' => $merk,
                        'nama_barang' => $nama_barang,
                        'kondisi' => $row['tipe'],
                        'internal' => $row['internal'],
                        'warna' => $row['warna']
                    ]
                ]);
            } else {
                echo json_encode(['status' => 'not_found']);
            }
            exit();
        }

        // Action Bawaan:
        if ($_GET['action'] === 'get_nama_barang') {
            $merk_id = (int)($_GET['merk_id'] ?? 0);
            $stmt = $koneksi->prepare("SELECT id, nama_barang FROM master_barang WHERE merk_id = ? AND status = 'aktif' ORDER BY nama_barang ASC");
            $stmt->bind_param("i", $merk_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while ($r = $result->fetch_assoc()) {
                $data[] = ['id' => $r['id'], 'nama_barang' => $r['nama_barang']];
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
            exit();
        }
        
        if ($_GET['action'] === 'get_kondisi') {
            $barang_id = (int)($_GET['barang_id'] ?? 0);
            $stmt = $koneksi->prepare("SELECT DISTINCT kondisi FROM master_varian_barang WHERE barang_id = ? AND status = 'aktif' AND kondisi IS NOT NULL AND kondisi != '' ORDER BY kondisi ASC");
            $stmt->bind_param("i", $barang_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while ($r = $result->fetch_assoc()) {
                $data[] = ['kondisi' => $r['kondisi']];
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
            exit();
        }
        
        if ($_GET['action'] === 'get_internal') {
            $barang_id = (int)($_GET['barang_id'] ?? 0);
            $kondisi = trim($_GET['kondisi'] ?? '');
            $stmt = $koneksi->prepare("SELECT DISTINCT internal_storage FROM master_varian_barang WHERE barang_id = ? AND kondisi = ? AND status = 'aktif' AND internal_storage IS NOT NULL ORDER BY internal_storage ASC");
            $stmt->bind_param("is", $barang_id, $kondisi);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while ($r = $result->fetch_assoc()) {
                $data[] = ['internal_storage' => $r['internal_storage']];
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
            exit();
        }
        
        if ($_GET['action'] === 'get_warna') {
            $barang_id = (int)($_GET['barang_id'] ?? 0);
            $kondisi = trim($_GET['kondisi'] ?? '');
            $internal = trim($_GET['internal_storage'] ?? '');
            $stmt = $koneksi->prepare("SELECT DISTINCT warna FROM master_varian_barang WHERE barang_id = ? AND kondisi = ? AND internal_storage = ? AND status = 'aktif' AND warna IS NOT NULL ORDER BY warna ASC");
            $stmt->bind_param("iss", $barang_id, $kondisi, $internal);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while ($r = $result->fetch_assoc()) {
                $data[] = ['warna' => $r['warna']];
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
            exit();
        }
        
        echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenali']);
        exit();
    } catch (Exception $e) {
        error_log("AJAX Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem']);
        exit();
    }
}

// =========================================================================
// AMBIL LIST MERK
// =========================================================================
$merkList = [];
try {
    $qMerk = $koneksi->query("SELECT id, nama_merk FROM master_merk WHERE status = 'aktif' ORDER BY nama_merk ASC");
    while ($r = $qMerk->fetch_assoc()) $merkList[] = $r;
} catch (Exception $e) {
    error_log("Error load merk: " . $e->getMessage());
}

// =========================================================================
// PROSES SIMPAN PEMBELIAN DARI CUSTOMER
// HANYA UPDATE JIKA IMEI SUDAH ADA DENGAN STATUS 'NO READY'
// =========================================================================
$pesan = "";
$status_pesan = "";

if (isset($_POST['proses_beli'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $pesan = "Token keamanan tidak valid.";
        $status_pesan = "error";
    } else {
        try {
            $imei           = trim($_POST['imei'] ?? '');
            $merk           = trim($_POST['merk'] ?? '');
            $nama_barang    = trim($_POST['nama_barang'] ?? '');
            $kondisi        = trim($_POST['kondisi'] ?? 'Normal');
            $tipe           = $kondisi;
            $warna          = trim($_POST['warna'] ?? '');
            $internal       = trim($_POST['internal'] ?? '');
            $harga_beli     = (int)str_replace('.', '', $_POST['harga_beli'] ?? '0');
            $harga_jual_est = (int)str_replace('.', '', $_POST['harga_jual_estimasi'] ?? '0');
            $metode_bayar   = trim($_POST['metode_bayar'] ?? 'Cash');
            $nama_customer  = trim($_POST['nama_customer'] ?? '');
            $no_hp          = trim($_POST['no_hp'] ?? '-');
            
            $catatan_manual = trim($_POST['keterangan'] ?? '');
            $user_petugas   = $_SESSION['username'] ?? 'admin';

            if (!preg_match('/^[0-9]{5,}$/', $imei)) throw new Exception("IMEI tidak valid");
            if (empty($merk) || empty($nama_barang)) throw new Exception("Merek dan nama barang wajib diisi");
            if ($harga_beli <= 0) throw new Exception("Harga beli harus lebih dari 0");
            if (empty($nama_customer)) throw new Exception("Nama customer wajib diisi");

            $stmt_cek = $koneksi->prepare("SELECT id, status FROM barang WHERE imei = ?");
            $stmt_cek->bind_param("s", $imei);
            $stmt_cek->execute();
            $result_cek = $stmt_cek->get_result();
            
            $is_update = false;
            $existing_id = null;
            
            if ($row_cek = $result_cek->fetch_assoc()) {
                if ($row_cek['status'] === 'READY') {
                    $stmt_cek->close();
                    throw new Exception("IMEI ini saat ini MASIH ADA di dalam stok toko (Belum terjual)!");
                } elseif ($row_cek['status'] === 'NO READY') {
                    $is_update = true;
                    $existing_id = $row_cek['id'];
                } else {
                    $stmt_cek->close();
                    throw new Exception("IMEI ini memiliki status " . $row_cek['status'] . " dan tidak dapat dibeli kembali.");
                }
            }
            $stmt_cek->close();

            $koneksi->begin_transaction();

            $nama_lengkap_barang = strtoupper($merk . " " . $nama_barang);
            if ($harga_jual_est <= 0) $harga_jual_est = (int)($harga_beli * 1.2);

            $spec_info = "$merk $nama_barang | Kondisi/Tipe: $kondisi | Internal: $internal | Warna: $warna";
            $keterangan_final = !empty($catatan_manual) ? ($catatan_manual . " | Spec: " . $spec_info) : $spec_info;

            $supplier = "JUAL BELI";
            $status_barang = 'READY';
            $tipe_barang = strtoupper($tipe);

            if ($is_update) {
                $stmt_update = $koneksi->prepare("
                    UPDATE barang SET 
                        nama = ?, tipe = ?, warna = ?, internal = ?, 
                        supplier = ?, keterangan = ?, modal = ?, harga_jual = ?, 
                        tanggal = NOW(), status = ?
                    WHERE id = ?
                ");
                $stmt_update->bind_param(
                    "ssssssiisi",
                    $nama_lengkap_barang, $tipe_barang, $warna, $internal,
                    $supplier, $keterangan_final, $harga_beli, $harga_jual_est,
                    $status_barang, $existing_id
                );
                if (!$stmt_update->execute()) throw new Exception("Gagal memperbarui stok: " . $stmt_update->error);
                $stmt_update->close();
            } else {
                $stmt_insert = $koneksi->prepare("
                    INSERT INTO barang (nama, tipe, imei, warna, internal, supplier, keterangan, modal, harga_jual, tanggal, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $stmt_insert->bind_param(
                    "sssssssiis",
                    $nama_lengkap_barang, $tipe_barang, $imei, $warna, $internal,
                    $supplier, $keterangan_final, $harga_beli, $harga_jual_est, $status_barang
                );
                if (!$stmt_insert->execute()) throw new Exception("Gagal menyimpan ke stok: " . $stmt_insert->error);
                $stmt_insert->close();
            }

            $stmt_log = $koneksi->prepare("
                INSERT INTO pembelian_customer 
                (tanggal, imei, nama_barang, tipe, warna, internal, kondisi, nama_customer, no_hp, harga_beli, harga_jual_estimasi, metode_bayar_ke_customer, catatan, user) 
                VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_log->bind_param(
                "ssssssssiisss",  
                $imei, $nama_barang, $tipe, $warna, $internal, $kondisi,
                $nama_customer, $no_hp, $harga_beli, $harga_jual_est,
                $metode_bayar, $catatan_manual, $user_petugas
            );
            if (!$stmt_log->execute()) throw new Exception("Gagal menyimpan log pembelian: " . $stmt_log->error);
            $stmt_log->close();

            $koneksi->commit();

            $pesan = "Pembelian berhasil! HP telah masuk stok dengan supplier JUAL BELI.";
            $status_pesan = "success";

        } catch (Exception $e) {
            if ($koneksi->connect_errno === 0) $koneksi->rollback();
            error_log("Beli Customer Error: " . $e->getMessage());
            $pesan = $e->getMessage();
            $status_pesan = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Beli dari Customer · Happy Cellular</title>

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
            --warning-orange: #F59E0B;
            --purple-asset: #8B5CF6;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 1.2rem;
        }

        .scan-card {
            background: var(--surface-card);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 18px rgba(0,0,0,0.02);
            position: relative;
            border: 1px solid rgba(0,0,0,0.01);
            margin-bottom: 1rem;
        }
        .card-icon {
            width: 48px; height: 48px;
            background: var(--primary-dongker-soft);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 12px;
            font-size: 1.4rem;
            color: var(--primary-dongker);
        }
        .card-icon.purple { background: rgba(139,92,246,0.1); color: var(--purple-asset); }
        .scan-card h2 { font-weight: 800; color: var(--text-main); font-size: 1.1rem; margin-bottom: 4px; }
        .input-wrapper {
            background: #F8FAFC;
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            padding: 4px 12px;
            margin: 12px 0;
            display: flex; align-items: center; transition: 0.3s;
        }
        .input-wrapper:focus-within {
            border-color: var(--primary-dongker); background: white; box-shadow: 0 0 0 3px var(--primary-dongker-soft);
        }
        .input-wrapper input {
            background: transparent; border: none; width: 100%; padding: 10px 8px; font-size: 1rem;
            font-weight: 700; color: var(--text-main); outline: none; letter-spacing: 0.5px;
        }
        .btn-clear { background: none; border: none; color: var(--text-muted); font-size: 1rem; padding: 6px; cursor: pointer; }
        .btn-clear:hover { color: var(--danger-red); }

        .card-custom {
            background: var(--surface-card); border-radius: 20px; padding: 1.5rem;
            box-shadow: 0 4px 18px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.01);
            margin-bottom: 1rem;
        }
        .card-title-custom {
            font-size: 0.85rem; font-weight: 800; color: var(--primary-dongker); letter-spacing:0.5px;
            text-transform: uppercase; display: flex; align-items:center; gap:8px;
            margin-bottom:1rem; border-bottom:1px solid #F1F5F9; padding-bottom:10px;
        }
        .form-control, .form-select {
            border-radius:10px; border:1px solid #E2E8F0; padding:0.75rem;
            font-weight:600; font-size:0.85rem; color:var(--text-main);
        }
        .form-control:focus, .form-select:focus { border-color:var(--primary-dongker); box-shadow:0 0 0 3px var(--primary-dongker-soft); outline:none; }

        .summary-bar {
            background: linear-gradient(135deg, var(--purple-asset) 0%, var(--primary-dongker) 100%);
            border-radius: 16px; padding: 1.2rem;
            box-shadow: 0 4px 18px rgba(139, 92, 246, 0.2);
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 1rem; color: white;
        }
        .summary-label { font-size: 0.7rem; font-weight: 700; opacity: 0.9; text-transform: uppercase; }
        .summary-val { font-size: 1.3rem; font-weight: 800; margin: 0; letter-spacing: -0.5px; }
        .btn-submit-trx {
            background: white; color: var(--primary-dongker); font-weight: 700; font-size: 0.85rem;
            padding: 10px 18px; border: none; border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.2s; display: flex; align-items: center; gap: 6px;
        }
        .btn-submit-trx:hover { transform: translateY(-1px); }
        .btn-submit-trx:active { transform: scale(0.97); }

        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0; height: var(--bottom-nav-height);
            background: var(--surface-card); display: flex; justify-content: space-around; align-items: center;
            box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.04); border-top: 1px solid #EDF2F7; z-index: 1050;
            padding-bottom: env(safe-area-inset-bottom, 0);
        }
        .nav-item {
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
            text-decoration: none; color: #A0AEC0; font-size: 0.7rem; font-weight: 600; flex: 1; height: 100%; transition: all 0.2s ease;
        }
        .nav-item i { font-size: 1.25rem; }
        .nav-item.active { color: var(--primary-dongker); }

        #scanner-container { display: none; position: fixed; inset: 0; background: #000; z-index: 2000; }
        #reader { width:100%; height:100%; }
        #reader video { width:100%; height:100%; object-fit:cover; }
        .scanner-header { position: absolute; top: 0; left: 0; width: 100%; padding: 20px; display: flex; justify-content: space-between; z-index: 25; background: linear-gradient(to bottom, rgba(0,0,0,0.8), transparent); }
        .btn-glass { background: rgba(255,255,255,0.2); backdrop-filter: blur(8px); border: none; color: white; border-radius: 30px; padding: 10px 20px; font-weight: 600; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-glass:hover { background: rgba(255,255,255,0.3); }
        .btn-glass.bg-danger { background: rgba(239,68,68,0.8) !important; }
        .scanner-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10; pointer-events: none; clip-path: polygon(0% 0%, 0% 100%, 100% 100%, 100% 0%, 0% 0%, calc(50% - 130px) calc(50% - 90px), calc(50% + 130px) calc(50% - 90px), calc(50% + 130px) calc(50% + 90px), calc(50% - 130px) calc(50% + 90px), calc(50% - 130px) calc(50% - 90px)); }
        .scanner-reticle { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 260px; height: 180px; z-index: 15; pointer-events: none; }
        .scanner-reticle::before, .scanner-reticle::after, .scanner-reticle .corner-bottom::before, .scanner-reticle .corner-bottom::after { content: ''; position: absolute; width: 30px; height: 30px; border-color: #fff; border-style: solid; }
        .scanner-reticle::before { top:0; left:0; border-width:4px 0 0 4px; border-top-left-radius:12px; }
        .scanner-reticle::after { top:0; right:0; border-width:4px 4px 0 0; border-top-right-radius:12px; }
        .scanner-reticle .corner-bottom::before { bottom:0; left:0; border-width:0 0 4px 4px; border-bottom-left-radius:12px; }
        .scanner-reticle .corner-bottom::after { bottom:0; right:0; border-width:0 4px 4px 0; border-bottom-right-radius:12px; }
        .scan-laser { position: absolute; width: 100%; height: 2px; background: var(--purple-asset); top: 0; box-shadow: 0 0 12px var(--purple-asset); animation: laser 2s ease-in-out infinite; }
        @keyframes laser { 0%, 100% { top: 0; opacity: 0; } 10%, 90% { opacity: 1; } 50% { top: 100%; } }
        .scanner-helper-text { position: absolute; top: calc(50% + 120px); left: 50%; transform: translateX(-50%); color: white; font-weight: 500; font-size: 0.9rem; z-index: 15; text-shadow: 0 2px 4px rgba(0,0,0,0.5); text-align: center; width: 100%; }

        .desktop-sidebar { display: none; }
        
        /* Popup Menu (mobile) */
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
        
        @media (max-width: 575.98px) {
            .main-wrapper { padding: 0.75rem; }
            .scan-card, .card-custom { padding: 1.2rem; }
            .summary-bar { flex-direction: column; gap: 12px; text-align: center; }
            .btn-submit-trx { width: 100%; justify-content: center; }
        }
        
        @media (min-width: 992px) {
            body { padding-bottom: 0; display: flex; background: #F8FAFC; }
            .bottom-nav { display: none !important; }
            .navbar-top { position: static; box-shadow: none; background: transparent; padding: 1.5rem 2rem 0; width: 100%; }
            .navbar-top .logo-area { display: none; }
            .desktop-sidebar {
                display: flex; flex-direction: column; width: var(--sidebar-width); background: var(--surface-card);
                height: 100vh; position: sticky; top: 0; border-right: 1px solid #E2E8F0; padding: 1.5rem; z-index: 1010;
            }
            .sidebar-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #F1F5F9; }
            .sidebar-menu { display: flex; flex-direction: column; gap: 5px; flex-grow: 1; }
            .sidebar-link {
                display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-muted);
                text-decoration: none; font-weight: 600; font-size: 0.85rem; border-radius: 12px; transition: all 0.2s;
            }
            .sidebar-link i { font-size: 1.1rem; width: 20px; text-align: center; }
            .sidebar-link:hover, .sidebar-link.active { background: var(--primary-dongker-soft); color: var(--primary-dongker); }
            .sidebar-divider { height: 1px; background: #F1F5F9; margin: 10px 0; }
            .content-wrapper { flex-grow: 1; display: flex; flex-direction: column; min-width: 0; }
            .main-wrapper { max-width: 1200px; padding: 1.5rem 2rem; }
            .more-menu-popup, .popup-overlay { display: none !important; }
        }

        #loadingOverlay {
            position: fixed; inset: 0; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(4px);
            z-index: 9999; display: none; align-items: center; justify-content: center; color: white; flex-direction: column;
        }

        .info-banner {
            background: rgba(139,92,246,0.08);
            border: 1px solid rgba(139,92,246,0.2);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
            color: #6d28d9;
            font-weight: 500;
        }
        .info-banner i { font-size: 1.1rem; color: var(--purple-asset); }

        /* PAYMENT BUTTON STYLE */
        .pay-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: #F8FAFC;
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: left;
            width: 100%;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .pay-btn:hover {
            border-color: #94A3B8;
            background: #F1F5F9;
            transform: translateY(-1px);
        }
        .pay-btn.selected {
            border-color: var(--purple-asset);
            background: rgba(139,92,246,0.08);
            box-shadow: 0 0 0 3px rgba(139,92,246,0.2);
        }
        .pay-icon-box {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: white;
            flex-shrink: 0;
        }
        .pay-label {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-main);
            line-height: 1.2;
        }
        .pay-sublabel {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 2px;
        }

        /* Konfirmasi styling tambahan */
        .konfirmasi-section {
            text-align: left;
            margin-bottom: 1.2rem;
        }
        .konfirmasi-section label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 4px;
            display: block;
        }
        .konfirmasi-highlight {
            background: rgba(139,92,246,0.07);
            border-radius: 10px;
            padding: 14px;
            border: 1px solid rgba(139,92,246,0.2);
            margin-bottom: 1rem;
        }
        .konfirmasi-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            margin-bottom: 6px;
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
        <a href="keuangan.php" class="sidebar-link"><i class="fas fa-chart-pie"></i> Analisis Laba</a>
        <a href="manajemen_label.php" class="sidebar-link"><i class="fas fa-print"></i> Cetak Label</a>
        <a href="tukar_tambah.php" class="sidebar-link"><i class="fas fa-exchange-alt"></i> Tukar Tambah</a>
        <a href="beli_customer.php" class="sidebar-link active"><i class="fas fa-hand-holding-usd" style="color: #8B5CF6;"></i> Buy Out</a>
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

        <?php if(!empty($pesan)): ?>
            <script>
                Swal.fire({
                    icon: '<?= $status_pesan ?>',
                    title: '<?= $status_pesan == "success" ? "Berhasil!" : "Gagal!" ?>',
                    text: '<?= addslashes($pesan) ?>',
                    confirmButtonColor: '#1E4A7A'
                }).then(() => {
                    <?php if($status_pesan == "success"): ?>
                        window.location.href = 'riwayat_beli_customer.php';
                    <?php endif; ?>
                });
            </script>
        <?php endif; ?>

        <div class="info-banner">
            <i class="fas fa-info-circle"></i>
            <div>Fitur ini untuk mencatat pembelian HP dari customer. Jika IMEI pernah terjual (status NO READY), data akan di-<strong>update</strong>. Harga beli harus diisi ulang.</div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold m-0" style="font-size: 1.1rem;"><i class="fas fa-hand-holding-usd me-2" style="color: var(--purple-asset);"></i>Beli dari Customer</h3>
            <a href="riwayat_beli_customer.php" class="btn btn-sm" style="background:rgba(139,92,246,0.1); color:var(--purple-asset); font-weight:700; border-radius:10px; font-size: 0.8rem;"><i class="fas fa-history"></i> Riwayat</a>
        </div>

        <form id="formBeli" method="POST">
            <input type="hidden" name="proses_beli" value="1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="metode_bayar" id="hidden_metode_bayar" value="">
            <input type="hidden" name="nama_customer" id="hidden_nama_customer" value="">
            <input type="hidden" name="no_hp" id="hidden_no_hp" value="">

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="scan-card">
                        <div class="card-icon purple"><i class="fas fa-barcode"></i></div>
                        <h2>Scan IMEI HP</h2>
                        <p class="text-muted" style="font-size:0.8rem;">Scan atau masukkan IMEI HP dari customer</p>

                        <div class="input-wrapper">
                            <input type="text" id="imei_input" name="imei" placeholder="Masukkan IMEI" maxlength="15" autofocus required>
                            <button type="button" class="btn-clear" onclick="document.getElementById('imei_input').value=''; document.getElementById('imei_input').focus();"><i class="fas fa-times-circle"></i></button>
                        </div>

                        <button type="button" class="btn-manual" style="width:100%; padding:12px; border-radius:12px; background:var(--primary-dongker-soft); color:var(--primary-dongker); border:none; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px;" onclick="openScanner()">
                            <i class="fas fa-camera"></i> Scan dengan Kamera
                        </button>
                    </div>

                    <div class="card-custom">
                        <div class="card-title-custom">
                            <i class="fas fa-mobile-alt" style="color: var(--purple-asset);"></i> Spesifikasi HP (Dari Data Master)
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">Merek *</label>
                                <select name="merk" id="merk" class="form-select" required>
                                    <option value="">-- Pilih Merek --</option>
                                    <?php foreach ($merkList as $m): ?>
                                    <option value="<?= htmlspecialchars($m['nama_merk']) ?>" data-id="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_merk']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">Nama Barang *</label>
                                <select name="nama_barang" id="nama_barang" class="form-select" required disabled>
                                    <option value="">-- Pilih Merek Dahulu --</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">Kondisi / Tipe *</label>
                                <select name="kondisi" id="kondisi" class="form-select" required disabled>
                                    <option value="">-- Pilih Nama Barang Dahulu --</option>
                                </select>
                            </div>
                            
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Internal Storage *</label>
                                <select name="internal" id="internal" class="form-select" required disabled>
                                    <option value="">-- Pilih Kondisi Dahulu --</option>
                                </select>
                            </div>
                            
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Warna *</label>
                                <select name="warna" id="warna" class="form-select" required disabled>
                                    <option value="">-- Pilih Storage Dahulu --</option>
                                </select>
                            </div>

                            <div class="col-12 mt-3">
                                <label class="form-label small fw-bold text-primary mb-1"><i class="fas fa-edit"></i> Keterangan / Catatan Kondisi Tambahan</label>
                                <textarea name="keterangan" id="keterangan" class="form-control" rows="2" placeholder="Contoh: Mulus, ada lecet sedikit di ujung, fullset..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card-custom">
                        <div class="card-title-custom">
                            <i class="fas fa-money-bill-wave" style="color: var(--success-green);"></i> Harga & Pembayaran
                        </div>

                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">Harga Beli dari Customer (Modal Baru) *</label>
                                <input type="text" name="harga_beli" id="harga_beli" class="form-control fw-bold" style="color: var(--danger-red);" placeholder="0" oninput="formatRibuan(this); hitungEstimasi()" required>
                                <small class="text-muted" style="font-size:0.7rem;">Dana yang akan dibayarkan ke customer saat ini</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">Estimasi Harga Jual Kembali *</label>
                                <input type="text" name="harga_jual_estimasi" id="harga_jual_est" class="form-control fw-bold" style="color: var(--success-green);" placeholder="Masukkan harga jual estimasi" oninput="formatRibuan(this); hitungEstimasi()" required>
                                <small class="text-muted" style="font-size:0.7rem;">Harga jual baru nanti saat HP dijual kembali</small>
                            </div>
                        </div>

                        <div id="preview-margin" style="margin-top:12px; padding:12px; background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); border-radius:10px; display:none;">
                            <div style="font-size:0.7rem; color:var(--text-muted); font-weight:600; margin-bottom:4px;">ESTIMASI MARGIN</div>
                            <div style="font-size:1rem; font-weight:800; color:var(--success-green);" id="preview-margin-val">Rp 0</div>
                            <div style="font-size:0.7rem; color:var(--text-muted);" id="preview-margin-pct">0% dari harga beli</div>
                        </div>
                    </div>

                    <div class="card-custom" style="background:rgba(139,92,246,0.03); border:1px dashed rgba(139,92,246,0.3);">
                        <i class="fas fa-user-check" style="color:var(--purple-asset); font-size:1.2rem; margin-bottom:6px; display:block;"></i>
                        <p style="font-size:0.85rem; font-weight:600; color:var(--text-main); margin-bottom:0;">Data customer & metode pembayaran akan diisi pada saat konfirmasi.</p>
                    </div>
                </div>
            </div>

            <div class="summary-bar">
                <div>
                    <span class="summary-label">Total Dana Dibayarkan</span>
                    <h3 class="summary-val" id="summary-total">Rp 0</h3>
                </div>
                <button type="button" class="btn-submit-trx" onclick="konfirmasiBeli()">
                    <i class="fas fa-check-circle"></i> Beli & Masuk Stok
                </button>
            </div>
        </form>

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
    <div class="scanner-reticle"><div class="corner-bottom"></div><div class="scan-laser"></div></div>
    <div class="scanner-helper-text">Posisikan barcode di dalam area kotak</div>
</div>

<div id="loadingOverlay">
    <div class="spinner-border text-light mb-3" style="width: 3rem; height: 3rem;"></div>
    <div class="fw-bold">Memproses Pembelian...</div>
</div>

<audio id="beep" src="https://www.soundjay.com/buttons/sounds/beep-07.mp3" preload="auto"></audio>

<script>
let selectedBarangId = null;

// ==========================================
// FUNGSI PEMBANTU: Fetch & isi dropdown, kembalikan Promise
// ==========================================
function fetchDropdown(url, selectId, placeholder, valueKey, textKey, dataIdKey = null) {
    return fetch(url)
    .then(res => res.json())
    .then(result => {
        const select = document.getElementById(selectId);
        if (result.status === 'success' && result.data.length > 0) {
            select.innerHTML = `<option value="">${placeholder}</option>`;
            result.data.forEach(item => {
                const option = document.createElement('option');
                option.value = item[valueKey];
                option.textContent = item[textKey];
                if (dataIdKey) option.dataset.id = item[dataIdKey];
                select.appendChild(option);
            });
            select.disabled = false;
        } else {
            resetDropdown(selectId, '-- Tidak ada data --', true);
        }
        return Promise.resolve();
    })
    .catch(error => {
        resetDropdown(selectId, '-- Error --', true);
        return Promise.reject(error);
    });
}

// ==========================================
// AUTOFILL BERJENJANG (ASYNC)
// ==========================================
async function cekDataIMEIHistoris(imei) {
    if (!imei || imei.length < 5) return;
    
    try {
        const response = await fetch(`beli_customer.php?action=get_imei_data&imei=${encodeURIComponent(imei)}`);
        const res = await response.json();
        if (res.status !== 'success') return;
        
        const d = res.data;

        // 1. Pilih Merk
        await pilihDropdown('merk', d.merk);
        
        // Dapatkan merkId yang terpilih
        const merkSelect = document.getElementById('merk');
        const merkOption = merkSelect.options[merkSelect.selectedIndex];
        const merkId = merkOption ? merkOption.dataset.id : null;
        if (!merkId) return;
        
        // 2. Muat & pilih Nama Barang
        await fetchDropdown(
            `beli_customer.php?action=get_nama_barang&merk_id=${merkId}`,
            'nama_barang',
            '-- Pilih Nama Barang --',
            'nama_barang',
            'nama_barang',
            'id'
        );
        await pilihDropdown('nama_barang', d.nama_barang);
        
        // Dapatkan barangId
        const namaSelect = document.getElementById('nama_barang');
        const namaOption = namaSelect.options[namaSelect.selectedIndex];
        selectedBarangId = namaOption ? namaOption.dataset.id : null;
        if (!selectedBarangId) return;
        
        // 3. Muat & pilih Kondisi
        await fetchDropdown(
            `beli_customer.php?action=get_kondisi&barang_id=${selectedBarangId}`,
            'kondisi',
            '-- Pilih Kondisi --',
            'kondisi',
            'kondisi'
        );
        await pilihDropdown('kondisi', d.kondisi);
        
        const kondisiValue = document.getElementById('kondisi').value;
        if (!kondisiValue) return;
        
        // 4. Muat & pilih Internal
        await fetchDropdown(
            `beli_customer.php?action=get_internal&barang_id=${selectedBarangId}&kondisi=${encodeURIComponent(kondisiValue)}`,
            'internal',
            '-- Pilih Storage --',
            'internal_storage',
            'internal_storage'
        );
        await pilihDropdown('internal', d.internal);
        
        const internalValue = document.getElementById('internal').value;
        if (!internalValue) return;
        
        // 5. Muat & pilih Warna
        await fetchDropdown(
            `beli_customer.php?action=get_warna&barang_id=${selectedBarangId}&kondisi=${encodeURIComponent(kondisiValue)}&internal_storage=${encodeURIComponent(internalValue)}`,
            'warna',
            '-- Pilih Warna --',
            'warna',
            'warna'
        );
        await pilihDropdown('warna', d.warna);
        
        // Kosongkan harga & estimasi
        document.getElementById('harga_beli').value = '';
        document.getElementById('harga_jual_est').value = '';
        hitungEstimasi();
        
        Swal.fire({
            toast: true, position: 'top-end',
            icon: 'info', title: 'Spesifikasi terisi otomatis. Silakan isi Modal & Harga Jual baru.',
            showConfirmButton: false, timer: 4000
        });
        
    } catch (err) {
        console.error('Error fetching IMEI data:', err);
    }
}

// Fungsi untuk memilih opsi pada dropdown, mengabaikan jika tidak ada
function pilihDropdown(elementId, value) {
    const select = document.getElementById(elementId);
    // Hapus disabled
    select.disabled = false;
    // Cari opsi
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].value.toUpperCase() === value.toUpperCase()) {
            select.selectedIndex = i;
            return Promise.resolve();
        }
    }
    // Jika tidak ada, buat opsi baru
    const option = document.createElement('option');
    option.value = value;
    option.textContent = value;
    select.appendChild(option);
    select.value = value;
    return Promise.resolve();
}

// Listener IMEI tetap
let imeiTypingTimer;
document.getElementById('imei_input').addEventListener('input', function() {
    clearTimeout(imeiTypingTimer);
    const imeiVal = this.value.trim();
    imeiTypingTimer = setTimeout(() => {
        cekDataIMEIHistoris(imeiVal);
    }, 600);
});

// ==========================================
// EVENT LISTENER DROPDOWN MANUAL
// ==========================================
document.getElementById('merk').addEventListener('change', async function() {
    const selectedOption = this.options[this.selectedIndex];
    const merkId = selectedOption.dataset.id;
    
    resetDropdown('nama_barang', '-- Pilih Nama Barang --', true);
    resetDropdown('kondisi', '-- Pilih Nama Barang Dahulu --', true);
    resetDropdown('internal', '-- Pilih Kondisi Dahulu --', true);
    resetDropdown('warna', '-- Pilih Storage Dahulu --', true);
    selectedBarangId = null;
    
    if (!merkId) return;
    
    await fetchDropdown(
        `beli_customer.php?action=get_nama_barang&merk_id=${merkId}`,
        'nama_barang',
        '-- Pilih Nama Barang --',
        'nama_barang',
        'nama_barang',
        'id'
    );
});

document.getElementById('nama_barang').addEventListener('change', async function() {
    const selectedOption = this.options[this.selectedIndex];
    selectedBarangId = selectedOption.dataset.id;
    
    resetDropdown('kondisi', '-- Pilih Kondisi / Tipe --', true);
    resetDropdown('internal', '-- Pilih Kondisi Dahulu --', true);
    resetDropdown('warna', '-- Pilih Storage Dahulu --', true);
    
    if (!selectedBarangId) return;
    
    await fetchDropdown(
        `beli_customer.php?action=get_kondisi&barang_id=${selectedBarangId}`,
        'kondisi',
        '-- Pilih Kondisi / Tipe --',
        'kondisi',
        'kondisi'
    );
});

document.getElementById('kondisi').addEventListener('change', async function() {
    const kondisi = this.value;
    resetDropdown('internal', '-- Pilih Storage --', true);
    resetDropdown('warna', '-- Pilih Storage Dahulu --', true);
    
    if (!kondisi || !selectedBarangId) return;
    
    await fetchDropdown(
        `beli_customer.php?action=get_internal&barang_id=${selectedBarangId}&kondisi=${encodeURIComponent(kondisi)}`,
        'internal',
        '-- Pilih Storage --',
        'internal_storage',
        'internal_storage'
    );
});

document.getElementById('internal').addEventListener('change', async function() {
    const internal = this.value;
    const kondisi = document.getElementById('kondisi').value;
    resetDropdown('warna', '-- Pilih Warna --', true);
    
    if (!internal || !kondisi || !selectedBarangId) return;
    
    await fetchDropdown(
        `beli_customer.php?action=get_warna&barang_id=${selectedBarangId}&kondisi=${encodeURIComponent(kondisi)}&internal_storage=${encodeURIComponent(internal)}`,
        'warna',
        '-- Pilih Warna --',
        'warna',
        'warna'
    );
});

function resetDropdown(id, placeholder, disabled = true) {
    const select = document.getElementById(id);
    select.innerHTML = `<option value="">${placeholder}</option>`;
    select.disabled = disabled;
}

// ==========================================
// FORMAT RUPIAH & HITUNG ESTIMASI (TANPA AUTO-ISI)
// ==========================================
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

function hitungEstimasi() {
    const hargaBeli = parseRibuan(document.getElementById('harga_beli').value);
    const hargaEst = parseRibuan(document.getElementById('harga_jual_est').value);
    const preview = document.getElementById('preview-margin');
    const summary = document.getElementById('summary-total');
    
    summary.textContent = 'Rp ' + hargaBeli.toLocaleString('id-ID');
    
    if (hargaBeli > 0 && hargaEst > 0) {
        preview.style.display = 'block';
        const margin = hargaEst - hargaBeli;
        const marginPct = ((margin / hargaBeli) * 100).toFixed(1);
        
        document.getElementById('preview-margin-val').textContent = 'Rp ' + margin.toLocaleString('id-ID');
        document.getElementById('preview-margin-pct').textContent = marginPct + '% dari harga beli';
        document.getElementById('preview-margin-val').style.color = margin < 0 ? 'var(--danger-red)' : 'var(--success-green)';
    } else {
        preview.style.display = 'none';
    }
}

// ==========================================
// LOGIKA PAYMENT & KONFIRMASI (SWEETALERT) - UI RAPIH
// ==========================================
const paymentMethods = [
    { id: 'Cash', label: 'Cash', icon: 'fa-money-bill-wave', color: '#10B981' },
    { id: 'Transfer', label: 'Transfer', icon: 'fa-university', color: '#3B82F6' },
    { id: 'E-Wallet', label: 'E-Wallet', icon: 'fa-wallet', color: '#F59E0B' }
];

let selectedPaymentId = null;
let selectedPaymentLabel = null;

function buildPaymentHTML() {
    return paymentMethods.map(m => `
        <button type="button" class="pay-btn" id="pbtn-${m.id}" onclick="pilihPayment('${m.id}','${m.label}')">
            <div class="pay-icon-box" style="background:${m.color};"><i class="fas ${m.icon}"></i></div>
            <div style="flex:1; text-align:left;">
                <div class="pay-label">${m.label}</div>
                <div class="pay-sublabel">${m.id === 'Cash' ? 'Tunai' : m.id === 'Transfer' ? 'BCA / Mandiri / dll' : 'OVO / Dana / GoPay'}</div>
            </div>
            <i class="fas fa-chevron-right" style="color:#CBD5E1;font-size:0.8rem;"></i>
        </button>
    `).join('');
}

function pilihPayment(id, label) {
    selectedPaymentId = id;
    selectedPaymentLabel = label;
    document.querySelectorAll('.pay-btn').forEach(b => b.classList.remove('selected'));
    document.getElementById('pbtn-' + id).classList.add('selected');
    Swal.getConfirmButton().disabled = false;
}

async function konfirmasiBeli() {
    const form = document.getElementById('formBeli');
    const imei = form.imei.value.trim();
    const merk = form.merk.value;
    const namaBarang = form.nama_barang.value;
    const kondisi = form.kondisi.value;
    const internal = form.internal.value;
    const warna = form.warna.value;
    const hargaBeli = parseRibuan(form.harga_beli.value);
    const hargaEst = parseRibuan(form.harga_jual_estimasi.value);
    const margin = hargaEst - hargaBeli;
    
    const keteranganManual = form.keterangan.value.trim();
    
    if (!imei || imei.length < 5) return Swal.fire({icon:'error', title:'Error', text:'IMEI tidak valid!'});
    if (!merk || !namaBarang) return Swal.fire({icon:'error', title:'Error', text:'Merek dan nama barang wajib diisi!'});
    if (!kondisi || !internal || !warna) return Swal.fire({icon:'error', title:'Error', text:'Kondisi / Tipe, storage, dan warna wajib diisi!'});
    if (hargaBeli <= 0) return Swal.fire({icon:'error', title:'Error', text:'Harga beli harus lebih dari 0!'});
    if (hargaEst <= 0) return Swal.fire({icon:'error', title:'Error', text:'Estimasi harga jual kembali harus diisi!'});

    selectedPaymentId = null;
    selectedPaymentLabel = null;

    // STEP 1: PAYMENT
    const { value: paymentConfirmed } = await Swal.fire({
        title: '<span style="font-size:1.1rem;font-weight:800;">Metode Pembayaran ke Customer</span>',
        html: `
            <div style="text-align:left;">
                <p style="color:#64748B;font-size:0.8rem;margin-bottom:12px;">Pilih cara Anda membayar customer</p>
                <div style="display:flex;flex-direction:column;gap:10px;">${buildPaymentHTML()}</div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Lanjut',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#8B5CF6',
        didOpen: () => { Swal.getConfirmButton().disabled = true; },
        preConfirm: () => {
            if (!selectedPaymentId) { Swal.showValidationMessage('Pilih metode pembayaran'); return false; }
            return true;
        }
    });

    if (!paymentConfirmed) return;

    // STEP 2: DATA CUSTOMER & KONFIRMASI AKHIR
    const { value: customerData } = await Swal.fire({
        title: '<span style="font-size:1.1rem;font-weight:800;">Konfirmasi & Data Customer</span>',
        html: `
            <div class="konfirmasi-section">
                <div class="konfirmasi-highlight">
                    <div style="font-weight:800;font-size:0.9rem;margin-bottom:10px;color:var(--purple-asset);">📱 ${merk} ${namaBarang}</div>
                    <div class="konfirmasi-row">
                        <span style="color:#64748B;">Kondisi/Tipe</span>
                        <strong>${kondisi}</strong>
                    </div>
                    <div class="konfirmasi-row">
                        <span style="color:#64748B;">Storage</span>
                        <strong>${internal}</strong>
                    </div>
                    <div class="konfirmasi-row">
                        <span style="color:#64748B;">Warna</span>
                        <strong>${warna}</strong>
                    </div>
                    <div class="konfirmasi-row" style="margin-bottom:4px;">
                        <span style="color:#64748B;">IMEI</span>
                        <span style="font-family:monospace;font-weight:700;">${imei}</span>
                    </div>
                    ${keteranganManual ? `<div style="margin-top:8px;font-size:0.75rem;color:var(--danger-red);">Catatan: <strong>${keteranganManual}</strong></div>` : ''}
                </div>
                
                <div class="konfirmasi-highlight" style="background:rgba(16,185,129,0.07);border-color:rgba(16,185,129,0.2);">
                    <div class="konfirmasi-row">
                        <span style="font-weight:600;">Harga Beli (Modal)</span>
                        <strong style="color:var(--danger-red);">Rp ${hargaBeli.toLocaleString('id-ID')}</strong>
                    </div>
                    <div class="konfirmasi-row">
                        <span style="font-weight:600;">Estimasi Jual</span>
                        <strong style="color:var(--success-green);">Rp ${hargaEst.toLocaleString('id-ID')}</strong>
                    </div>
                    <div class="konfirmasi-row" style="margin-top:8px;padding-top:8px;border-top:1px dashed #D1D5DB;">
                        <span style="font-weight:800;">Margin</span>
                        <strong style="color:${margin >=0 ? 'var(--success-green)' : 'var(--danger-red)'}; font-size:1.1rem;">Rp ${margin.toLocaleString('id-ID')} (${((margin/hargaBeli)*100).toFixed(1)}%)</strong>
                    </div>
                </div>
                
                <div style="background:#EEF4FA;border:1px solid #1E4A7A;border-radius:8px;padding:10px 12px;margin:16px 0;font-size:0.85rem;font-weight:600;color:#1E4A7A;">
                    <i class="fas fa-check-circle"></i> Metode Bayar: ${selectedPaymentLabel}
                </div>
                
                <label class="konfirmasi-section-label">Nama Customer *</label>
                <input id="swal-nama" type="text" class="swal2-input" placeholder="Nama lengkap" style="margin-top:4px; width:100%; box-sizing:border-box;">
                
                <label class="konfirmasi-section-label" style="margin-top:12px;">No. HP / WhatsApp</label>
                <input id="swal-nohp" type="text" class="swal2-input" placeholder="0812..." style="margin-top:4px; width:100%; box-sizing:border-box;">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Beli & Masuk Stok',
        cancelButtonText: 'Kembali',
        confirmButtonColor: '#8B5CF6',
        preConfirm: () => {
            const nama = document.getElementById('swal-nama').value.trim();
            if (!nama) { Swal.showValidationMessage('Nama customer wajib diisi'); return false; }
            return {
                nama: nama,
                nohp: document.getElementById('swal-nohp').value.trim() || '-'
            };
        }
    });

    if (customerData) {
        document.getElementById('hidden_metode_bayar').value = selectedPaymentLabel;
        document.getElementById('hidden_nama_customer').value = customerData.nama;
        document.getElementById('hidden_no_hp').value = customerData.nohp;
        document.getElementById('harga_jual_est').value = hargaEst.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        
        document.getElementById('loadingOverlay').style.display = 'flex';
        setTimeout(() => { document.getElementById('formBeli').submit(); }, 300);
    }
}

// ==========================================
// LOGIKA SCANNER (KAMERA) – TIDAK BERUBAH
// ==========================================
let scannerMode = 'quagga';
let quaggaInitialized = false;
let currentFacing = 'environment';
let isTorchOn = false;
let track = null;
let nativeStream = null;
let scanCooldown = false;
let lastScan = "";
const beep = document.getElementById('beep');

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
        try { torchBtn.style.display = track.getCapabilities().torch ? 'flex' : 'none'; } catch(e) { torchBtn.style.display = 'none'; }
    } else { torchBtn.style.display = 'none'; }
}

function toggleTorch() {
    if (!track) return;
    isTorchOn = !isTorchOn;
    track.applyConstraints({ advanced: [{ torch: isTorchOn }] }).catch(() => {});
    const icon = document.querySelector('#torchBtn i');
    if (isTorchOn) icon.classList.replace('fa-bolt', 'fa-bolt-slash');
    else icon.classList.replace('fa-bolt-slash', 'fa-bolt');
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
            if (barcodes.length > 0 && !scanCooldown) {
                const code = barcodes[0].rawValue;
                if (code !== lastScan) handleSuccessfulScan(code);
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
        inputStream: { name: "Live", type: "LiveStream", target: document.querySelector('#reader'), constraints: { facingMode: facing } },
        decoder: { readers: ["code_128_reader","ean_reader","ean_8_reader","code_39_reader","upc_reader"] },
        locator: { patchSize: "medium", halfSample: false }, numOfWorkers: navigator.hardwareConcurrency || 4, frequency: 10, locate: true
    }, (err) => {
        if (err) { Swal.fire({icon:'error', title:'Kamera gagal diakses'}); closeScanner(); return; }
        quaggaInitialized = true; Quagga.start();
    });
    Quagga.offDetected();
    Quagga.onDetected((result) => {
        const code = result.codeResult.code;
        if (!scanCooldown && code !== lastScan) handleSuccessfulScan(code);
    });
}

function handleSuccessfulScan(code) {
    scanCooldown = true; lastScan = code; beep.play().catch(() => {});
    setTimeout(() => {
        document.getElementById('imei_input').value = code;
        cekDataIMEIHistoris(code);
        closeScanner();
        setTimeout(() => { scanCooldown = false; lastScan = ""; }, 2000);
    }, 400);
}

async function startScanner(facing) {
    if (scannerMode === 'native' && nativeStream) stopNativeScanner();
    else if (scannerMode === 'quagga' && quaggaInitialized) { Quagga.stop(); Quagga.offDetected(); quaggaInitialized = false; }
    if (canUseBarcodeDetector()) {
        try { await startNativeScanner(facing); scannerMode = 'native'; }
        catch (e) { startQuagga(facing); scannerMode = 'quagga'; }
    } else { startQuagga(facing); scannerMode = 'quagga'; }
    await updateTorchAvailability();
}

function openScanner() {
    document.getElementById('scanner-container').style.display = 'block';
    scanCooldown = false; lastScan = ""; isTorchOn = false;
    startScanner(currentFacing);
}

function closeScanner() {
    document.getElementById('scanner-container').style.display = 'none';
    if (track && isTorchOn) track.applyConstraints({ advanced: [{ torch: false }] }).catch(() => {});
    if (scannerMode === 'native') stopNativeScanner();
    else if (scannerMode === 'quagga' && quaggaInitialized) { Quagga.stop(); Quagga.offDetected(); quaggaInitialized = false; }
    track = null; isTorchOn = false;
}

function switchCamera() {
    currentFacing = (currentFacing === 'environment') ? 'user' : 'environment';
    if (track && isTorchOn) { track.applyConstraints({ advanced: [{ torch: false }] }).catch(() => {}); isTorchOn = false; }
    startScanner(currentFacing);
}

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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>