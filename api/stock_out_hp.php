<?php
session_start();
header('Content-Type: application/json');

// Matikan error reporting untuk production, aktifkan untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cek apakah user sudah login
if(!isset($_SESSION['login']) || $_SESSION['level'] != 'admin'){
    echo json_encode(['status' => 'error', 'msg' => 'Akses Ditolak! Silakan login terlebih dahulu.']);
    exit;
}

include '../connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'msg' => 'Method tidak diizinkan']);
    exit;
}

$imei = isset($_POST['imei']) ? mysqli_real_escape_string($koneksi, $_POST['imei']) : '';
$harga_jual = isset($_POST['harga_jual']) ? (int)$_POST['harga_jual'] : 0;
$tanggal = isset($_POST['tanggal']) ? mysqli_real_escape_string($koneksi, $_POST['tanggal']) : date('Y-m-d');

if (empty($imei)) {
    echo json_encode(['status' => 'error', 'msg' => 'IMEI tidak boleh kosong']);
    exit;
}

if ($harga_jual <= 0) {
    echo json_encode(['status' => 'error', 'msg' => 'Harga jual harus diisi dengan benar']);
    exit;
}

// Cek apakah barang ada dan status READY
$cek_query = "SELECT * FROM barang WHERE imei = '$imei' AND status = 'READY'";
$cek_result = mysqli_query($koneksi, $cek_query);

if (!$cek_result) {
    echo json_encode(['status' => 'error', 'msg' => 'Error query: ' . mysqli_error($koneksi)]);
    exit;
}

if (mysqli_num_rows($cek_result) == 0) {
    echo json_encode(['status' => 'error', 'msg' => 'IMEI tidak ditemukan atau barang sudah NON READY']);
    exit;
}

$barang = mysqli_fetch_assoc($cek_result);
$tanggal_full = $tanggal . ' ' . date('H:i:s');

// Insert ke tabel penjualan
$insert_query = "INSERT INTO penjualan (imei, harga, tanggal) VALUES ('$imei', '$harga_jual', '$tanggal_full')";

if (mysqli_query($koneksi, $insert_query)) {
    // Update status barang menjadi NON READY
    $update_query = "UPDATE barang SET status = 'NON READY' WHERE imei = '$imei'";
    mysqli_query($koneksi, $update_query);
    
    echo json_encode([
        'status' => 'success',
        'msg' => "Stock out berhasil! Barang {$barang['nama']} telah terjual dengan harga Rp " . number_format($harga_jual, 0, ',', '.')
    ]);
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Gagal mencatat penjualan: ' . mysqli_error($koneksi)]);
}
?>