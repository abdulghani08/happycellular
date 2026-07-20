<?php
session_start();
include '../connection.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $imei = mysqli_real_escape_string($koneksi, $_POST['imei']);
    $harga = mysqli_real_escape_string($koneksi, $_POST['harga']);
    $tanggal = date('Y-m-d H:i:s'); // Waktu server saat ini
    
    // Validasi IMEI apakah ada di database barang dan statusnya READY
    $check_query = "SELECT * FROM barang WHERE imei = '$imei' AND status = 'READY'";
    $check_result = mysqli_query($koneksi, $check_query);
    
    if(mysqli_num_rows($check_result) > 0) {
        // Insert data penjualan
        $query = "INSERT INTO penjualan (imei, harga, tanggal) VALUES ('$imei', '$harga', '$tanggal')";
        
        if(mysqli_query($koneksi, $query)) {
            // Update status barang menjadi NON READY karena sudah terjual
            $update_barang = "UPDATE barang SET status = 'NON READY' WHERE imei = '$imei'";
            mysqli_query($koneksi, $update_barang);
            
            $_SESSION['success'] = "Penjualan berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan penjualan: " . mysqli_error($koneksi);
        }
    } else {
        $_SESSION['error'] = "IMEI tidak ditemukan atau barang sedang tidak READY!";
    }
    
    header("Location: penjualan.php");
    exit();
}
?>