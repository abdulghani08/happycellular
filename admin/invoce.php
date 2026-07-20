<?php
session_start();
require_once '../api/auth_helper.php';
restrictTo(['admin']);
require_once '../connection.php';

// Ambil ID transaksi dari URL
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('<script>alert("ID transaksi tidak valid"); window.close();</script>');
}

// Ambil data penjualan
$stmt = $koneksi->prepare("
    SELECT 
        p.*,
        b.nama AS nama_barang,
        b.tipe,
        b.warna,
        b.internal,
        b.modal,
        so.user AS kasir,
        so.tanggal AS tanggal_transaksi
    FROM penjualan p
    JOIN barang b ON p.imei = b.imei
    LEFT JOIN stock_out so ON p.imei = so.imei
    WHERE p.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('<script>alert("Data transaksi tidak ditemukan"); window.close();</script>');
}

$data = $result->fetch_assoc();
$stmt->close();

// Format data
$noInvoice   = 'INV-' . str_pad($data['id'], 6, '0', STR_PAD_LEFT);
$tanggal     = date('d F Y, H:i', strtotime($data['tanggal_transaksi'] ?? $data['tanggal']));
$namaBarang  = trim($data['nama_barang'] . ' ' . $data['tipe']);
$spek        = trim($data['warna'] . ' · ' . $data['internal']);
$hargaJual   = (int)$data['harga_jual'];
$dp          = (int)($data['dp'] ?? 0);
$sisaBayar   = $hargaJual - $dp;
$metode      = $data['metode_pembayaran'];
$kasir       = $data['kasir'] ?? ($_SESSION['username'] ?? 'Admin');
$namaCustomer = $data['nama_customer'] ?? 'Umum';
$noHp        = $data['no_hp'] ?? '-';
$keterangan  = $data['keterangan'] ?? '';
$laba        = $hargaJual - (int)$data['modal'];

