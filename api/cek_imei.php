<?php
header('Content-Type: application/json');
require_once '../connection.php';   // ← perbaikan: hanya naik 1 level, karena ada di folder api/

$imei = isset($_GET['imei']) ? trim($_GET['imei']) : '';

if (empty($imei)) {
    echo json_encode(['exists' => false, 'error' => 'IMEI kosong']);
    exit;
}

$stmt = $koneksi->prepare("SELECT harga_jual, modal FROM barang WHERE imei = ?");
$stmt->bind_param("s", $imei);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'exists'     => true,
        'harga_jual' => (int)$row['harga_jual'],
        'modal'      => (int)$row['modal']
    ]);
} else {
    echo json_encode(['exists' => false]);
}

$stmt->close();
$koneksi->close();