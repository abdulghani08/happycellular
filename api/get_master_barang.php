<?php
require_once 'auth_helper.php';
restrictAPI(['admin']);

$merkId = (int)($_GET['merk_id'] ?? 0);

if ($merkId <= 0) {
    echo json_encode(['status' => 'error', 'msg' => 'Merk wajib dipilih']);
    exit();
}

$stmt = $koneksi->prepare(
    "SELECT id, nama_barang FROM master_barang
     WHERE merk_id = ? AND status = 'aktif'
     ORDER BY nama_barang ASC"
);
$stmt->bind_param('i', $merkId);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();

echo json_encode(['status' => 'success', 'data' => $data]);
