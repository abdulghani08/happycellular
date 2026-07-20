<?php
session_start();
if(!isset($_SESSION['login']) || $_SESSION['level'] != 'frontliner'){
    header("Location: ../login.php");
    exit();
}
include '../connection.php';

$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// Ambil daftar status yang tersedia
$statusQuery = "SELECT DISTINCT status FROM barang WHERE status IS NOT NULL AND status != '' ORDER BY status ASC";
$statusResult = $koneksi->query($statusQuery);
$availableStatuses = [];
while($s = $statusResult->fetch_assoc()) {
    $availableStatuses[] = $s['status'];
}

// Query produk
$query = "SELECT * FROM barang WHERE 1=1";
$params = [];
$types = '';

if($status){
    $query .= " AND status = ?";
    $params[] = $status;
    $types .= 's';
}
if($search){
    $query .= " AND (nama LIKE ? OR imei LIKE ? OR tipe LIKE ? OR supplier LIKE ? OR warna LIKE ? OR internal LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like, $like, $like);
    $types .= 'ssssss';
}
$query .= " ORDER BY id DESC";

$stmt = mysqli_prepare($koneksi, $query);
if(!empty($params)){
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fungsi pembantu untuk membuat detail produk
function buildProductHTML($row) {
    $dataDetail = [
        'id' => $row['id'],
        'nama' => $row['nama'],
        'tipe' => $row['tipe'],
        'warna' => $row['warna'],
        'internal' => $row['internal'],
        'imei' => $row['imei'],
        'status' => $row['status'],
        'tanggal' => date('d M Y', strtotime($row['tanggal'])),
        'supplier' => $row['supplier'] ?? '-',
        'keterangan' => $row['keterangan'] ?? '',
        'harga_jual' => number_format($row['harga_jual'], 0, ',', '.'),
        'gambar_base64' => !empty($row['gambar']) ? base64_encode($row['gambar']) : null
    ];
    $json = htmlspecialchars(json_encode($dataDetail), ENT_QUOTES, 'UTF-8');
    $hasGambar = !empty($row['gambar']);
    
    $statusLower = strtolower(trim($row['status']));
    $statusColor = $statusLower === 'ready' ? 'text-success' : ($statusLower === 'no ready' ? 'text-danger' : 'text-warning');
    
    $supplierLower = strtolower($row['supplier']);
    if (strpos($supplierLower, 'customer') !== false) {
        $supBadge = 'bg-primary text-white';
        $borderClass = 'border-primary';
    } elseif (strpos($supplierLower, 'tukar') !== false) {
        $supBadge = 'bg-warning text-dark';
        $borderClass = 'border-warning';
    } else {
        $supBadge = 'bg-success text-white';
        $borderClass = 'border-success';
    }
    
    ob_start();
    ?>
    <div class="product-item-row <?= $borderClass ?>" data-detail='<?= $json ?>' onclick="showDetail(this)" style="cursor: pointer;">
        <div class="product-img-box">
            <?php if($hasGambar): ?>
                <img src="data:image/jpeg;base64,<?= $dataDetail['gambar_base64'] ?>" alt="<?= htmlspecialchars($row['nama']) ?>" style="width:100%; height:100%; object-fit:cover; border-radius:6px;">
            <?php else: ?>
                <i class="fa-solid fa-mobile-screen fs-4"></i>
            <?php endif; ?>
        </div>
        
        <div class="product-details">
            <div class="product-name-text"><?= htmlspecialchars($row['nama']) ?></div>
            
            <div class="product-info-inline">
                <div class="fw-bold text-dark mb-1">
                    <?= htmlspecialchars($row['tipe']) ?> · <?= htmlspecialchars($row['warna']) ?> · <?= htmlspecialchars($row['internal']) ?>GB
                </div>
                <div class="mb-1">
                    IMEI: <span class="imei-badge"><?= htmlspecialchars($row['imei']) ?></span>
                </div>
                <div class="mt-1">
                    <span class="badge <?= $supBadge ?>" style="font-size: 0.65rem; font-weight:600;">
                        <i class="fa-solid fa-truck-ramp-box me-1"></i> <?= htmlspecialchars($row['supplier']) ?>
                    </span>
                </div>
            </div>
            
            <div class="product-sub-meta mt-1">
                <span class="<?= $statusColor ?> fw-bold"><?= htmlspecialchars($row['status']) ?></span>
            </div>
        </div>
        
        <button class="btn-print-item" onclick="cetakLabel(this, event)" data-detail='<?= $json ?>'>
            <i class="fas fa-print"></i> Label
        </button>
    </div>
    <?php
    return ob_get_clean();
}

// Jika AJAX, kembalikan daftar produk saja
if($ajax) {
    if(mysqli_num_rows($result) > 0){
        while($row = mysqli_fetch_assoc($result)){
            echo buildProductHTML($row);
        }
    } else {
        echo '<div class="text-center py-5 w-100"><i class="fa-solid fa-box-open fa-2x mb-2 text-muted d-block opacity-50"></i><h6 class="text-muted fw-bold">Tidak ada produk ditemukan</h6><p class="text-muted small mb-0">Coba ubah filter atau kata kunci pencarian Anda.</p></div>';
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Stok Barang · Happy Cellular</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    
    <style>
        :root {
            --primary-dark: #0A192F;
            --primary-blue: #1E4A7A;
            --accent-blue: #5D9BEC;
            --bg-body: #f4f7fb;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #E2E8F0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            min-height: 100vh;
            color: var(--text-dark);
            padding-bottom: 2rem;
        }

        .navbar-custom {
            background: var(--primary-dark);
            padding: 0.9rem 2rem;
            box-shadow: 0 10px 30px -10px rgba(0,20,40,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.3rem;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        
        .navbar-logo { width: 32px; height: 32px; object-fit: contain; border-radius: 6px; }

        .btn-back {
            color: white;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 40px;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-back:hover { background: rgba(255,255,255,0.2); color: white; }

        .main-wrapper { max-width: 600px; margin: 2rem auto; padding: 0 1.5rem; }

        .screen-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .search-container { position: relative; margin-bottom: 14px; }

        .search-container i {
            position: absolute; left: 16px; top: 50%;
            transform: translateY(-50%); color: var(--text-muted); font-size: 0.95rem;
        }

        .search-input {
            width: 100%; height: 46px;
            background: white; border: 1px solid var(--border-color); border-radius: 14px;
            padding-left: 44px; padding-right: 16px;
            font-size: 0.85rem; font-weight: 600; color: var(--text-dark);
            transition: all 0.2s ease;
        }

        .search-input:focus { outline: none; border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(30, 74, 122, 0.1); }

        .filter-scroll-wrapper {
            display: flex; gap: 8px; overflow-x: auto; padding-bottom: 5px;
            margin-bottom: 20px; scrollbar-width: none;
        }

        .filter-scroll-wrapper::-webkit-scrollbar { display: none; }

        .filter-pill-label {
            white-space: nowrap; padding: 6px 14px; border-radius: 20px;
            font-size: 0.75rem; font-weight: 700; color: var(--text-muted);
            background: white; cursor: pointer; border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .btn-check:checked + .filter-pill-label {
            background: var(--primary-dark); color: #FFFFFF; border-color: var(--primary-dark);
        }

        .product-list-container {
            display: flex; flex-direction: column; gap: 12px;
            transition: opacity 0.2s ease;
        }

        .product-item-row {
            display: flex; align-items: center; padding: 14px;
            background: white; border-radius: 18px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.03);
            position: relative; transition: all 0.2s;
        }

        .product-item-row.border-primary { border-left: 5px solid #0d6efd !important; }
        .product-item-row.border-warning { border-left: 5px solid #ffc107 !important; }
        .product-item-row.border-success { border-left: 5px solid #198754 !important; }

        .product-item-row:hover { border-color: var(--accent-blue); }

        .product-img-box {
            width: 52px; height: 52px; background: #f1f5ff; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            padding: 6px; margin-right: 14px; flex-shrink: 0;
            color: var(--primary-blue);
        }

        .product-img-box img { max-width: 100%; max-height: 100%; object-fit: contain; }

        .product-details { flex-grow: 1; min-width: 0; padding-right: 40px; }

        .product-name-text {
            font-weight: 700; font-size: 0.9rem; color: var(--text-dark);
            margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .product-info-inline { font-size: 0.75rem; line-height: 1.45; margin-bottom: 4px; color: var(--text-muted); }

        .imei-badge {
            font-family: monospace; background: #F1F5F9; padding: 1px 6px;
            border-radius: 4px; font-weight: 600; color: var(--text-dark); display: inline-block;
        }

        .product-sub-meta { font-size: 0.85rem; font-weight: 700; }

        .btn-print-item {
            position: absolute; right: 10px; bottom: 10px;
            background: var(--primary-dark); color: white;
            border: none; border-radius: 8px; padding: 4px 10px;
            font-size: 0.7rem; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; gap: 4px;
            transition: all 0.2s; z-index: 10;
        }
        
        .btn-print-item:hover { background: var(--primary-blue); }

        .custom-sheet-modal .modal-content {
            border-radius: 24px 24px 0 0 !important; border: none;
        }
        
        .modal-drag-indicator {
            width: 40px; height: 5px; background: #E2E8F0; border-radius: 10px;
            margin: 12px auto 0 auto;
        }

        @media (max-width: 768px) {
            .navbar-custom { padding: 0.8rem 1rem; }
            .navbar-brand span { font-size: 1rem; }
            .main-wrapper { padding: 0 1rem; margin-top: 1.5rem; }
        }

        @media (min-width: 992px) {
            .custom-sheet-modal .modal-dialog {
                position: relative !important; margin: 1.75rem auto !important; top: 5% !important;
            }
            .custom-sheet-modal .modal-content { border-radius: 20px !important; }
            .modal-drag-indicator { display: none; }
        }
    </style>
</head>
<body>

<nav class="navbar-custom">
    <a href="home_frontliner.php" class="navbar-brand">
        <img src="../asset/happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=0A192F&color=fff'">
        <span>Happy Cellular · Stok</span>
    </a>
    <a href="home_frontliner.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Dashboard
    </a>
</nav>

<div class="main-wrapper">
    <h2 class="screen-title">Katalog Stok</h2>

    <form id="filterForm" method="GET" onsubmit="return false;">
        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" id="searchInput" class="search-input" placeholder="Cari nama, IMEI, tipe..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <div class="filter-scroll-wrapper">
            <input type="radio" class="btn-check" name="status" value="" id="statusAll" <?= $status == '' ? 'checked' : '' ?>>
            <label class="filter-pill-label" for="statusAll">Semua</label>

            <?php foreach($availableStatuses as $s): 
                $id = 'status_'.preg_replace('/[^a-zA-Z0-9]/', '_', $s);
            ?>
            <input type="radio" class="btn-check" name="status" value="<?= htmlspecialchars($s) ?>" id="<?= $id ?>" <?= $status == $s ? 'checked' : '' ?>>
            <label class="filter-pill-label" for="<?= $id ?>"><?= htmlspecialchars($s) ?></label>
            <?php endforeach; ?>
        </div>
    </form>

    <div class="product-list-container" id="productListContainer">
        <?php 
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if(mysqli_num_rows($result) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($result)){
                echo buildProductHTML($row);
            } ?>
        <?php else: ?>
            <div class="text-center py-5 w-100">
                <i class="fa-solid fa-box-open fa-2x mb-2 text-muted d-block opacity-50"></i>
                <h6 class="text-muted fw-bold">Tidak ada produk ditemukan</h6>
                <p class="text-muted small mb-0">Coba ubah filter atau kata kunci pencarian Anda.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade custom-sheet-modal" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable fixed-bottom m-0 max-width-none" style="max-width: 480px; margin: 0 auto !important;">
        <div class="modal-content shadow-lg">
            <div class="modal-drag-indicator"></div>
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold m-0" style="font-size:1.1rem; color: var(--primary-dark);">Detail Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div id="modalImage" class="mx-auto rounded-4 bg-light d-flex align-items-center justify-content-center p-3 mb-3" style="width:110px; height:110px; overflow:hidden;"></div>
                    <span id="modalStatusBadge" class="badge px-3 py-2 rounded-pill mb-2"></span>
                    <h4 id="modalNama" class="fw-bold text-dark mb-1" style="font-size:1.2rem;"></h4>
                    <h5 id="modalHargaJual" class="fw-bold" style="color: var(--primary-blue)"></h5>
                </div>

                <div class="card p-3 border-0 bg-light rounded-4 mb-3">
                    <div class="row g-3 style-info-row" style="font-size: 0.85rem;">
                        <div class="col-6"><label class="small text-muted d-block mb-1">Tipe</label><span id="modalTipe" class="fw-bold text-dark"></span></div>
                        <div class="col-6"><label class="small text-muted d-block mb-1">Warna</label><span id="modalWarna" class="fw-bold text-dark"></span></div>
                        <div class="col-6"><label class="small text-muted d-block mb-1">Penyimpanan</label><span id="modalInternal" class="fw-bold text-dark"></span></div>
                        <div class="col-6"><label class="small text-muted d-block mb-1">Tanggal Masuk</label><span id="modalTanggal" class="fw-bold text-dark"></span></div>
                        <div class="col-12"><label class="small text-muted d-block mb-1">Supplier</label><span id="modalSupplier" class="fw-bold text-dark"></span></div>
                        <div class="col-12" id="keteranganContainer" style="display:none;"><label class="small text-muted d-block mb-1">Keterangan</label><span id="modalKeterangan" class="fw-bold text-dark"></span></div>
                    </div>
                </div>

                <div class="p-3 bg-white border border-dashed rounded-4 text-center mb-4" style="border-color: #CBD5E1;">
                    <svg id="barcodeSvg" style="max-width: 100%; height: 50px;"></svg>
                    <div id="modalImei" class="mt-2 font-monospace fw-bold text-secondary small"></div>
                </div>

                <button class="btn btn-dark w-100 rounded-4 py-3 fw-bold shadow-sm mb-2" style="background: var(--primary-dark); border:none;" onclick="cetakLabelDariModal()">
                    <i class="fas fa-print me-2"></i> Cetak Label Inventaris
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const filterForm = document.getElementById('filterForm');
const productContainer = document.getElementById('productListContainer');
let searchTimeout;
let detailModal;
let currentData = {};

document.addEventListener("DOMContentLoaded", function() {
    detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
});

function fetchProducts() {
    productContainer.style.opacity = '0.4';
    const formData = new FormData(filterForm);
    const params = new URLSearchParams(formData);
    params.append('ajax', '1');
    fetch('view_stock.php?' + params.toString())
        .then(response => response.text())
        .then(html => {
            productContainer.innerHTML = html;
            productContainer.style.opacity = '1';
        })
        .catch(err => {
            console.error('Gagal memuat data:', err);
            productContainer.style.opacity = '1';
        });
}

document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { fetchProducts(); }, 500);
});

document.querySelectorAll('input[name="status"]').forEach(radio => {
    radio.addEventListener('change', function() { fetchProducts(); });
});

function showDetail(card) {
    const data = JSON.parse(card.getAttribute('data-detail'));
    currentData = data;

    const imgContainer = document.getElementById('modalImage');
    imgContainer.innerHTML = '';
    
    if (data.gambar_base64) {
        const imgEl = document.createElement('img');
        imgEl.src = 'data:image/jpeg;base64,' + data.gambar_base64;
        imgEl.style.maxWidth = '100%'; imgEl.style.maxHeight = '100%';
        imgEl.style.objectFit = 'contain'; imgEl.style.borderRadius = '8px';
        imgContainer.appendChild(imgEl);
    } else {
        imgContainer.innerHTML = '<i class="fa-solid fa-mobile-screen fa-3x text-muted opacity-50"></i>';
    }

    document.getElementById('modalNama').textContent = data.nama;
    document.getElementById('modalTipe').textContent = data.tipe;
    document.getElementById('modalWarna').textContent = data.warna;
    document.getElementById('modalInternal').textContent = data.internal + ' GB';
    document.getElementById('modalSupplier').textContent = data.supplier;
    document.getElementById('modalHargaJual').textContent = 'Rp ' + data.harga_jual;
    document.getElementById('modalTanggal').textContent = data.tanggal;
    document.getElementById('modalImei').textContent = data.imei;

    if(data.keterangan) {
        document.getElementById('modalKeterangan').textContent = data.keterangan;
        document.getElementById('keteranganContainer').style.display = 'block';
    } else {
        document.getElementById('keteranganContainer').style.display = 'none';
    }

    const badge = document.getElementById('modalStatusBadge');
    badge.textContent = data.status;
    if(data.status === 'READY') {
        badge.className = 'badge bg-success-subtle text-success px-3 py-2';
    } else if(data.status === 'NO READY') {
        badge.className = 'badge bg-danger-subtle text-danger px-3 py-2';
    } else {
        badge.className = 'badge bg-warning-subtle text-warning px-3 py-2';
    }

    try {
        JsBarcode("#barcodeSvg", data.imei, { format: "CODE128", width: 2, height: 50, displayValue: false, lineColor: "#1E293B" });
    } catch(e) {}

    detailModal.show();
}

function cetakLabelDariModal() {
    const dummyBtn = document.createElement('button');
    dummyBtn.setAttribute('data-detail', JSON.stringify(currentData));
    cetakLabel(dummyBtn, { stopPropagation: () => {} });
}

// ==================== CETAK LABEL DENGAN LAYOUT UKURAN REFERENSI ====================
function cetakLabel(btn, e) {
    e.stopPropagation();
    const data = JSON.parse(btn.getAttribute('data-detail'));
    const internalVal = data.internal + 'GB';
    
    const w = window.open('', '_blank', 'width=400,height=300');
    w.document.write(`
        <html>
        <head>
            <title>Label - ${data.nama}</title>
            <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\/script>
            <style>
                @page { size: 58mm auto; margin: 0; }
                * { box-sizing: border-box; }
                html, body {
                    width: 58mm;
                    margin: 0; padding: 0;
                    background: #fff;
                    font-family: Arial, Helvetica, sans-serif;
                }
                .label-page {
                    width: 50mm;
                    height: 20mm;
                    margin: 6.7mm auto;
                    padding: 0.5mm 1mm;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    align-items: center;
                    text-align: center;
                    overflow: hidden;
                    page-break-inside: avoid;
                }
                .title {
                    font-size: 7pt;
                    font-weight: bold;
                    line-height: 1.1;
                    max-width: 48mm;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .sub {
                    font-size: 6pt;
                    color: #333;
                    max-width: 48mm;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .barcode-wrap {
                    width: 100%;
                    display: flex;
                    justify-content: center;
                    line-height: 0;
                }
                svg.barcode {
                    width: 46mm;
                    height: 6mm;
                }
                .imei {
                    font-size: 6pt;
                    font-family: 'Courier New', monospace;
                    font-weight: bold;
                    letter-spacing: 0.5px;
                }
                @media print {
                    html, body { width: 58mm; }
                }
            </style>
        </head>
        <body>
            <div class="label-page">
                <div class="title">${data.nama}</div>
                <div class="sub">${data.warna} | ${internalVal}</div>
                <div class="barcode-wrap">
                    <svg class="barcode" data-value="${data.imei}"></svg>
                </div>
                <div class="imei">${data.imei}</div>
            </div>
            <script>
                try {
                    JsBarcode(".barcode", "${data.imei}", {
                        format: "CODE128",
                        width: 1.2,
                        height: 20,
                        displayValue: false,
                        margin: 0
                    });
                } catch(e) {}
                window.onload = function() {
                    window.print();
                    setTimeout(function(){ window.close(); }, 1000);
                };
            <\/script>
        </body>
        </html>
    `);
    w.document.close();
}
</script>
</body>
</html>