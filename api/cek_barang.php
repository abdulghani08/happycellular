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

$imei = isset($_GET['imei']) ? mysqli_real_escape_string($koneksi, $_GET['imei']) : '';

if (empty($imei)) {
    echo json_encode(['status' => 'error', 'msg' => 'IMEI tidak boleh kosong']);
    exit;
}

$query = "SELECT * FROM barang WHERE imei = '$imei'";
$result = mysqli_query($koneksi, $query);

if (!$result) {
    echo json_encode(['status' => 'error', 'msg' => 'Error query: ' . mysqli_error($koneksi)]);
    exit;
}

if (mysqli_num_rows($result) > 0) {
    $data = mysqli_fetch_assoc($result);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'imei' => $data['imei'],
            'nama' => $data['nama'],
            'tipe' => $data['tipe'],
            'warna' => $data['warna'],
            'internal' => $data['internal'],
            'supplier' => $data['supplier'],
            'modal' => $data['modal'],
            'status' => $data['status']
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'msg' => 'IMEI tidak ditemukan']);
}
?>