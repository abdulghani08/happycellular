<?php
session_start();
require_once 'auth_helper.php';
restrictTo(['admin']); // hanya admin yang bisa akses

include '../connection.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Content-Type: image/png');
    readfile('../asset/no-image.png'); // fallback
    exit;
}

$stmt = $koneksi->prepare("SELECT gambar FROM barang WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($blob);
$stmt->fetch();

if ($blob) {
    // deteksi tipe MIME sederhana (asumsi gambar JPEG/PNG)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($blob);
    header("Content-Type: $mime");
    echo $blob;
} else {
    // jika tidak ada gambar, kirim placeholder
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#f0f0f0"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="18" fill="#999">No Image</text></svg>';
}

$stmt->close();
$koneksi->close();