// Generate nomor invoice untuk QR (opsional)
$qrData = $noInvoice . '|' . $data['imei'] . '|' . $hargaJual;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= $noInvoice ?> · Happy Cellular</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dongker: #1E4A7A;
            --primary-soft: #EEF4FA;
            --text-main: #1E293B;
            --text-muted: #64748B;
            --success: #10B981;
            --border: #E2E8F0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #F4F7FA;
            color: var(--text-main);
            min-height: 100vh;
            padding: 20px 0;
        }

        /* ACTION BAR (tidak tercetak) */
        .action-bar {
            max-width: 800px;
            margin: 0 auto 20px;
            padding: 0 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .btn-action {
            padding: 10px 20px;
            border-radius: 12px;
            border: none;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-family: inherit;
        }
        .btn-print {
            background: var(--primary-dongker);
            color: white;
        }
        .btn-print:hover { background: #163a61; transform: translateY(-1px); }
        .btn-back {
            background: white;
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        .btn-back:hover { background: #F8FAFC; }

        /* INVOICE CONTAINER */
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        /* HEADER TOKO */
        .invoice-header {
            background: linear-gradient(135deg, var(--primary-dongker) 0%, #2d5a94 100%);
            color: white;
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .store-info { display: flex; align-items: center; gap: 15px; }
        .store-logo {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: var(--primary-dongker);
            font-size: 1.5rem;
        }
        .store-name { font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px; }
        .store-tagline { font-size: 0.8rem; opacity: 0.85; margin-top: 2px; }
        .store-contact { font-size: 0.75rem; opacity: 0.75; margin-top: 4px; }

        .invoice-title {
            text-align: right;
        }
        .invoice-title-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.8;
            font-weight: 600;
        }
        .invoice-title-number {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-top: 4px;
        }

        /* INVOICE BODY */
        .invoice-body {
            padding: 30px 40px;
        }

        /* META INFO */
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 2px dashed var(--border);
        }
        .meta-block {}
        .meta-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 8px;
        }
        .meta-value {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.5;
        }
        .meta-value.small {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        /* PRODUCT DETAIL */
        .product-section {
            background: #F8FAFC;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid var(--border);
        }
        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }
        .product-name {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .product-spec {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .product-imei {
            background: white;
            border: 1.5px solid var(--primary-dongker);
            color: var(--primary-dongker);
            padding: 8px 14px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }

        .spec-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        .spec-item {
            background: white;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
        }
        .spec-item-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .spec-item-value {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-main);
        }

        /* PAYMENT SUMMARY */
        .payment-summary {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 25px;
        }
        .payment-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
        }
        .payment-row:last-child { border-bottom: none; }
        .payment-row-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .payment-row-value {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .payment-row.total {
            background: var(--primary-soft);
            padding: 18px 20px;
        }
        .payment-row.total .payment-row-label {
            color: var(--primary-dongker);
            font-size: 0.9rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .payment-row.total .payment-row-value {
            color: var(--primary-dongker);
            font-size: 1.4rem;
            font-weight: 800;
        }

        .badge-metode {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary-dongker);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        /* STATUS PAID */
        .status-paid {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #D1FAE5;
            color: #065F46;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        /* FOOTER */
        .invoice-footer {
            background: #F8FAFC;
            padding: 25px 40px;
            text-align: center;
            border-top: 2px dashed var(--border);
        }
        .thank-you {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--primary-dongker);
            margin-bottom: 6px;
        }
        .footer-note {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.6;
            max-width: 500px;
            margin: 0 auto;
        }
        .footer-note strong { color: var(--text-main); }

        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 25px;
            padding-top: 20px;
        }
        .signature-block {
            text-align: center;
        }
        .signature-line {
            border-bottom: 1.5px solid var(--text-main);
            height: 50px;
            margin-bottom: 8px;
        }
        .signature-name {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .signature-role {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* WATERMARK (untuk print) */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 6rem;
            font-weight: 800;
            color: rgba(16, 185, 129, 0.08);
            pointer-events: none;
            z-index: 0;
            letter-spacing: 10px;
        }

        /* PRINT STYLES */
        @media print {
            @page {
                size: A5;
                margin: 10mm;
            }
            body {
                background: white;
                padding: 0;
            }
            .action-bar { display: none !important; }
            .invoice-container {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
            }
            .invoice-header { padding: 15px 20px; }
            .invoice-body { padding: 15px 20px; }
            .invoice-footer { padding: 15px 20px; }
            .store-name { font-size: 1.2rem; }
            .invoice-title-number { font-size: 1.4rem; }
            .product-section { padding: 12px; }
            .product-name { font-size: 1rem; }
            .payment-row.total .payment-row-value { font-size: 1.1rem; }
            .no-print { display: none !important; }
        }

        /* RESPONSIVE */
        @media (max-width: 640px) {
            body { padding: 10px 0; }
            .invoice-header { padding: 20px; flex-direction: column; text-align: center; }
            .invoice-title { text-align: center; }
            .invoice-body { padding: 20px; }
            .meta-grid { grid-template-columns: 1fr; gap: 15px; }
            .spec-grid { grid-template-columns: 1fr; }
            .product-header { flex-direction: column; gap: 10px; }
            .invoice-footer { padding: 20px; }
            .signature-section { grid-template-columns: 1fr; gap: 20px; }
        }
    </style>
</head>
<body>

<!-- ACTION BAR (tidak tercetak) -->
<div class="action-bar no-print">
    <button class="btn-action btn-back" onclick="window.history.back()">
        <i class="fas fa-arrow-left"></i> Kembali
    </button>
    <button class="btn-action btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Cetak Invoice
    </button>
</div>

<!-- INVOICE -->
<div class="invoice-container" style="position: relative;">
    <div class="watermark">PAID</div>

    <!-- HEADER -->
    <div class="invoice-header">
        <div class="store-info">
            <div class="store-logo">HC</div>
            <div>
                <div class="store-name">Happy Cellular</div>
                <div class="store-tagline">Solusi Handphone Terpercaya</div>
                <div class="store-contact">
                    <i class="fas fa-map-marker-alt"></i> Jakarta Pusat · 
                    <i class="fas fa-phone"></i> 0895-0843-8446
                </div>
            </div>
        </div>
        <div class="invoice-title">
            <div class="invoice-title-label">Invoice No.</div>
            <div class="invoice-title-number"><?= $noInvoice ?></div>
        </div>
    </div>

    <!-- BODY -->
    <div class="invoice-body">
        
        <!-- META INFO -->
        <div class="meta-grid">
            <div class="meta-block">
                <div class="meta-label">Tanggal Transaksi</div>
                <div class="meta-value"><?= $tanggal ?></div>
                <div class="meta-value small" style="margin-top:4px;">
                    <i class="fas fa-user-circle"></i> Kasir: <strong><?= htmlspecialchars($kasir) ?></strong>
                </div>
            </div>
            <div class="meta-block" style="text-align: right;">
                <div class="meta-label">Customer</div>
                <div class="meta-value"><?= htmlspecialchars($namaCustomer) ?></div>
                <div class="meta-value small" style="margin-top:4px;">
                    <i class="fas fa-phone"></i> <?= htmlspecialchars($noHp) ?>
                </div>
            </div>
        </div>

        <!-- PRODUCT DETAIL -->
        <div class="product-section">
            <div class="product-header">
                <div>
                    <div class="product-name"><?= htmlspecialchars(strtoupper($namaBarang)) ?></div>
                    <div class="product-spec"><?= htmlspecialchars(strtoupper($spek)) ?></div>
                </div>
                <div class="product-imei">
                    <i class="fas fa-barcode me-1"></i> <?= htmlspecialchars($data['imei']) ?>
                </div>
            </div>
            <div class="spec-grid">
                <div class="spec-item">
                    <div class="spec-item-label">Kondisi</div>
                    <div class="spec-item-value"><?= htmlspecialchars($data['tipe'] ?: 'New') ?></div>
                </div>
                <div class="spec-item">
                    <div class="spec-item-label">Warna</div>
                    <div class="spec-item-value"><?= htmlspecialchars($data['warna']) ?></div>
                </div>
                <div class="spec-item">
                    <div class="spec-item-label">Storage</div>
                    <div class="spec-item-value"><?= htmlspecialchars($data['internal']) ?></div>
                </div>
            </div>
        </div>

        <!-- PAYMENT SUMMARY -->
        <div class="payment-summary">
            <div class="payment-row">
                <div class="payment-row-label">Harga Jual</div>
                <div class="payment-row-value">Rp <?= number_format($hargaJual, 0, ',', '.') ?></div>
            </div>
            <?php if ($dp > 0): ?>
            <div class="payment-row">
                <div class="payment-row-label">Uang Muka (DP)</div>
                <div class="payment-row-value">Rp <?= number_format($dp, 0, ',', '.') ?></div>
            </div>
            <div class="payment-row">
                <div class="payment-row-label">Sisa Pembayaran</div>
                <div class="payment-row-value">Rp <?= number_format($sisaBayar, 0, ',', '.') ?></div>
            </div>
            <?php endif; ?>
            <div class="payment-row">
                <div class="payment-row-label">Metode Pembayaran</div>
                <div class="payment-row-value">
                    <span class="badge-metode">
                        <i class="fas fa-credit-card"></i> <?= htmlspecialchars($metode) ?>
                    </span>
                </div>
            </div>
            <div class="payment-row total">
                <div class="payment-row-label">Total Transaksi</div>
                <div class="payment-row-value">Rp <?= number_format($hargaJual, 0, ',', '.') ?></div>
            </div>
            <div class="payment-row" style="background: #F0FDF4;">
                <div class="payment-row-label" style="color: #065F46;">Status Pembayaran</div>
                <div class="payment-row-value">
                    <span class="status-paid">
                        <i class="fas fa-check-circle"></i> LUNAS
                    </span>
                </div>
            </div>
        </div>

        <?php if (!empty($keterangan)): ?>
        <div style="background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
            <div style="font-size: 0.75rem; font-weight: 700; color: #92400E; text-transform: uppercase; margin-bottom: 4px;">
                <i class="fas fa-sticky-note"></i> Catatan
            </div>
            <div style="font-size: 0.85rem; color: #78350F; font-weight: 600;">
                <?= htmlspecialchars($keterangan) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- SIGNATURE -->
        <div class="signature-section">
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-name"><?= htmlspecialchars($kasir) ?></div>
                <div class="signature-role">Kasir / Penjual</div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-name"><?= htmlspecialchars($namaCustomer) ?></div>
                <div class="signature-role">Customer / Pembeli</div>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="invoice-footer">
        <div class="thank-you">✨ Terima Kasih Atas Kepercayaan Anda! ✨</div>
        <div class="footer-note">
            Barang yang sudah dibeli <strong>tidak dapat dikembalikan</strong> kecuali dalam kondisi rusak dari pabrik.<br>
            Garansi toko berlaku <strong>7 hari</strong> untuk kerusakan internal.<br>
            <span style="font-size: 0.75rem; opacity: 0.7;">Invoice ini dicetak otomatis pada <?= date('d/m/Y H:i') ?></span>
        </div>
    </div>
</div>

<script>
// Auto print saat halaman dibuka (opsional, bisa di-comment jika tidak diinginkan)
// window.onload = function() { setTimeout(() => window.print(), 500); };
</script>
</body>
</html>