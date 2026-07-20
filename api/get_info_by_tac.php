<?php
session_start();
header('Content-Type: application/json');
require_once 'auth_helper.php';
restrictAPI(['admin']);
include '../connection.php';

$imei = isset($_GET['imei']) ? trim($_GET['imei']) : '';

if (strlen($imei) < 8) {
    echo json_encode(['success' => false, 'msg' => 'IMEI terlalu pendek']);
    exit;
}

$tac = substr($imei, 0, 8); // Ambil 8 digit pertama (TAC)

// Cari model berdasarkan TAC yang pernah diinput sebelumnya
$stmt = $koneksi->prepare("SELECT nama, tipe, warna, internal, supplier, modal, harga_jual FROM barang WHERE imei LIKE ? ORDER BY id DESC LIMIT 1");
$tac_param = $tac . "%";
$stmt->bind_param("s", $tac_param);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo json_encode(['success' => true, 'data' => $row, 'tac' => $tac]);
} else {
    echo json_encode(['success' => false, 'msg' => 'TAC baru, belum ada di database']);
}

$stmt->close();