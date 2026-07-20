<?php
session_start();
require_once 'auth_helper.php';
restrictTo(['admin']);
require_once '../connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'msg' => 'Method not allowed']);
    exit;
}

$payloadJson = $_POST['payload'] ?? '{}';
$payload = json_decode($payloadJson, true);
$groups = $payload['groups'] ?? [];

if (empty($groups)) {
    echo json_encode(['status' => 'error', 'msg' => 'Tidak ada grup data']);
    exit;
}

// Proses gambar (jika ada)
// PENTING: kolom `gambar` di tabel `barang` bertipe BLOB dan dibaca langsung
// sebagai data biner oleh data_barang.php (base64_encode($row['gambar'])).
// Jadi di sini kita HARUS menyimpan isi biner filenya, bukan path/nama filenya.
$gambarData = null;
if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
    $gambarData = file_get_contents($_FILES['gambar']['tmp_name']);
}

$totalSuccess = 0;
$errors = [];

mysqli_begin_transaction($koneksi);

foreach ($groups as $group) {
    $spec = $group['spesifikasi'];
    $imeis = $group['imei_list'];

    $nama = $spec['nama'] ?? '';
    $tipe = $spec['tipe'] ?? '';
    $warna = $spec['warna'] ?? '';
    $internal = $spec['internal'] ?? '';
    $supplier = $spec['supplier'] ?? '';
    $keterangan = $spec['keterangan'] ?? '';
    $modal = $spec['modal'] ?? 0;
    $harga_jual = $spec['harga_jual'] ?? 0;
    $tanggal = $spec['tanggal'] ?? date('Y-m-d');

    if (empty($nama) || empty($tipe)) {
        $errors[] = "Spesifikasi tidak lengkap pada salah satu grup";
        continue;
    }

    foreach ($imeis as $imei) {
        // PERBAIKAN 1: Ganti data_barang menjadi barang
        $check = mysqli_query($koneksi, "SELECT id FROM barang WHERE imei = '$imei'");
        if (mysqli_num_rows($check) > 0) {
            $errors[] = "IMEI $imei sudah terdaftar di database";
            continue;
        }

        // PERBAIKAN 2: Sesuaikan nama tabel dan kolom (internal, tanggal)
        $stmt = mysqli_prepare($koneksi, "INSERT INTO barang 
            (imei, nama, tipe, warna, internal, supplier, keterangan, modal, harga_jual, tanggal, status, gambar) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'READY', ?)");

        // PERBAIKAN 3: kolom gambar pakai tipe 'b' (blob) karena isinya data biner,
        // bukan string biasa. Data biner dikirim lewat send_long_data agar aman
        // terhadap karakter NULL/byte apapun di dalam file gambar.
        mysqli_stmt_bind_param($stmt, 'sssssssddsb',
            $imei, $nama, $tipe, $warna, $internal, $supplier, $keterangan, $modal, $harga_jual, $tanggal, $gambarData
        );
        if ($gambarData !== null) {
            mysqli_stmt_send_long_data($stmt, 10, $gambarData); // index ke-10 = param terakhir ('b')
        }

        if (mysqli_stmt_execute($stmt)) {
            $totalSuccess++;
        } else {
            $errors[] = "Gagal menyimpan IMEI $imei: " . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt);
    }
}

if ($totalSuccess > 0) {
    mysqli_commit($koneksi);
    $msg = "Berhasil menyimpan $totalSuccess barang.";
    if (!empty($errors)) {
        $msg .= " Namun ada beberapa masalah: " . implode('; ', $errors);
    }
    echo json_encode(['status' => 'success', 'msg' => $msg]);
} else {
    mysqli_rollback($koneksi);
    echo json_encode(['status' => 'error', 'msg' => 'Gagal menyimpan semua barang. ' . implode('; ', $errors)]);
}