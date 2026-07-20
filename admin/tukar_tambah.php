<?php
session_start();
require_once '../api/auth_helper.php';
restrictTo(['admin']);
include '../connection.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// =========================================================================
// CSRF TOKEN
// =========================================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =========================================================================
// AMBIL LIST MERK UNTUK DROPDOWN HP LAMA
// =========================================================================
$merkList = [];
try {
    $qMerk = $koneksi->query("SELECT id, nama_merk FROM master_merk WHERE status = 'aktif' ORDER BY nama_merk ASC");
    while ($r = $qMerk->fetch_assoc()) $merkList[] = $r;
} catch (Exception $e) {
    error_log("Error load merk: " . $e->getMessage());
}

// =========================================================================
// ENDPOINT AJAX: CARI IMEI HP BARU
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'cari_imei_ajax') {
    header('Content-Type: application/json');
    $imei_cari = trim($_GET['imei'] ?? '');
    
    if (!preg_match('/^[0-9]{15}$/', $imei_cari)) {
        echo json_encode(['success' => false, 'message' => 'Format IMEI tidak valid']);
        exit();
    }
    
    try {
        $stmt = $koneksi->prepare("SELECT nama, tipe, warna, internal, harga_jual, modal FROM barang WHERE imei = ? AND status = 'READY' LIMIT 1");
        $stmt->bind_param("s", $imei_cari);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            echo json_encode([
                'success'    => true,
                'nama_tipe'  => $data['nama'] . ' ' . $data['tipe'] . ' (' . $data['warna'] . ' / ' . $data['internal'] . 'GB)',
                'harga_jual' => (int)($data['harga_jual'] ?? 0),
                'modal'      => (int)($data['modal'] ?? 0)
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'IMEI tidak terdaftar atau unit sudah SOLD'
            ]);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("AJAX Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem']);
    }
    exit();
}

// =========================================================================
// PROSES SIMPAN TRANSAKSI TUKAR TAMBAH
// =========================================================================
$pesan = "";
$status_pesan = "";

if (isset($_POST['proses_tukar_tambah'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $pesan = "Token keamanan tidak valid. Silakan refresh halaman.";
        $status_pesan = "error";
    } else {
        try {
            $imei_baru         = trim($_POST['imei_baru'] ?? '');
            $harga_jual_normal = (int)($_POST['harga_jual_normal'] ?? 0);
            $metode_pembayaran = trim($_POST['metode_pembayaran'] ?? '');
            $user_petugas      = $_SESSION['username'] ?? 'admin';
            
            $imei_lama         = trim($_POST['imei_lama'] ?? '');
            $nama_hp_lama      = trim($_POST['nama_hp_lama'] ?? '');
            $tipe_lama         = trim($_POST['tipe_lama'] ?? '');
            $warna_lama        = trim($_POST['warna_lama'] ?? '');
            $internal_lama     = trim($_POST['internal_lama'] ?? '');
            $kondisi_lama      = trim($_POST['kondisi_lama'] ?? 'Normal');
            $nilai_tukar_lama  = (int)($_POST['nilai_tukar_lama'] ?? 0);
            $harga_jual_lama   = (int)($_POST['harga_jual_lama'] ?? 0);
            
            $dp_tambahan       = (int)($_POST['dp_tambahan'] ?? 0);
            
            $nama_customer     = trim($_POST['nama_customer'] ?? 'TUKAR TAMBAH');
            $no_hp_customer    = trim($_POST['no_hp'] ?? '-');
            $catatan           = trim($_POST['catatan'] ?? '');

            // ====== VALIDASI DASAR ======
            if (!preg_match('/^[0-9]{15}$/', $imei_baru)) throw new Exception("IMEI HP Baru tidak valid");
            if (!preg_match('/^[0-9]{15}$/', $imei_lama)) throw new Exception("IMEI HP Lama tidak valid");
            if ($imei_baru === $imei_lama) throw new Exception("IMEI HP Baru dan HP Lama tidak boleh sama");
            if ($harga_jual_normal <= 0) throw new Exception("Harga jual HP baru tidak valid");
            if ($nilai_tukar_lama < 0 || $harga_jual_lama <= 0) throw new Exception("Nilai tukar atau harga jual HP lama tidak valid");
            if (empty($metode_pembayaran)) throw new Exception("Metode pembayaran wajib dipilih");

            $isCicilan = in_array($metode_pembayaran, ['Kredivo', 'Akulaku', 'Shopee PayLater']);
            $status_pencairan = $isCicilan ? 'Pending' : 'Cair';

            // ====== LOGIKA DOWNGRADE & PEMBAYARAN ======
            $kembalian = 0;
            $dp_aktual = $dp_tambahan;
            $isDowngrade = $nilai_tukar_lama > $harga_jual_normal;

            if ($isDowngrade) {
                if ($isCicilan) {
                    throw new Exception("Downgrade (Nilai tukar > Harga HP baru) tidak dapat menggunakan metode Cicilan (PayLater). Silakan pilih Cash atau Transfer.");
                }
                $kembalian = $nilai_tukar_lama - $harga_jual_normal;
                $sisa_pembayaran = -$kembalian; 
                $dp_aktual = -$kembalian;       
                $dp_tambahan = 0;
            } else {
                $sisa_pembayaran = $harga_jual_normal - $nilai_tukar_lama - $dp_tambahan;
                if ($sisa_pembayaran < 0) {
                    throw new Exception("DP Tambahan terlalu besar, melebihi sisa pembayaran!");
                }
            }

            // ====== MULAI TRANSAKSI ======
            $koneksi->begin_transaction();

            // TAHAP 1: LOCK & CEK HP BARU
            $stmt_cek_baru = $koneksi->prepare("SELECT id, modal, harga_jual FROM barang WHERE imei = ? AND status = 'READY' FOR UPDATE");
            $stmt_cek_baru->bind_param("s", $imei_baru);
            $stmt_cek_baru->execute();
            $result_baru = $stmt_cek_baru->get_result();

            if ($result_baru->num_rows === 0) {
                $stmt_cek_baru->close();
                throw new Exception("IMEI HP Baru tidak ditemukan di stok atau statusnya TIDAK READY!");
            }

            $data_hp_baru = $result_baru->fetch_assoc();
            $modal_hp_baru = (int)$data_hp_baru['modal'];
            $stmt_cek_baru->close();

            // TAHAP 2: CEK DUPLIKASI IMEI HP LAMA
            $stmt_cek_lama = $koneksi->prepare("SELECT id FROM barang WHERE imei = ?");
            $stmt_cek_lama->bind_param("s", $imei_lama);
            $stmt_cek_lama->execute();
            $result_cek_lama = $stmt_cek_lama->get_result();

            if ($result_cek_lama->num_rows > 0) {
                $stmt_cek_lama->close();
                throw new Exception("IMEI HP Lama sudah terdaftar di database!");
            }
            $stmt_cek_lama->close();

            // TAHAP 3: INSERT HP LAMA KE STOK (STOCK IN)
            $nama_lengkap_hp_lama = $nama_hp_lama . " " . $tipe_lama;
            $nama_lengkap_insert = $nama_lengkap_hp_lama; 
            $tipe_insert = $kondisi_lama;
            
            $status_ready = 'READY';
            $keterangan_lama = 'Hasil Tukar Tambah HP Baru';
            $supplier_lama = 'TUKAR TAMBAH';

            $stmt_insert_lama = $koneksi->prepare("
                INSERT INTO barang (nama, tipe, imei, warna, internal, supplier, keterangan, modal, harga_jual, tanggal, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            
            $stmt_insert_lama->bind_param(
                "sssssssiis",
                $nama_lengkap_insert, $tipe_insert, $imei_lama, $warna_lama, $internal_lama,
                $supplier_lama, $keterangan_lama, $nilai_tukar_lama, $harga_jual_lama, $status_ready
            );
            
            if (!$stmt_insert_lama->execute()) throw new Exception("Gagal menyimpan HP lama: " . $stmt_insert_lama->error);
            $stmt_insert_lama->close();

            // TAHAP 4: INSERT LOG STOCK OUT
            $keterangan_stock_out = "Tukar Tambah | Metode: " . $metode_pembayaran . " | Cust: " . $nama_customer;
            $status_out = 'OUT';
            
            $stmt_stock_out = $koneksi->prepare("INSERT INTO stock_out (imei, keterangan, user, tanggal, status, tanggal_return) VALUES (?, ?, ?, NOW(), ?, NULL)");
            $stmt_stock_out->bind_param("ssss", $imei_baru, $keterangan_stock_out, $user_petugas, $status_out);
            if (!$stmt_stock_out->execute()) throw new Exception("Gagal menyimpan log stock out: " . $stmt_stock_out->error);
            $stmt_stock_out->close();

            // TAHAP 5: INSERT LOG PENJUALAN
            $metode_tt = $metode_pembayaran . " (TT)";
            $stmt_penjualan = $koneksi->prepare("INSERT INTO penjualan (imei, harga_jual, dp, nama_customer, no_hp, metode_pembayaran, modal, tanggal, status_pencairan, jenis_transaksi) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'HP')");
            
            $stmt_penjualan->bind_param("siisssis", $imei_baru, $harga_jual_normal, $dp_aktual, $nama_customer, $no_hp_customer, $metode_tt, $modal_hp_baru, $status_pencairan);
            if (!$stmt_penjualan->execute()) throw new Exception("Gagal mencatat penjualan: " . $stmt_penjualan->error);
            $stmt_penjualan->close();

            // TAHAP 6: INSERT LOG TUKAR TAMBAH
            $laba_total = $harga_jual_normal - $modal_hp_baru - $kembalian;

            $stmt_tt = $koneksi->prepare("INSERT INTO tukar_tambah (tanggal, imei_baru, imei_lama, nama_lama, kondisi_lama, harga_lama, harga_baru, selisih, laba, user, catatan) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt_tt->bind_param(
                "ssssiiiiss",
                $imei_baru, $imei_lama, $nama_lengkap_hp_lama, $kondisi_lama,
                $nilai_tukar_lama, $harga_jual_normal, $sisa_pembayaran, $laba_total, $user_petugas, $catatan
            );
            if (!$stmt_tt->execute()) throw new Exception("Gagal menyimpan riwayat TT: " . $stmt_tt->error);
            $stmt_tt->close();

            // TAHAP 7: UPDATE STATUS HP BARU
            $status_sold = 'NO READY'; 
            $stmt_update_baru = $koneksi->prepare("UPDATE barang SET status = ? WHERE imei = ?");
            $stmt_update_baru->bind_param("ss", $status_sold, $imei_baru);
            if (!$stmt_update_baru->execute()) throw new Exception("Gagal merubah status HP baru: " . $stmt_update_baru->error);
            $stmt_update_baru->close();

            $koneksi->commit();

            $pesan = "Transaksi Tukar Tambah Sukses! " . ($isDowngrade ? "Terdeteksi Downgrade, Kembalian: Rp " . number_format($kembalian, 0, ',', '.') : "Seluruh data stok, penjualan, dan log berhasil disinkronkan.");
            $status_pesan = "success";

        } catch (Exception $e) {
            if ($koneksi->connect_errno === 0) {
                $koneksi->rollback();
            }
            error_log("TT Error: " . $e->getMessage());
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
    <title>Tukar Tambah · Happy Cellular</title>

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
            display: flex; align-items: center; gap: 6px; border: none; cursor: pointer; transition: all 0.2s;
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

        .main-wrapper { max-width: 1200px; margin: 0 auto; padding: 1rem 1.2rem; }

        .scan-card {
            background: var(--surface-card); border-radius: 20px; padding: 1.5rem;
            box-shadow: 0 4px 18px rgba(0,0,0,0.02); position: relative; border: 1px solid rgba(0,0,0,0.01); margin-bottom: 1rem;
        }
        .card-icon { width: 48px; height: 48px; background: var(--primary-dongker-soft); border-radius: 14px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; font-size: 1.4rem; color: var(--primary-dongker); }
        .scan-card h2 { font-weight: 800; color: var(--text-main); font-size: 1.1rem; margin-bottom: 4px; }
        .input-wrapper { background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 12px; padding: 4px 12px; margin: 12px 0; display: flex; align-items: center; transition: 0.3s; }
        .input-wrapper:focus-within { border-color: var(--primary-dongker); background: white; box-shadow: 0 0 0 3px var(--primary-dongker-soft); }
        .input-wrapper input { background: transparent; border: none; width: 100%; padding: 10px 8px; font-size: 1rem; font-weight: 700; color: var(--text-main); outline: none; letter-spacing: 0.5px; }
        .btn-clear { background: none; border: none; color: var(--text-muted); font-size: 1rem; padding: 6px; cursor: pointer; }
        .btn-clear:hover { color: var(--danger-red); }

        .card-custom { background: var(--surface-card); border-radius: 20px; padding: 1.5rem; box-shadow: 0 4px 18px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.01); margin-bottom: 1rem; }
        .card-title-custom { font-size: 0.85rem; font-weight: 800; color: var(--primary-dongker); letter-spacing:0.5px; text-transform: uppercase; display: flex; align-items:center; gap:8px; margin-bottom:1rem; border-bottom:1px solid #F1F5F9; padding-bottom:10px; }
        .form-control, .form-select { border-radius:10px; border:1px solid #E2E8F0; padding:0.75rem; font-weight:600; font-size:0.85rem; color:var(--text-main); }
        .form-control:focus, .form-select:focus { border-color:var(--primary-dongker); box-shadow:0 0 0 3px var(--primary-dongker-soft); outline:none; }

        .badge-status-imei { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 10px; font-size: 0.8rem; font-weight: 600; margin-top: 8px; transition: all 0.3s ease; }
        .status-ready { background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .status-error { background-color: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }

        .summary-bar { background: var(--surface-card); border-radius: 16px; padding: 1rem 1.2rem; box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04); border: 1px solid rgba(0, 0, 0, 0.01); display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; }
        .summary-label { font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; }
        .summary-val { font-size: 1.2rem; font-weight: 800; color: var(--primary-dongker); margin: 0; letter-spacing: -0.5px; }
        .btn-submit-trx { background: var(--primary-dongker); color: #fff; font-weight: 700; font-size: 0.85rem; padding: 10px 18px; border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(30, 74, 122, 0.2); transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
        .btn-submit-trx:active { transform: scale(0.97); }

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
        .scan-laser { position: absolute; width: 100%; height: 2px; background: var(--danger-red); top: 0; box-shadow: 0 0 12px var(--danger-red); animation: laser 2s ease-in-out infinite; }
        @keyframes laser { 0%, 100% { top: 0; opacity: 0; } 10%, 90% { opacity: 1; } 50% { top: 100%; } }
        .scanner-helper-text { position: absolute; top: calc(50% + 120px); left: 50%; transform: translateX(-50%); color: white; font-weight: 500; font-size: 0.9rem; z-index: 15; text-shadow: 0 2px 4px rgba(0,0,0,0.5); text-align: center; width: 100%; }

        .desktop-sidebar { display: none; }

        .pay-btn { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 12px; cursor: pointer; transition: all 0.2s ease; text-align: left; width: 100%; font-family: 'Plus Jakarta Sans', sans-serif; }
        .pay-btn:hover:not(:disabled) { border-color: #94A3B8; background: #F1F5F9; transform: translateY(-1px); }
        .pay-btn.selected { border-color: var(--primary-dongker); background: var(--primary-dongker-soft); box-shadow: 0 0 0 3px rgba(30, 74, 122, 0.15); }
        .pay-btn:disabled { opacity: 0.6; cursor: not-allowed; background: #F8FAFC; }
        .pay-icon-box { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: white; flex-shrink: 0; }
        .pay-label { font-weight: 700; font-size: 0.85rem; color: var(--text-main); line-height: 1.2; }
        .pay-sublabel { font-size: 0.7rem; color: var(--text-muted); font-weight: 500; margin-top: 2px; }

        @media (max-width: 575.98px) {
            .main-wrapper { padding: 0.75rem; }
            .scan-card, .card-custom { padding: 1.2rem; }
            .summary-bar { flex-direction: column; gap: 12px; text-align: center; }
            .btn-submit-trx { width: 100%; justify-content: center; }
        }

        @media (min-width: 992px) {
            body { padding-bottom: 0; display: flex; background: #F8FAFC; }
            .bottom-nav { display: none !important; }
            .popup-overlay, .more-menu-popup { display: none !important; }
            .navbar-top { position: static; box-shadow: none; background: transparent; padding: 1.5rem 2rem 0; width: 100%; }
            .navbar-top .logo-area { display: none; }
            .desktop-sidebar {
                display: flex; flex-direction: column; width: var(--sidebar-width); background: var(--surface-card);
                height: 100vh; position: sticky; top: 0; border-right: 1px solid #E2E8F0; padding: 1.5rem; z-index: 1010;
            }
            .sidebar-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #F1F5F9; }
            .sidebar-menu { display: flex; flex-direction: column; gap: 5px; flex-grow: 1; }
            .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 0.85rem; border-radius: 12px; transition: all 0.2s; }
            .sidebar-link i { font-size: 1.1rem; width: 20px; text-align: center; }
            .sidebar-link:hover, .sidebar-link.active { background: var(--primary-dongker-soft); color: var(--primary-dongker); }
            .sidebar-divider { height: 1px; background: #F1F5F9; margin: 10px 0; }
            .content-wrapper { flex-grow: 1; display: flex; flex-direction: column; min-width: 0; }
            .main-wrapper { max-width: 1200px; padding: 1.5rem 2rem; }
        }

        #loadingOverlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(4px); z-index: 9999; display: none; align-items: center; justify-content: center; color: white; flex-direction: column; }
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
        <a href="stock_in.php" class="sidebar-link"><i class="fas fa-arrow-circle-down" style="color:#10B981;"></i> Stock In</a>
        <a href="admin_stock_out.php" class="sidebar-link"><i class="fas fa-arrow-circle-up" style="color:#EF4444;"></i> Stock Out</a>
        <a href="return.php" class="sidebar-link"><i class="fas fa-rotate-left" style="color:#F59E0B;"></i> Retur</a>
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
                <li><hr class="dropdown-divider" style="border-color:#EDF2F7;"></li>
                <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
            </ul>
        </div>
    </div>

    <div class="main-wrapper">

        <?php if(!empty($pesan)): ?>
            <script>
                Swal.fire({
                    icon: '<?= $status_pesan ?>',
                    title: '<?= $status_pesan == "success" ? "Berhasil!" : "Gagal Transaksi!" ?>',
                    text: '<?= addslashes($pesan) ?>',
                    confirmButtonColor: '#1E4A7A'
                }).then(() => {
                    <?php if($status_pesan == "success"): ?>
                        window.location.href = 'riwayat_tukar_tambah.php';
                    <?php endif; ?>
                });
            </script>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold m-0" style="font-size: 1.1rem;">Tukar Tambah</h3>
            <a href="riwayat_tukar_tambah.php" class="btn btn-sm" style="background:var(--primary-dongker-soft); color:var(--primary-dongker); font-weight:700; border-radius:10px; font-size: 0.8rem;"><i class="fas fa-history"></i> Riwayat</a>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="scan-card">
                    <div class="card-icon"><i class="fas fa-barcode"></i></div>
                    <h2>Scan Unit Toko</h2>
                    <p class="text-muted" style="font-size:0.8rem;">HP yang dijual ke Pelanggan</p>
                    <div class="input-wrapper">
                        <input type="text" id="imei_baru_view" placeholder="Masukkan IMEI / Barcode" maxlength="15" autofocus>
                        <button class="btn-clear" onclick="document.getElementById('imei_baru_view').value=''; document.getElementById('imei_baru_view').focus(); hitungSelisih();"><i class="fas fa-times-circle"></i></button>
                    </div>
                    <div id="container_autofill" class="badge-status-imei d-none">
                        <i id="icon_status" class="fas"></i> 
                        <span id="teks_status_autofill">-</span>
                    </div>
                    <div class="mt-3">
                        <label style="font-size:0.75rem; font-weight:700; color:var(--text-muted); display:block; text-align:left; margin-bottom:4px;">Harga Jual (Rp)</label>
                        <input type="text" id="harga_baru_view" class="form-control fw-bold text-primary text-center" oninput="formatRibuan(this); hitungSelisih()" placeholder="Terisi otomatis" style="height:44px;">
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card-custom">
                    <div class="card-title-custom"><i class="fas fa-mobile-alt"></i> Data HP Lama (Pelanggan)</div>
                    <div class="row g-2 mb-2">
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted mb-1">Merek *</label>
                            <select id="merk" class="form-select">
                                <option value="">-- Pilih Merek --</option>
                                <?php foreach ($merkList as $m): ?>
                                <option value="<?= $m['id'] ?>" data-label="<?= htmlspecialchars($m['nama_merk']) ?>"><?= htmlspecialchars($m['nama_merk']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted mb-1">Nama Barang *</label>
                            <select id="namaBarang" class="form-select" disabled>
                                <option value="">-- Pilih Nama Barang --</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted mb-1">Kondisi *</label>
                            <select id="kondisi" class="form-select" disabled>
                                <option value="">-- Pilih --</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted mb-1">Storage *</label>
                            <select id="internal" class="form-select" disabled>
                                <option value="">-- Pilih --</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted mb-1">Warna *</label>
                            <select id="warna" class="form-select" disabled>
                                <option value="">-- Pilih --</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted mb-1">IMEI HP Lama *</label>
                            <input type="text" id="imei_lama_view" class="form-control" placeholder="15 digit" maxlength="15">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted mb-1">Nilai Tukar (Rp) *</label>
                            <input type="text" id="harga_lama_view" class="form-control fw-bold text-success" placeholder="0" oninput="formatRibuan(this); hitungSelisih()">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted mb-1">Est. Jual (Rp) *</label>
                            <input type="text" id="harga_jual_lama_view" class="form-control fw-bold" placeholder="0" oninput="formatRibuan(this);">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="summary-bar">
            <div>
                <span class="summary-label" id="sisa_bayar_label">Sisa Wajib Dibayar:</span>
                <h3 class="summary-val" id="sisa_bayar_teks">Rp 0</h3>
            </div>
            <button class="btn-submit-trx" onclick="prosesTT()">
                <i class="fas fa-check-circle"></i> Proses
            </button>
        </div>
    </div>
</div>

<!-- HIDDEN FORM -->
<form id="formTT" action="" method="POST" style="display:none;">
    <input type="hidden" name="proses_tukar_tambah" value="1">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="imei_baru" id="post_imei_baru">
    <input type="hidden" name="harga_jual_normal" id="post_harga_jual_normal">
    <input type="hidden" name="imei_lama" id="post_imei_lama">
    <input type="hidden" name="nama_hp_lama" id="post_nama_hp_lama">
    <input type="hidden" name="tipe_lama" id="post_tipe_lama">
    <input type="hidden" name="warna_lama" id="post_warna_lama">
    <input type="hidden" name="internal_lama" id="post_internal_lama">
    <input type="hidden" name="kondisi_lama" id="post_kondisi_lama">
    <input type="hidden" name="nilai_tukar_lama" id="post_nilai_tukar_lama">
    <input type="hidden" name="harga_jual_lama" id="post_harga_jual_lama">
    <input type="hidden" name="metode_pembayaran" id="post_metode_pembayaran">
    <input type="hidden" name="nama_customer" id="post_nama_customer">
    <input type="hidden" name="no_hp" id="post_no_hp">
    <input type="hidden" name="catatan" id="post_catatan">
    <input type="hidden" name="dp_tambahan" id="post_dp_tambahan" value="0">
</form>

<!-- BOTTOM NAV (Mobile) - SUDAH DISESUAIKAN -->
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

<!-- SCANNER MODAL -->
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
    <div class="fw-bold">Memproses Transaksi...</div>
</div>

<audio id="beep" src="https://www.soundjay.com/buttons/sounds/beep-07.mp3" preload="auto"></audio>

<script>
// ===== FUNGSI FORMAT RUPIAH =====
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

function parseRibuan(val) { if(!val) return 0; return parseInt(val.replace(/[^0-9]/g, '')) || 0; }
function validasiIMEI(imei) { return /^[0-9]{15}$/.test(imei); }

function getSelectedLabel(selectEl) {
    if (!selectEl || !selectEl.value) return '';
    const opt = selectEl.options[selectEl.selectedIndex];
    return (opt.dataset.label || opt.textContent || '').trim();
}

function resetSelect(selectEl, placeholder, disabled = true) {
    selectEl.innerHTML = `<option value="">${placeholder}</option>`;
    selectEl.disabled = disabled;
    selectEl.value = '';
}

function fillSelect(selectEl, items, placeholder) {
    resetSelect(selectEl, placeholder, false);
    items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.nama_barang || item.value;
        opt.dataset.label = item.nama_barang || item.value;
        opt.textContent = item.nama_barang || item.value;
        selectEl.appendChild(opt);
    });
}

// ===== MASTER CASCADE DROPDOWN =====
async function loadMasterBarang(merkId) {
    const namaBarangEl = document.getElementById('namaBarang');
    resetSelect(namaBarangEl, '-- Pilih Nama Barang --');
    resetSelect(document.getElementById('kondisi'), '-- Pilih Kondisi --');
    resetSelect(document.getElementById('internal'), '-- Pilih Storage --');
    resetSelect(document.getElementById('warna'), '-- Pilih Warna --');
    delete namaBarangEl.dataset.barangId;
    if (!merkId) return;
    try {
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
    } catch(e) { console.error(e); }
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
    try {
        const res = await fetch(url);
        const json = await res.json();
        if (json.status !== 'success') return;
        if (level === 'kondisi') fillSelect(document.getElementById('kondisi'), json.data, '-- Pilih Kondisi --');
        if (level === 'storage') fillSelect(document.getElementById('internal'), json.data, '-- Pilih Storage --');
        if (level === 'warna') fillSelect(document.getElementById('warna'), json.data, '-- Pilih Warna --');
    } catch(e) { console.error(e); }
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
document.getElementById('kondisi').addEventListener('change', () => loadVarianLevel('storage'));
document.getElementById('internal').addEventListener('change', () => loadVarianLevel('warna'));

// ===== CEK IMEI HP BARU =====
let imeiSearchTimeout;
let currentModal = 0;

document.getElementById('imei_baru_view').addEventListener('input', function() {
    clearTimeout(imeiSearchTimeout);
    var imei = this.value.trim();
    var boxAutofill = document.getElementById('container_autofill');
    var iconStatus = document.getElementById('icon_status');
    var labelTeks = document.getElementById('teks_status_autofill');
    var fieldHargaBaru = document.getElementById('harga_baru_view');
    if (imei.length > 0 && !validasiIMEI(imei)) {
        boxAutofill.classList.remove('d-none');
        boxAutofill.className = "badge-status-imei status-error";
        iconStatus.className = "fas fa-exclamation-circle text-danger";
        labelTeks.innerText = "IMEI harus 15 digit angka";
        fieldHargaBaru.value = ""; currentModal = 0; hitungSelisih();
        return;
    }
    if (imei.length >= 15 && validasiIMEI(imei)) {
        imeiSearchTimeout = setTimeout(() => {
            fetch('tukar_tambah.php?action=cari_imei_ajax&imei=' + encodeURIComponent(imei))
                .then(response => response.json())
                .then(res => {
                    boxAutofill.classList.remove('d-none');
                    if (res.success) {
                        boxAutofill.className = "badge-status-imei status-ready";
                        iconStatus.className = "fas fa-check-circle text-success";
                        labelTeks.innerHTML = "Produk Valid: <strong>" + res.nama_tipe + "</strong>";
                        fieldHargaBaru.value = res.harga_jual; currentModal = res.modal || 0;
                        formatRibuan(fieldHargaBaru); hitungSelisih();
                    } else {
                        boxAutofill.className = "badge-status-imei status-error";
                        iconStatus.className = "fas fa-times-circle text-danger";
                        labelTeks.innerText = res.message || "Unit tidak tersedia";
                        fieldHargaBaru.value = ""; currentModal = 0; hitungSelisih();
                    }
                })
                .catch(err => { console.error("Error:", err); });
        }, 300);
    } else {
        boxAutofill.classList.add('d-none');
        fieldHargaBaru.value = ""; currentModal = 0; hitungSelisih();
    }
});

// ===== HITUNG SELISIH =====
function hitungSelisih() {
    var hb = parseRibuan(document.getElementById('harga_baru_view').value);
    var hl = parseRibuan(document.getElementById('harga_lama_view').value);
    var sisa = hb - hl;
    var teksEl = document.getElementById('sisa_bayar_teks');
    var labelEl = document.getElementById('sisa_bayar_label');
    if (hb > 0 && hl > 0) {
        if (sisa < 0) { teksEl.innerText = 'Rp ' + Math.abs(sisa).toLocaleString('id-ID'); teksEl.style.color = '#10B981'; if(labelEl) labelEl.innerText = 'Kembalian (Refund):'; }
        else { teksEl.innerText = 'Rp ' + sisa.toLocaleString('id-ID'); teksEl.style.color = 'var(--primary-dongker)'; if(labelEl) labelEl.innerText = 'Sisa Wajib Dibayar:'; }
    } else { teksEl.innerText = 'Rp 0'; teksEl.style.color = 'var(--primary-dongker)'; if(labelEl) labelEl.innerText = 'Sisa Wajib Dibayar:'; }
}

// ===== PAYMENT & SUBMIT =====
const swalConfig = { background: '#FFFFFF', color: '#1E293B', confirmButtonColor: '#1E4A7A', cancelButtonColor: '#F1F5F9', customClass: { popup: 'rounded-4', cancelButton: 'text-dark fw-bold' } };
const paymentMethods = [
    { id: 'Cash', label: 'Cash', icon: 'fa-money-bill-wave', color: '#10B981' },
    { id: 'Transfer', label: 'Transfer', icon: 'fa-university', color: '#3B82F6' },
    { id: 'Kredivo', label: 'Kredivo', icon: 'fa-credit-card', color: '#EF4444' },
    { id: 'Akulaku', label: 'Akulaku', icon: 'fa-wallet', color: '#F59E0B' },
    { id: 'Shopee PayLater', label: 'Shopee PayLater', icon: 'fa-shopping-bag', color: '#F97316' },
];
let _selectedPaymentId = null;
let _selectedPaymentLabel = null;

function buildPaymentHTML(isDowngrade) {
    const btns = paymentMethods.map(m => {
        const safeId = m.id.replace(/\s/g, '_');
        const isCicilan = ['Kredivo', 'Akulaku', 'Shopee PayLater'].includes(m.id);
        const isDisabled = isDowngrade && isCicilan;
        const disabledAttr = isDisabled ? 'disabled' : '';
        const onclickAction = isDisabled ? '' : `pilihPayment('${m.id}','${m.label}')`;
        let subLabel = '';
        if (isCicilan) subLabel = 'Cicilan / PayLater';
        else if (m.id === 'Cash') subLabel = 'Tunai di tempat';
        else if (m.id === 'Transfer') subLabel = 'BCA / Mandiri / dll';
        return `
        <button type="button" class="pay-btn" id="pbtn-${safeId}" onclick="${onclickAction}" ${disabledAttr}>
            <div class="pay-icon-box" style="background:${m.color};"><i class="fas ${m.icon}"></i></div>
            <div style="flex:1; text-align:left;">
                <div class="pay-label">${m.label}</div>
                <div class="pay-sublabel">${subLabel}</div>
            </div>
            ${isDisabled ? '<i class="fas fa-lock" style="color:#EF4444;font-size:0.8rem;"></i>' : '<i class="fas fa-chevron-right" style="color:#CBD5E1;font-size:0.8rem;"></i>'}
        </button>`;
    }).join('');
    return `
        <div style="text-align:left; margin-bottom:12px;">
            <p style="color:#64748B;font-size:0.8rem;margin-bottom:8px;font-weight:600;">Pilih metode pembayaran</p>
            ${isDowngrade ? '<div style="background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;padding:8px 12px;border-radius:8px;font-size:0.75rem;margin-bottom:12px;font-weight:600;display:flex;align-items:center;gap:6px;"><i class="fas fa-exclamation-triangle"></i> Mode Downgrade terdeteksi. Metode PayLater dinonaktifkan.</div>' : ''}
            <div style="display:flex;flex-direction:column;gap:10px;">${btns}</div>
            <div id="pay-confirm-badge" style="display:none;align-items:center;gap:8px;margin-top:16px;padding:10px 14px;background:#EEF4FA;border:1px solid #1E4A7A;border-radius:10px;font-size:0.8rem;font-weight:700;color:#1E4A7A;"><i class="fas fa-check-circle"></i><span id="pay-confirm-label"></span></div>
        </div>
    `;
}

function pilihPayment(id, label) {
    _selectedPaymentId = id; _selectedPaymentLabel = label;
    document.querySelectorAll('.pay-btn').forEach(b => { b.classList.remove('selected'); });
    const btn = document.getElementById('pbtn-' + id.replace(/\s/g, '_'));
    if (btn) { btn.classList.add('selected'); }
    document.getElementById('pay-confirm-label').textContent = label + ' dipilih';
    document.getElementById('pay-confirm-badge').style.display = 'flex';
    Swal.getConfirmButton().disabled = false;
    Swal.getConfirmButton().style.opacity = '1';
}

async function prosesTT() {
    var imeiBaru = document.getElementById('imei_baru_view').value.trim();
    var hargaBaru = parseRibuan(document.getElementById('harga_baru_view').value);
    var modalBaru = currentModal;
    var merk = getSelectedLabel(document.getElementById('merk'));
    var namaBarang = getSelectedLabel(document.getElementById('namaBarang'));
    var kondisi = document.getElementById('kondisi').value;
    var internal = document.getElementById('internal').value;
    var warna = document.getElementById('warna').value;
    var imeiLama = document.getElementById('imei_lama_view').value.trim();
    var nilaiTukar = parseRibuan(document.getElementById('harga_lama_view').value);
    var hargaJualSeken = parseRibuan(document.getElementById('harga_jual_lama_view').value);

    if(!imeiBaru || !validasiIMEI(imeiBaru)) return Swal.fire({...swalConfig, icon:'error', title:'Error', text:'IMEI HP Baru tidak valid!'});
    if(!imeiLama || !validasiIMEI(imeiLama)) return Swal.fire({...swalConfig, icon:'error', title:'Error', text:'IMEI HP Lama tidak valid!'});
    if(hargaBaru<=0) return Swal.fire({...swalConfig, icon:'error', title:'Error', text:'Harga HP Baru belum terisi!'});
    if(!merk || !namaBarang || !kondisi || !internal || !warna) return Swal.fire({...swalConfig, icon:'error', title:'Error', text:'Data HP Lama belum lengkap!'});
    if(nilaiTukar<=0 || hargaJualSeken<=0) return Swal.fire({...swalConfig, icon:'error', title:'Error', text:'Nilai tukar & harga jual HP lama wajib diisi!'});
    if(imeiBaru === imeiLama) return Swal.fire({...swalConfig, icon:'error', title:'Error', text:'IMEI Baru dan Lama tidak boleh sama!'});

    var isDowngrade = nilaiTukar > hargaBaru;
    var kembalian = 0;
    var sisaAwal = 0;
    if (isDowngrade) { kembalian = nilaiTukar - hargaBaru; }
    else { sisaAwal = hargaBaru - nilaiTukar; }

    _selectedPaymentId = null;

    const step1 = await Swal.fire({
        ...swalConfig,
        title: '<span style="font-size:1rem;font-weight:800;">Metode Pembayaran</span>',
        html: buildPaymentHTML(isDowngrade),
        showCancelButton: true,
        confirmButtonText: 'Lanjut <i class="fas fa-arrow-right ms-1"></i>',
        cancelButtonText: 'Batal',
        didOpen: () => { const btn = Swal.getConfirmButton(); btn.disabled = true; btn.style.opacity = '0.4'; },
        preConfirm: () => { if (!_selectedPaymentId) { Swal.showValidationMessage('Pilih metode pembayaran'); return false; } return true; }
    });

    if(!step1.isConfirmed || !_selectedPaymentId) return;

    const isCicilan = ['Kredivo', 'Akulaku', 'Shopee PayLater'].includes(_selectedPaymentLabel);
    let dpInputHTML = '';
    if (!isDowngrade && isCicilan) {
        dpInputHTML = `
            <div style="margin-top:12px;">
                <label style="display:block;text-align:left;color:#64748B;font-size:0.75rem;margin-bottom:4px;font-weight:600;">DP Tambahan (Rp)</label>
                <input id="swal-dp-tambahan" type="number" placeholder="0" style="width:100%;box-sizing:border-box;height:40px;border:1px solid #E2E8F0;border-radius:8px;padding:0 10px;font-size:0.9rem;">
                <div style="margin-top:6px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:8px 10px;text-align:left;">
                    <span style="color:#64748B;font-size:0.7rem;font-weight:600;">Sisa dibiayai ${_selectedPaymentLabel}: </span>
                    <span id="sisa-cicilan-text" style="color:#1E4A7A;font-weight:800;font-size:0.85rem;">Rp ${sisaAwal.toLocaleString('id-ID')}</span>
                </div>
            </div>
        `;
    }

    let summaryDetailHTML = '';
    if (isDowngrade) {
        summaryDetailHTML = `<div style="display:flex;justify-content:space-between;font-size:0.85rem;"><span style="color:#10B981;font-weight:800;">Kembalian (Refund):</span><span style="font-weight:800;color:#10B981;">+ Rp ${kembalian.toLocaleString('id-ID')}</span></div>`;
    } else {
        summaryDetailHTML = `<div style="display:flex;justify-content:space-between;font-size:0.85rem;"><span style="color:#1E293B;font-weight:800;">Sisa Pembayaran:</span><span style="font-weight:800;color:#1E4A7A;">Rp ${sisaAwal.toLocaleString('id-ID')}</span></div>`;
    }

    const step2 = await Swal.fire({
        ...swalConfig,
        title: '<span style="font-size:1rem;font-weight:800;">Konfirmasi Transaksi</span>',
        html: `
            <div style="background:#EEF4FA;border:1px solid #1E4A7A;border-radius:10px;padding:10px 12px;margin-bottom:12px;">
                <div style="font-size:0.75rem;color:#1E4A7A;font-weight:700;margin-bottom:6px;"><i class="fas fa-tag"></i> Metode: ${_selectedPaymentLabel}</div>
                <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:4px;"><span style="color:#64748B;">Harga HP Baru:</span><span style="font-weight:700;">Rp ${hargaBaru.toLocaleString('id-ID')}</span></div>
                <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:4px;"><span style="color:#64748B;">Nilai Tukar HP Lama:</span><span style="font-weight:700;color:#10B981;">- Rp ${nilaiTukar.toLocaleString('id-ID')}</span></div>
                <hr style="border-top:1px dashed #CBD5E1;margin:8px 0;">
                ${summaryDetailHTML}
            </div>
            ${dpInputHTML}
            <div style="margin-top:12px;"><label style="display:block;text-align:left;color:#64748B;font-size:0.75rem;margin-bottom:4px;font-weight:600;">Nama Customer</label><input id="swal-nama" type="text" placeholder="Nama lengkap" style="width:100%;box-sizing:border-box;height:40px;border:1px solid #E2E8F0;border-radius:8px;padding:0 10px;font-size:0.9rem;"></div>
            <div style="margin-top:8px;"><label style="display:block;text-align:left;color:#64748B;font-size:0.75rem;margin-bottom:4px;font-weight:600;">No. WhatsApp</label><input id="swal-nohp" type="text" placeholder="0812..." style="width:100%;box-sizing:border-box;height:40px;border:1px solid #E2E8F0;border-radius:8px;padding:0 10px;font-size:0.9rem;"></div>
            <div style="margin-top:8px;"><label style="display:block;text-align:left;color:#64748B;font-size:0.75rem;margin-bottom:4px;font-weight:600;">Catatan</label><input id="swal-catatan" type="text" placeholder="Opsional" style="width:100%;box-sizing:border-box;height:40px;border:1px solid #E2E8F0;border-radius:8px;padding:0 10px;font-size:0.9rem;"></div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check-circle me-1"></i> Simpan',
        cancelButtonText: '<i class="fas fa-arrow-left me-1"></i> Kembali',
        didOpen: () => {
            if (!isDowngrade && isCicilan) {
                const dpInput = document.getElementById('swal-dp-tambahan');
                dpInput.addEventListener('input', () => {
                    const dp = Number(dpInput.value) || 0;
                    const sisaCicilan = sisaAwal - dp;
                    document.getElementById('sisa-cicilan-text').textContent = 'Rp ' + (sisaCicilan > 0 ? sisaCicilan : 0).toLocaleString('id-ID');
                });
            }
        },
        preConfirm: () => {
            const nama = document.getElementById('swal-nama').value.trim();
            if(!nama) { Swal.showValidationMessage('Nama wajib diisi'); return false; }
            let dpTambahan = 0;
            if (!isDowngrade && isCicilan) {
                dpTambahan = Number(document.getElementById('swal-dp-tambahan').value) || 0;
                if (dpTambahan > sisaAwal) { Swal.showValidationMessage('DP tidak boleh melebihi Sisa Pembayaran'); return false; }
            }
            return { nama: nama, nohp: document.getElementById('swal-nohp').value.trim(), catatan: document.getElementById('swal-catatan').value.trim(), dp_tambahan: dpTambahan };
        }
    });

    if (step2.isDismissed && step2.dismiss === Swal.DismissReason.cancel) return prosesTT();
    if (step2.isConfirmed) {
        document.getElementById('post_imei_baru').value = imeiBaru;
        document.getElementById('post_harga_jual_normal').value = hargaBaru;
        document.getElementById('post_imei_lama').value = imeiLama;
        document.getElementById('post_nama_hp_lama').value = merk;
        document.getElementById('post_tipe_lama').value = namaBarang;
        document.getElementById('post_warna_lama').value = warna;
        document.getElementById('post_internal_lama').value = internal;
        document.getElementById('post_kondisi_lama').value = kondisi;
        document.getElementById('post_nilai_tukar_lama').value = nilaiTukar;
        document.getElementById('post_harga_jual_lama').value = hargaJualSeken;
        document.getElementById('post_metode_pembayaran').value = _selectedPaymentLabel;
        document.getElementById('post_nama_customer').value = step2.value.nama;
        document.getElementById('post_no_hp').value = step2.value.nohp;
        document.getElementById('post_catatan').value = step2.value.catatan;
        document.getElementById('post_dp_tambahan').value = step2.value.dp_tambahan;
        document.getElementById('loadingOverlay').style.display = 'flex';
        setTimeout(() => { document.getElementById('formTT').submit(); }, 300);
    }
}

// ===== SCANNER =====
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
    else if (scannerMode === 'quagga' && quaggaInitialized) { const stream = Quagga.CameraAccess.getActiveStream(); if (stream) videoTrack = stream.getVideoTracks()[0]; }
    const torchBtn = document.getElementById('torchBtn');
    if (videoTrack) { track = videoTrack; try { torchBtn.style.display = track.getCapabilities().torch ? 'flex' : 'none'; } catch(e) { torchBtn.style.display = 'none'; } }
    else { torchBtn.style.display = 'none'; }
}
function toggleTorch() {
    if (!track) return;
    isTorchOn = !isTorchOn;
    track.applyConstraints({ advanced: [{ torch: isTorchOn }] }).catch(err => { console.error('Torch error:', err); isTorchOn = false; });
    const torchIcon = document.querySelector('#torchBtn i');
    if (isTorchOn) { torchIcon.classList.replace('fa-bolt', 'fa-bolt-slash'); } 
    else { torchIcon.classList.replace('fa-bolt-slash', 'fa-bolt'); }
}
async function startNativeScanner(facing) {
    const readerElement = document.getElementById('reader'); readerElement.innerHTML = '';
    const video = document.createElement('video');
    video.setAttribute('autoplay', ''); video.setAttribute('playsinline', ''); video.setAttribute('muted', '');
    video.style.width = '100%'; video.style.height = '100%'; video.style.objectFit = 'cover';
    readerElement.appendChild(video);
    nativeStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: facing, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
    video.srcObject = nativeStream; await video.play();
    const barcodeDetector = new BarcodeDetector({ formats: ['code_128','ean_13','ean_8','code_39','upc_a'] });
    const detectLoop = async () => {
        if (!nativeStream || !video.readyState) return;
        try {
            const barcodes = await barcodeDetector.detect(video);
            if (barcodes.length > 0 && !scanCooldown) {
                const code = barcodes[0].rawValue;
                if (code !== lastScan) {
                    scanCooldown = true; lastScan = code; beep.play().catch(() => {});
                    setTimeout(() => {
                        document.getElementById('imei_baru_view').value = code;
                        document.getElementById('imei_baru_view').dispatchEvent(new Event('input'));
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
function stopNativeScanner() { if (nativeStream) { nativeStream.getTracks().forEach(t => t.stop()); nativeStream = null; } document.getElementById('reader').innerHTML = ''; }
function startQuagga(facing) {
    if (quaggaInitialized) { Quagga.stop(); Quagga.offDetected(); quaggaInitialized = false; }
    Quagga.init({
        inputStream: { name: "Live", type: "LiveStream", target: document.querySelector('#reader'), constraints: { facingMode: facing } },
        decoder: { readers: ["code_128_reader","ean_reader","ean_8_reader","code_39_reader","upc_reader"] },
        locator: { patchSize: "medium", halfSample: false }, numOfWorkers: navigator.hardwareConcurrency || 4, frequency: 10, locate: true
    }, (err) => { if (err) { console.error('Quagga error:', err); Swal.fire({icon:'error', title:'Kamera gagal diakses'}); closeScanner(); return; } quaggaInitialized = true; Quagga.start(); });
    Quagga.offDetected();
    Quagga.onDetected((result) => {
        let code = result.codeResult.code;
        if (!scanCooldown && code !== lastScan) {
            scanCooldown = true; lastScan = code; beep.play().catch(() => {});
            setTimeout(() => {
                document.getElementById('imei_baru_view').value = code;
                document.getElementById('imei_baru_view').dispatchEvent(new Event('input'));
                closeScanner();
                setTimeout(() => { scanCooldown = false; lastScan = ""; }, 2000);
            }, 400);
        }
    });
}
async function startScanner(facing) {
    if (scannerMode === 'native' && nativeStream) stopNativeScanner();
    else if (scannerMode === 'quagga' && quaggaInitialized) { Quagga.stop(); Quagga.offDetected(); quaggaInitialized = false; }
    if (canUseBarcodeDetector()) { try { await startNativeScanner(facing); scannerMode = 'native'; } catch (e) { startQuagga(facing); scannerMode = 'quagga'; } }
    else { startQuagga(facing); scannerMode = 'quagga'; }
    await updateTorchAvailability();
}
function openScanner() {
    document.getElementById('scanner-container').style.display = 'block';
    scanCooldown = false; lastScan = ""; isTorchOn = false;
    const torchIcon = document.querySelector('#torchBtn i');
    if (torchIcon) { torchIcon.classList.remove('fa-bolt-slash'); torchIcon.classList.add('fa-bolt'); }
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

// ===== POPUP MENU MOBILE =====
const moreBtn = document.getElementById('moreMenuBtn');
const popup = document.getElementById('moreMenuPopup');
const overlay = document.getElementById('popupOverlay');
if(moreBtn) { moreBtn.addEventListener('click', function(e) { e.stopPropagation(); popup.classList.toggle('show'); overlay.classList.toggle('show'); }); }
if(overlay) { overlay.addEventListener('click', function() { popup.classList.remove('show'); overlay.classList.remove('show'); }); }
document.addEventListener('click', function(e) { if (popup && !popup.contains(e.target) && e.target !== moreBtn && !moreBtn.contains(e.target)) { popup.classList.remove('show'); overlay.classList.remove('show'); } });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>