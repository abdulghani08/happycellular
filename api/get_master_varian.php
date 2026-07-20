<?php
require_once 'auth_helper.php';
restrictAPI(['admin']);

$barangId = (int)($_GET['barang_id'] ?? 0);
$level = $_GET['level'] ?? 'kondisi';
$kondisi = trim($_GET['kondisi'] ?? '');
$internal = trim($_GET['internal_storage'] ?? '');

if ($barangId <= 0) {
    echo json_encode(['status' => 'error', 'msg' => 'Barang wajib dipilih']);
    exit();
}

$data = [];

if ($level === 'kondisi') {
    $stmt = $koneksi->prepare(
        "SELECT DISTINCT kondisi AS value FROM master_varian_barang
         WHERE barang_id = ? AND status = 'aktif'
         ORDER BY kondisi ASC"
    );
    $stmt->bind_param('i', $barangId);
} elseif ($level === 'storage') {
    if ($kondisi === '') {
        echo json_encode(['status' => 'error', 'msg' => 'Kondisi wajib dipilih']);
        exit();
    }
    $stmt = $koneksi->prepare(
        "SELECT DISTINCT internal_storage AS value FROM master_varian_barang
         WHERE barang_id = ? AND kondisi = ? AND status = 'aktif'
         ORDER BY internal_storage ASC"
    );
    $stmt->bind_param('is', $barangId, $kondisi);
} elseif ($level === 'warna') {
    if ($kondisi === '' || $internal === '') {
        echo json_encode(['status' => 'error', 'msg' => 'Kondisi dan storage wajib dipilih']);
        exit();
    }
    $stmt = $koneksi->prepare(
        "SELECT DISTINCT warna AS value FROM master_varian_barang
         WHERE barang_id = ? AND kondisi = ? AND internal_storage = ? AND status = 'aktif'
         ORDER BY warna ASC"
    );
    $stmt->bind_param('iss', $barangId, $kondisi, $internal);
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Level tidak valid']);
    exit();
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();

echo json_encode(['status' => 'success', 'data' => $data]);
