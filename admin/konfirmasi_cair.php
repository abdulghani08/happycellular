<?php
session_start();

// Validasi session
if(!isset($_SESSION['login']) || $_SESSION['level'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

include '../connection.php';

// Validasi input parameter ID transaksi
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = (int)$_GET['id']; // Cast ke integer untuk keamanan
    
    // Simpan filter parameter pencarian
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date   = $_GET['end_date'] ?? date('Y-m-d');
    
    // Gunakan prepared statement untuk keamanan
    $stmt = mysqli_prepare($koneksi, "UPDATE penjualan SET 
                                      status_pencairan = 'Cair', 
                                      tanggal_pencairan = NOW() 
                                      WHERE id = ?");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            
            if ($affected_rows > 0) {
                // Simpan flash message di session
                $_SESSION['flash_success'] = "Status pencairan berhasil diperbarui menjadi CAIR.";
            } else {
                $_SESSION['flash_error'] = "Tidak ada perubahan data. Transaksi mungkin sudah cair.";
            }
            
            mysqli_stmt_close($stmt);
            
            // Redirect dengan filter yang dipertahankan
            header("Location: keuangan.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date));
            exit();
        } else {
            $_SESSION['flash_error'] = "Gagal memperbarui database: " . mysqli_error($koneksi);
            mysqli_stmt_close($stmt);
            header("Location: keuangan.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date));
            exit();
        }
    } else {
        $_SESSION['flash_error'] = "Error preparing statement: " . mysqli_error($koneksi);
        header("Location: keuangan.php");
        exit();
    }
} else {
    $_SESSION['flash_error'] = "ID transaksi tidak valid.";
    header("Location: keuangan.php");
    exit();
}
?>