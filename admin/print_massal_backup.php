<?php
include '../connection.php';

$ids = $_GET['ids'] ?? '';
if (!$ids) die("Pilih data dulu");

$ids = preg_replace('/[^0-9,]/', '', $ids);
$idArray = array_filter(explode(',', $ids), 'is_numeric');
if (empty($idArray)) die("ID tidak valid");

$idList = implode("','", $idArray);
$query = mysqli_query($koneksi, "SELECT nama, tipe, imei, warna, internal FROM barang WHERE id IN ('$idList')");

if (mysqli_num_rows($query) == 0) die("Barang tidak ditemukan");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cetak Label Massal</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        :root {
            /* Ukuran label */
            --label-width: 50mm;
            --label-height: 30mm;
            
            /* Jarak antar label */
            --gap-x: 3mm;
            --gap-y: 3mm;
            
            /* Margin kertas */
            --margin-left: 5mm;
            --margin-top: 5mm;
            
            /* Jumlah kolom */
            --cols: 3;
        }

        body {
            margin: 0;
            padding: 0;
            background: white;
            font-family: Arial, sans-serif;
        }

        /* Grid container */
        .label-grid {
            display: grid;
            grid-template-columns: repeat(var(--cols), var(--label-width));
            grid-auto-rows: var(--label-height);
            gap: var(--gap-y) var(--gap-x);
            width: fit-content;
            margin-left: var(--margin-left);
            margin-top: var(--margin-top);
            
            /* Pastikan tidak terpotong saat print */
            page-break-inside: avoid;
        }

        /* Setiap label */
        .label-page {
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            box-sizing: border-box;
            padding: 1mm;
            border: 0.5px dashed #ccc; /* opsional, bisa dihapus */
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .label-page {
                border: none; /* jangan tampilkan garis bantu */
            }
        }

        .title {
            font-size: 8pt;
            font-weight: bold;
            line-height: 1.1;
        }
        .sub {
            font-size: 6pt;
            margin: 1pt 0;
        }
        .imei {
            font-size: 7pt;
            font-weight: bold;
            font-family: 'Courier New', monospace;
        }
        svg {
            width: 44mm;
            height: 12mm;
        }
    </style>
</head>
<body>

<div class="label-grid">
    <?php while($data = mysqli_fetch_assoc($query)): ?>
        <div class="label-page">
            <div class="title"><?= htmlspecialchars($data['nama']) ?> <?= htmlspecialchars($data['tipe']) ?></div>
            <div class="sub"><?= htmlspecialchars($data['warna']) ?> | <?= htmlspecialchars($data['internal']) ?></div>
            <svg class="barcode" data-value="<?= htmlspecialchars($data['imei']) ?>"></svg>
            <div class="imei"><?= htmlspecialchars($data['imei']) ?></div>
        </div>
    <?php endwhile; ?>
</div>

<script>
    document.querySelectorAll('.barcode').forEach(el => {
        try {
            JsBarcode(el, el.getAttribute('data-value'), {
                format: "CODE128",
                displayValue: false,
                height: 40,
                margin: 0
            });
        } catch (err) {
            el.style.display = 'none';
        }
    });

    window.onload = () => {
        setTimeout(() => {
            window.print();
        }, 500);
    };
</script>
</body>
</html>