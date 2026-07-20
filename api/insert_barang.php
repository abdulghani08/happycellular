<?php
session_start();
header('Content-Type: application/json');
error_reporting(0);

if (!isset($_SESSION['login']) || $_SESSION['level'] !== 'admin') {
    echo json_encode(["status" => "error", "msg" => "Akses ditolak"]);
    exit();
}

include '../connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "msg" => "Method tidak diizinkan"]);
    exit();
}

// Ambil data teks
$imei     = trim($_POST['imei'] ?? '');
$nama     = trim($_POST['nama'] ?? '');
$tipe     = trim($_POST['tipe'] ?? '');
$warna    = trim($_POST['warna'] ?? '');
$internal = trim($_POST['internal'] ?? '');
$supplier = trim($_POST['supplier'] ?? '');
$modal    = isset($_POST['modal']) ? (int)$_POST['modal'] : 0;
$tanggal  = $_POST['tanggal'] ?? date('Y-m-d');

// Proses upload gambar
$gambar = null;
if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
    $fileTmp   = $_FILES['gambar']['tmp_name'];
    $fileName  = $_FILES['gambar']['name'];
    $fileSize  = $_FILES['gambar']['size'];
    $fileExt   = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed   = ['jpg', 'jpeg', 'png'];

    if (!in_array($fileExt, $allowed)) {
        echo json_encode(["status" => "error", "msg" => "Format gambar harus JPG atau PNG"]);
        exit();
    }
    if ($fileSize > 2 * 1024 * 1024) {
        echo json_encode(["status" => "error", "msg" => "Ukuran gambar maksimal 2MB"]);
        exit();
    }

    $newName = time() . '_' . uniqid() . '.' . $fileExt;
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $destination = $uploadDir . $newName;

    if (move_uploaded_file($fileTmp, $destination)) {
        $gambar = $newName;
    } else {
        echo json_encode(["status" => "error", "msg" => "Gagal mengupload gambar"]);
        exit();
    }
}

// Validasi dasar
if ($imei === '') {
    echo json_encode(["status" => "error", "msg" => "IMEI wajib diisi"]);
    exit();
}
if (strlen($imei) < 10) {
    echo json_encode(["status" => "error", "msg" => "IMEI tidak valid"]);
    exit();
}
if ($modal < 0) {
    echo json_encode(["status" => "error", "msg" => "Harga modal tidak valid"]);
    exit();
}

// Cek duplikat IMEI
$cek = $koneksi->prepare("SELECT id FROM barang WHERE imei = ?");
$cek->bind_param("s", $imei);
$cek->execute();
$cek->store_result();
if ($cek->num_rows > 0) {
    echo json_encode(["status" => "error", "msg" => "IMEI sudah terdaftar"]);
    $cek->close();
    $koneksi->close();
    exit();
}
$cek->close();

// Insert data (termasuk gambar jika ada)
if ($gambar !== null) {
    $stmt = $koneksi->prepare("
        INSERT INTO barang 
        (nama, tipe, imei, warna, internal, supplier, modal, tanggal, gambar, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'READY')
    ");
    $stmt->bind_param("sssssssis", $nama, $tipe, $imei, $warna, $internal, $supplier, $modal, $tanggal, $gambar);
} else {
    $stmt = $koneksi->prepare("
        INSERT INTO barang 
        (nama, tipe, imei, warna, internal, supplier, modal, tanggal, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'READY')
    ");
    $stmt->bind_param("ssssssis", $nama, $tipe, $imei, $warna, $internal, $supplier, $modal, $tanggal);
}

if (!$stmt) {
    echo json_encode(["status" => "error", "msg" => "Prepare gagal: " . $koneksi->error]);
    exit();
}

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "msg" => "Barang berhasil ditambahkan"]);
} else {
    echo json_encode(["status" => "error", "msg" => "Gagal menyimpan: " . $stmt->error]);
}

$stmt->close();
$koneksi->close();