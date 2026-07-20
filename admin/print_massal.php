<?php
session_start();
include '../connection.php';
// Validasi admin
if(!isset($_SESSION['login']) || $_SESSION['level'] != 'admin'){
    header("Location: ../login.php");
    exit();
}
$isPreview = isset($_GET['preview']) && $_GET['preview'] == '1';

// ==================== PASTIKAN KOLOM STATUS CETAK ADA ====================
function ensureKolomCetak($koneksi) {
    $check = mysqli_query($koneksi, "SHOW COLUMNS FROM barang LIKE 'label_dicetak'");
    if ($check && mysqli_num_rows($check) == 0) {
        mysqli_query($koneksi, "ALTER TABLE barang ADD COLUMN label_dicetak TINYINT(1) NOT NULL DEFAULT 0");
        mysqli_query($koneksi, "ALTER TABLE barang ADD COLUMN label_dicetak_at DATETIME NULL");
    }
}
ensureKolomCetak($koneksi);

// ==================== TEMPLATE UKURAN LABEL ====================
// Perbaikan: lebar maksimal untuk printer 58mm adalah ~48mm (printable area)
$templates = [
    '50x20' => ['nama' => 'Kecil (50×20mm)',  'w' => 46, 'h' => 20, 'my' => 0, 'title' => 7, 'sub' => 6, 'imei' => 6, 'bh' => 6],
    '40x30' => ['nama' => 'Sedang (40×30mm)', 'w' => 38, 'h' => 30, 'my' => 0, 'title' => 7, 'sub' => 6, 'imei' => 6, 'bh' => 9],
    '58x40' => ['nama' => 'Besar (58×40mm)',  'w' => 48, 'h' => 40, 'my' => 0, 'title' => 9, 'sub' => 7, 'imei' => 7, 'bh' => 12],
    '30x20' => ['nama' => 'Mini (30×20mm)',   'w' => 28, 'h' => 20, 'my' => 0, 'title' => 6, 'sub' => 5, 'imei' => 5, 'bh' => 5],
];

$templateKey = $_GET['template'] ?? '50x20';
if (!isset($templates[$templateKey])) $templateKey = '50x20';
$T = $templates[$templateKey];

// ==================== PARSE ID + QTY ====================
$idsRaw = $_GET['ids'] ?? '';
if (!$idsRaw) die("Pilih data dulu");
$idsRaw = preg_replace('/[^0-9,:]/', '', $idsRaw);
$pairs = array_filter(explode(',', $idsRaw), fn($p) => $p !== '');
$qtyMap  = [];
$idOrder = [];

foreach ($pairs as $pair) {
    $parts = explode(':', $pair);
    $id  = intval($parts[0] ?? 0);
    $qty = isset($parts[1]) ? intval($parts[1]) : 1;
    if ($id <= 0) continue;
    if ($qty < 1) $qty = 1;
    if ($qty > 99) $qty = 99;
    if (!isset($qtyMap[$id])) { $qtyMap[$id] = 0; $idOrder[] = $id; }
    $qtyMap[$id] += $qty;
}

if (empty($idOrder)) die("ID tidak valid");
$idList = implode(',', $idOrder);

$query = mysqli_query($koneksi, "SELECT id, nama, imei, warna, internal FROM barang WHERE id IN ($idList)");
if (mysqli_num_rows($query) == 0) die("Barang tidak ditemukan");

$dataById = [];
while ($row = mysqli_fetch_assoc($query)) {
    $dataById[$row['id']] = $row;
}

$totalLabel = array_sum($qtyMap);

