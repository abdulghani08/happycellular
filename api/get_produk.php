<?php

require_once '../connection.php';

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);

$stmt = $koneksi->prepare("
SELECT *
FROM master_produk
WHERE id = ?
AND aktif = 1
");

$stmt->bind_param("i",$id);
$stmt->execute();

$result = $stmt->get_result();

if($row = $result->fetch_assoc())
{
    echo json_encode([
        'status' => 'success',
        'data' => $row
    ]);
}
else
{
    echo json_encode([
        'status' => 'error'
    ]);
}