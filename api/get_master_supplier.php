<?php
require_once 'auth_helper.php';
restrictAPI(['admin']);

$result = mysqli_query(
    $koneksi,
    "SELECT id, nama_supplier FROM master_supplier WHERE status = 'aktif' ORDER BY nama_supplier ASC"
);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

echo json_encode(['status' => 'success', 'data' => $data]);
