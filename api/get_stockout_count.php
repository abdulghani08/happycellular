<?php
session_start();
header('Content-Type: application/json');

// Cek apakah user sudah login
if(!isset($_SESSION['login']) || $_SESSION['level'] != 'admin'){
    echo json_encode(['status' => 'error', 'msg' => 'Akses Ditolak']);
    exit;
}

include '../connection.php';

$query = "SELECT COUNT(*) as total FROM penjualan WHERE DATE(tanggal) = CURDATE()";
$result = mysqli_query($koneksi, $query);

if (!$result) {
    echo json_encode(['status' => 'error', 'msg' => mysqli_error($koneksi)]);
    exit;
}

$data = mysqli_fetch_assoc($result);

echo json_encode([
    'status' => 'success',
    'count' => (int)($data['total'] ?? 0)
]);
?>