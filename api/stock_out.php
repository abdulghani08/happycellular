<?php
session_start();
header('Content-Type: application/json');
error_reporting(0);

// Cek login & level
if (!isset($_SESSION['login']) || $_SESSION['level'] != 'frontliner') {
    echo json_encode(["status" => "error", "msg" => "Akses ditolak"]);
    exit();
}

include '../connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "msg" => "Method tidak diizinkan"]);
    exit();
}

$imei = trim($_POST['imei'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? 'Tanpa keterangan');

if ($imei === '') {
    echo json_encode(["status" => "error", "msg" => "IMEI wajib diisi"]);
    exit();
}

// Cek apakah barang dengan IMEI tersebut ada dan status READY
$cek = $koneksi->prepare("SELECT status FROM barang WHERE imei = ?");
$cek->bind_param("s", $imei);
$cek->execute();
$cek->store_result();

if ($cek->num_rows === 0) {
    echo json_encode(["status" => "error", "msg" => "IMEI tidak ditemukan"]);
    $cek->close();
    exit();
}

$cek->bind_result($status);
$cek->fetch();
$cek->close();

if ($status !== 'READY') {
    echo json_encode(["status" => "error", "msg" => "Barang sudah tidak READY"]);
    exit();
}

// Mulai transaksi
$koneksi->begin_transaction();

try {
    // Update status barang
    $stmt1 = $koneksi->prepare("UPDATE barang SET status = 'NON READY' WHERE imei = ?");
    $stmt1->bind_param("s", $imei);
    $stmt1->execute();
    $stmt1->close();

    // Insert ke stock_out
    $stmt2 = $koneksi->prepare("INSERT INTO stock_out (imei, keterangan) VALUES (?, ?)");
    $stmt2->bind_param("ss", $imei, $keterangan);
    $stmt2->execute();
    $stmt2->close();

    $koneksi->commit();
    echo json_encode(["status" => "success", "msg" => "Stock out berhasil diproses"]);
} catch (Exception $e) {
    $koneksi->rollback();
    echo json_encode(["status" => "error", "msg" => "Gagal: " . $e->getMessage()]);
}

$koneksi->close();