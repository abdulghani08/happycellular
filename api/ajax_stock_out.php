<?php
session_start();
require_once 'auth_helper.php';
restrictAPI(['admin', 'frontliner']);

include '../connection.php';

// Validasi aksi
if (!isset($_POST['action']) || $_POST['action'] !== 'scan_out') {
    echo json_encode([
        'status' => 'error',
        'msg'    => 'Aksi tidak valid'
    ]);
    exit;
}

// Ambil input
$imei           = preg_replace('/[^0-9]/', '', $_POST['imei'] ?? '');
$harga_final    = (int)($_POST['harga_jual'] ?? 0);
$metode         = trim($_POST['metode'] ?? '');
$nama_customer  = trim($_POST['nama_customer'] ?? '');
$no_hp          = trim($_POST['no_hp'] ?? '');
$catatan        = trim($_POST['keterangan'] ?? '');
$dp             = (int)($_POST['dp'] ?? 0);
$user           = $_SESSION['username'] ?? 'unknown';

// Validasi dasar
if (empty($imei)) {
    echo json_encode([
        'status' => 'error',
        'msg'    => 'IMEI tidak boleh kosong'
    ]);
    exit;
}

if ($harga_final <= 0) {
    echo json_encode([
        'status' => 'error',
        'msg'    => 'Harga jual tidak valid'
    ]);
    exit;
}

if (empty($metode)) {
    echo json_encode([
        'status' => 'error',
        'msg'    => 'Metode pembayaran wajib dipilih'
    ]);
    exit;
}

$koneksi->begin_transaction();

try {

    // =========================
    // AMBIL DATA BARANG
    // =========================
    $stmt = $koneksi->prepare("
        SELECT nama, modal, status
        FROM barang
        WHERE imei = ?
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception("Prepare SELECT gagal: " . $koneksi->error);
    }

    $stmt->bind_param("s", $imei);

    if (!$stmt->execute()) {
        throw new Exception("Execute SELECT gagal: " . $stmt->error);
    }

    $res  = $stmt->get_result();
    $data = $res->fetch_assoc();

    if (!$data) {
        throw new Exception("IMEI tidak ditemukan!");
    }

    if ($data['status'] !== 'READY') {
        throw new Exception("Barang berstatus: " . $data['status']);
    }

    $modal = (int)$data['modal'];

    // =========================
    // UPDATE STATUS BARANG
    // =========================
    $upd = $koneksi->prepare("
        UPDATE barang
        SET status='NO READY', updated_at=NOW()
        WHERE imei=?
    ");

    if (!$upd) {
        throw new Exception("Prepare UPDATE gagal: " . $koneksi->error);
    }

    $upd->bind_param("s", $imei);

    if (!$upd->execute()) {
        throw new Exception("Update barang gagal: " . $upd->error);
    }

    // =========================
    // INSERT STOCK OUT
    // =========================
    $keterangan_out = "Metode: $metode";

    if ($nama_customer) {
        $keterangan_out .= " | Customer: $nama_customer";
    }

    if ($no_hp) {
        $keterangan_out .= " ($no_hp)";
    }

    if ($catatan) {
        $keterangan_out .= " | Catatan: $catatan";
    }

    $st_out = $koneksi->prepare("
        INSERT INTO stock_out
        (imei, keterangan, user, tanggal, status)
        VALUES (?, ?, ?, NOW(), 'OUT')
    ");

    if (!$st_out) {
        throw new Exception("Prepare stock_out gagal: " . $koneksi->error);
    }

    $st_out->bind_param(
        "sss",
        $imei,
        $keterangan_out,
        $user
    );

    if (!$st_out->execute()) {
        throw new Exception("Insert stock_out gagal: " . $st_out->error);
    }

    // =========================
    // INSERT PENJUALAN
    // =========================
    $ins_jual = $koneksi->prepare("
        INSERT INTO penjualan
        (
            imei,
            harga_jual,
            modal,
            dp,
            nama_customer,
            no_hp,
            metode_pembayaran,
            tanggal
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    if (!$ins_jual) {
        throw new Exception("Prepare penjualan gagal: " . $koneksi->error);
    }

    $ins_jual->bind_param(
        "siiisss",
        $imei,
        $harga_final,
        $modal,
        $dp,
        $nama_customer,
        $no_hp,
        $metode
    );

    if (!$ins_jual->execute()) {
        throw new Exception("Insert penjualan gagal: " . $ins_jual->error);
    }

    // =========================
    // COMMIT
    // =========================
    $koneksi->commit();

    $laba = $harga_final - $modal;

    echo json_encode([
        'status'    => 'success',
        'nama'      => $data['nama'],
        'harga'     => number_format($harga_final, 0, ',', '.'),
        'laba'      => number_format($laba, 0, ',', '.'),
        'laba_raw'  => $laba,
        'timestamp' => date('H:i:s')
    ]);

} catch (Exception $e) {

    $koneksi->rollback();

    echo json_encode([
        'status' => 'error',
        'msg'    => $e->getMessage()
    ]);
}
?>