// =================== CATAT KE FILE LOG =====================
if (!$isPreview) {
    $logFile = __DIR__ . '/../logs/print_log.txt';
    $username = $_SESSION['username'] ?? 'Unknown';
    $waktu = date('Y-m-d H:i:s');
    $id_csv_parts = [];
    foreach ($idOrder as $id) { $id_csv_parts[] = $id . ':' . $qtyMap[$id]; }
    $id_csv = implode(',', $id_csv_parts);
    $logLine = "[$waktu] User: $username | Jumlah: $totalLabel | ID: $id_csv\n";
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    
    // Tandai barang sebagai sudah dicetak
    mysqli_query($koneksi, "UPDATE barang SET label_dicetak = 1, label_dicetak_at = NOW() WHERE id IN ($idList)");
}
// ===========================================================
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cetak Label Massal - 58mm</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        /* PERBAIKAN UTAMA: Gunakan tinggi spesifik dari template, bukan 'auto' */
        @page {
            size: 58mm <?= $T['h'] ?>mm;
            margin: 0;
        }
        
        * { box-sizing: border-box; }
        
        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            font-family: Arial, Helvetica, sans-serif;
            width: 58mm;
            overflow: hidden;
        }
        
        .label-grid {
            display: block;
            width: 58mm;
            margin: 0;
            padding: 0;
        }
        
        .label-page {
            width: <?= $T['w'] ?>mm;
            height: <?= $T['h'] ?>mm;
            /* Hapus margin vertikal untuk mencegah drift */
            margin: 0 auto;
            padding: 0.5mm 1mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            text-align: center;
            font-size: 0;
            page-break-inside: avoid;
            break-inside: avoid;
            page-break-after: always; /* PENTING: Paksa ganti halaman setiap label */
            overflow: hidden;
        }
        
        /* Hilangkan page-break pada label terakhir */
        .label-page:last-child {
            page-break-after: auto;
        }
        
        @media screen {
            body {
                background: #e5e5e5;
                padding: 5mm 0 60px;
            }
            .label-page {
                border: 0.5px dashed #aaa;
                background: #fff;
            }
        }
        
        .title {
            font-size: <?= $T['title'] ?>pt;
            font-weight: bold;
            line-height: 1.1;
            margin: 0;
            max-width: <?= $T['w'] - 2 ?>mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sub {
            font-size: <?= $T['sub'] ?>pt;
            font-weight: normal;
            color: #333;
            margin: 0;
            max-width: <?= $T['w'] - 2 ?>mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .barcode-wrap {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            line-height: 0;
            margin: 0.5mm 0;
        }
        
        svg.barcode {
            width: <?= $T['w'] - 4 ?>mm;
            height: <?= $T['bh'] ?>mm;
            max-width: 100%;
        }
        
        .imei {
            font-size: <?= $T['imei'] ?>pt;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin: 0;
            color: #000;
            max-width: <?= $T['w'] - 2 ?>mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        @media print {
            html, body {
                width: 58mm;
                margin: 0;
                padding: 0;
                background: #fff;
            }
            .label-grid { margin: 0; width: 58mm; }
            .label-page {
                border: none !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .preview-toolbar { display: none !important; }
        }
        
        .preview-toolbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 999;
            background: #1E4A7A;
            color: #fff;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: Arial, sans-serif;
            font-size: 12px;
            gap: 8px;
        }
        
        .preview-toolbar button {
            border: none;
            border-radius: 20px;
            padding: 6px 14px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
        }
        
        .btn-confirm-print { background: #10B981; color: #fff; }
        .btn-close-preview { background: rgba(255,255,255,0.18); color: #fff; }
    </style>
</head>
<body>
    <?php if ($isPreview): ?>
    <div class="preview-toolbar">
        <span>Preview &middot; <?= $totalLabel ?> label &middot; Template: <?= htmlspecialchars($T['nama']) ?></span>
        <span>
            <button class="btn-close-preview" type="button" onclick="parent.postMessage('label-preview-close','*')">Tutup</button>
            <button class="btn-confirm-print" type="button" onclick="parent.postMessage('label-preview-confirm-print','*')">Cetak Sekarang</button>
        </span>
    </div>
    <div style="height:42px;"></div>
    <?php endif; ?>
    
    <div class="label-grid">
        <?php foreach ($idOrder as $id):
            if (!isset($dataById[$id])) continue;
            $data = $dataById[$id];
            $qty  = $qtyMap[$id];
            for ($i = 0; $i < $qty; $i++): ?>
        <div class="label-page">
            <div class="title"><?= htmlspecialchars($data['nama']) ?></div>
            <div class="sub"><?= htmlspecialchars($data['warna']) ?> | <?= htmlspecialchars($data['internal']) ?></div>
            <div class="barcode-wrap">
                <svg class="barcode" data-value="<?= htmlspecialchars($data['imei']) ?>"></svg>
            </div>
            <div class="imei"><?= htmlspecialchars($data['imei']) ?></div>
        </div>
        <?php endfor;
        endforeach; ?>
    </div>
    
    <script>
        function renderBarcodes() {
            document.querySelectorAll('.barcode').forEach(el => {
                try {
                    JsBarcode(el, el.getAttribute('data-value'), {
                        format: "CODE128",
                        displayValue: false,
                        height: 30,
                        width: 1.2,
                        margin: 0,
                        background: "#ffffff",
                        lineColor: "#000000"
                    });
                } catch (err) {
                    console.warn('Barcode error:', err);
                    el.style.display = 'none';
                }
            });
        }
        
        const isPreview = <?= $isPreview ? 'true' : 'false' ?>;
        
        window.addEventListener('load', () => {
            renderBarcodes();
            if (!isPreview) {
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        window.print();
                    });
                });
            }
        });
        
        window.addEventListener('afterprint', () => {
            try {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage('label-print-done', '*');
                }
            } catch (e) { /* abaikan */ }
        });
    </script>
</body>
</html>