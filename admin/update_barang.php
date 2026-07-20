<?php
session_start();
require_once '../api/auth_helper.php';
restrictTo(['admin']);
include '../connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data_barang.php');
    exit;
}

$id         = (int)($_POST['id'] ?? 0);
$nama       = trim($_POST['nama'] ?? '');
$tipe       = trim($_POST['tipe'] ?? '');
$warna      = trim($_POST['warna'] ?? '');
$internal   = (int)($_POST['internal'] ?? 0);
$supplier   = trim($_POST['supplier'] ?? '');
$imei       = trim($_POST['imei'] ?? '');
$modal      = (float)($_POST['modal'] ?? 0);
$harga_jual = (float)($_POST['harga_jual'] ?? 0);
$status     = trim($_POST['status'] ?? 'READY');
$keterangan = trim($_POST['keterangan'] ?? '');

if (!$id || !$nama || !$imei) {
    header('Location: data_barang.php?error=Data tidak lengkap');
    exit;
}

$query = "UPDATE barang SET 
            nama = ?, 
            tipe = ?, 
            warna = ?, 
            internal = ?, 
            supplier = ?, 
            modal = ?, 
            harga_jual = ?, 
            status = ?, 
            keterangan = ?, 
            updated_at = NOW() 
          WHERE id = ?";

$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, "sssisdsssi", 
    $nama, $tipe, $warna, $internal, $supplier, 
    $modal, $harga_jual, $status, $keterangan, $id
);

if (mysqli_stmt_execute($stmt)) {
    header('Location: data_barang.php?success=Produk berhasil diperbarui');
} else {
    header('Location: data_barang.php?error=Gagal memperbarui produk');
}
exit;