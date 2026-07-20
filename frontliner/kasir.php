<?php
session_start();
if(!isset($_SESSION['login']) || $_SESSION['level'] != 'frontliner'){
    header("Location: ../login.php");
    exit();
}
include '../connection.php';

$barang = null;
$message = "";

// SCAN
if(isset($_POST['scan'])){
    $imei = trim($_POST['imei']);
    
    $stmt = $koneksi->prepare("SELECT * FROM barang WHERE imei = ? AND status = 'READY'");
    $stmt->bind_param("s", $imei);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows > 0){
        $barang = $result->fetch_assoc();
    }else{
        $message = "Barang tidak tersedia atau sudah terjual!";
    }
    $stmt->close();
}

// JUAL
if(isset($_POST['jual'])){
    $imei = $_POST['imei'];
    $harga = (float)$_POST['harga'];
    
    $stmt = $koneksi->prepare("INSERT INTO penjualan (imei, harga, tanggal) VALUES (?, ?, NOW())");
    $stmt->bind_param("sd", $imei, $harga);
    $stmt1 = $koneksi->prepare("UPDATE barang SET status = 'NON READY' WHERE imei = ?");
    $stmt1->bind_param("s", $imei);
    
    if($stmt->execute() && $stmt1->execute()){
        header("Location: kasir.php?success=1");
        exit();
    } else {
        $message = "Error: " . $koneksi->error;
    }
    $stmt->close();
    $stmt1->close();
}
$page_title = "Kasir";
include '../header.php';
?>

<div class="form-section">
    <?php if(isset($_GET['success'])): ?>
        <div style="background: linear-gradient(135deg, #10b981, #059669); color:white; padding:16px; border-radius:12px; margin-bottom:24px;">
            Berhasil dijual!
        </div>
    <?php endif; ?>
    
    <?php if($message): ?>
        <div class="alert-error"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="input-group">
            <i class="fas fa-barcode-read"></i>
            <input name="imei" placeholder="Scan IMEI / Barcode" required autofocus>
            <button name="scan" class="btn btn-primary" style="margin-left:10px;">Scan Barang</button>
        </div>
    </form>

    <?php if($barang): ?>
        <div class="card" style="margin-top:24px;">
            <h4><i class="fas fa-check-circle"></i> Barang Ditemukan</h4>
            <p><strong><?= htmlspecialchars($barang['nama']) ?></strong></p>
            <p>IMEI: <?= htmlspecialchars($barang['imei']) ?></p>
            <p>Status: <span style="color:#10b981;">READY</span></p>
            
            <form method="post" style="margin-top:20px;">
                <input type="hidden" name="imei" value="<?= $barang['imei'] ?>">
                <div class="input-group">
                    <i class="fas fa-money-bill-wave"></i>
                    <input name="harga" type="number" placeholder="Harga Jual (Rp)" required min="0" step="1000">
                    <button name="jual" class="btn btn-primary" style="margin-left:10px;">Proses Jual</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include '../footer.php'; ?>

