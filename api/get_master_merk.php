<?php
require_once 'auth_helper.php';
restrictAPI(['admin']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = $koneksi->prepare("SELECT id, nama_merk FROM master_merk WHERE id = ? AND status = 'aktif' LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        echo json_encode(['status' => 'success', 'data' => $row]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Merk tidak ditemukan']);
    }
    exit();
}

$result = mysqli_query(
    $koneksi,
    "SELECT id, nama_merk FROM master_merk WHERE status = 'aktif' ORDER BY nama_merk ASC"
);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

echo json_encode(['status' => 'success', 'data' => $data]);
