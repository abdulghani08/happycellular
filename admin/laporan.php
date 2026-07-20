<?php
session_start();
if(!isset($_SESSION['login']) || $_SESSION['level'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

include '../connection.php';

// ==================== DATA HARIAN ====================
$hari = mysqli_fetch_assoc(mysqli_query($koneksi,"
    SELECT 
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(harga_jual),0) as total_pendapatan 
    FROM penjualan 
    WHERE DATE(tanggal) = CURDATE()
"));

$total_transaksi_hari = $hari['jumlah_transaksi'];
$total_pendapatan_hari = $hari['total_pendapatan'];

// ==================== DATA BULANAN ====================
$bulan = mysqli_fetch_assoc(mysqli_query($koneksi,"
    SELECT 
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(harga_jual),0) as total_pendapatan 
    FROM penjualan 
    WHERE MONTH(tanggal) = MONTH(CURDATE()) 
    AND YEAR(tanggal) = YEAR(CURDATE())
"));

$total_transaksi_bulan = $bulan['jumlah_transaksi'];
$total_pendapatan_bulan = $bulan['total_pendapatan'];

// ==================== DATA TAHUNAN ====================
$tahun = mysqli_fetch_assoc(mysqli_query($koneksi,"
    SELECT 
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(harga_jual),0) as total_pendapatan 
    FROM penjualan 
    WHERE YEAR(tanggal) = YEAR(CURDATE())
"));

$total_transaksi_tahun = $tahun['jumlah_transaksi'];
$total_pendapatan_tahun = $tahun['total_pendapatan'];

// ==================== GRAFIK HARIAN ====================
$grafik = mysqli_query($koneksi,"
    SELECT DATE(tanggal) as tgl, COALESCE(SUM(harga_jual),0) as total
    FROM penjualan
    WHERE tanggal >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(tanggal)
");

$map = [];
while($row = mysqli_fetch_assoc($grafik)){
    $map[$row['tgl']] = (int)$row['total'];
}

$harian_labels = [];
$harian_pendapatan = [];

for($i=6;$i>=0;$i--){
    $tgl = date('Y-m-d', strtotime("-$i days"));
    $harian_labels[] = date('d M', strtotime($tgl));
    $harian_pendapatan[] = $map[$tgl] ?? 0;
}

// ==================== TOTAL ====================
$total_all = mysqli_fetch_assoc(mysqli_query($koneksi,"
    SELECT COUNT(*) as trx, COALESCE(SUM(harga_jual),0) as total FROM penjualan
"));

$total_transaksi = $total_all['trx'];
$total_pendapatan = $total_all['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Keuangan</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body { background:#f4f7fb; font-family:Arial; }
.card { border-radius:15px; }
.stat { font-size:22px; font-weight:bold; }
</style>
</head>

<body>

<div class="container mt-4">

<h4 class="mb-4">Dashboard Keuangan</h4>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card p-3">
            <div>Hari Ini</div>
            <div class="stat text-primary">Rp <?=number_format($total_pendapatan_hari)?></div>
            <small><?=$total_transaksi_hari?> transaksi</small>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card p-3">
            <div>Bulan Ini</div>
            <div class="stat text-success">Rp <?=number_format($total_pendapatan_bulan)?></div>
            <small><?=$total_transaksi_bulan?> transaksi</small>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card p-3">
            <div>Tahun Ini</div>
            <div class="stat text-warning">Rp <?=number_format($total_pendapatan_tahun)?></div>
            <small><?=$total_transaksi_tahun?> transaksi</small>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card p-3">
            <div>Total</div>
            <div class="stat text-dark">Rp <?=number_format($total_pendapatan)?></div>
            <small><?=$total_transaksi?> transaksi</small>
        </div>
    </div>
</div>

<div class="card p-4 mb-4">
    <h5>Grafik 7 Hari</h5>
    <canvas id="chart"></canvas>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between mb-3">
        <h5>Transaksi Terbaru</h5>
        <a href="data_penjualan.php" class="btn btn-sm btn-primary">Lihat Semua</a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>IMEI</th>
                <th>Harga</th>
                <th>Tanggal</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $q = mysqli_query($koneksi,"SELECT * FROM penjualan ORDER BY tanggal DESC LIMIT 10");
        while($d = mysqli_fetch_assoc($q)):
        ?>
            <tr>
                <td><?=$d['id']?></td>
                <td><?=$d['imei']?></td>
                <td>Rp <?=number_format($d['harga_jual'])?></td>
                <td><?=date('d-m-Y H:i',strtotime($d['tanggal']))?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</div>

<script>
new Chart(document.getElementById('chart'),{
    type:'line',
    data:{
        labels: <?=json_encode($harian_labels)?>,
        datasets:[{
            label:'Pendapatan',
            data: <?=json_encode($harian_pendapatan)?>,
            borderColor:'blue',
            fill:false
        }]
    }
});
</script>

</body>
</html>