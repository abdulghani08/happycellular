<?php
session_start();
if(!isset($_SESSION['login']) || $_SESSION['level'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

include '../connection.php';

// ======================= PAGINATION & FILTER =======================
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build WHERE clause
$where = "1=1";
if (!empty($search)) {
    $where .= " AND (p.imei LIKE '%$search%' OR b.nama LIKE '%$search%' OR b.tipe LIKE '%$search%' OR b.warna LIKE '%$search%')";
}
if (!empty($start_date) && !empty($end_date)) {
    $where .= " AND DATE(p.tanggal) BETWEEN '$start_date' AND '$end_date'";
} elseif (!empty($start_date)) {
    $where .= " AND DATE(p.tanggal) >= '$start_date'";
} elseif (!empty($end_date)) {
    $where .= " AND DATE(p.tanggal) <= '$end_date'";
}

// Total records
$total_query = "SELECT COUNT(*) as total FROM penjualan p JOIN barang b ON p.imei = b.imei WHERE $where";
$total_res = mysqli_query($koneksi, $total_query);
$total_row = mysqli_fetch_assoc($total_res);
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// Query data
$query = "
    SELECT p.*, 
           b.nama, 
           b.tipe, 
           b.warna, 
           b.internal, 
           b.supplier 
    FROM penjualan p 
    JOIN barang b ON p.imei = b.imei 
    WHERE $where
    ORDER BY p.tanggal DESC
    LIMIT $start, $limit
";
$result = mysqli_query($koneksi, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Data Penjualan · Happy Cellular</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-dark: #0A192F;
            --primary-blue: #1E4A7A;
            --accent-blue: #5D9BEC;
            --bg-body: #f4f7fb;
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-dark);
            padding-bottom: 2rem;
        }

        .navbar-custom {
            background: var(--primary-dark);
            padding: 0.9rem 1rem;
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
            font-size: 1.2rem;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .navbar-logo {
            width: 30px;
            height: 30px;
            object-fit: contain;
            border-radius: 6px;
        }

        .user-badge {
            background: rgba(255,255,255,0.1);
            padding: 6px 14px;
            border-radius: 40px;
            color: white;
            font-weight: 500;
            font-size: 0.8rem;
            border: 1px solid rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .main-wrapper {
            max-width: 100%;
            margin: 1rem auto;
            padding: 0 1rem;
        }

        .card-table {
            background: white;
            border-radius: 20px;
            border: 1px solid rgba(30, 74, 122, 0.08);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .filter-box {
            background: #F8FAFC;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #E2E8F0;
        }

        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid #CBD5E1;
            padding: 0.75rem;
            font-size: 0.9rem;
            width: 100%;
        }

        /* Tabel Responsif - Gunakan scroll horizontal */
        .table-responsive-custom {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 1rem;
        }

        .data-table {
            width: 100%;
            min-width: 700px;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .data-table thead th {
            background: #F8FAFD;
            color: #5c6f87;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #E2E8F0;
            padding: 0.8rem;
            white-space: nowrap;
        }

        .data-table tbody td {
            padding: 0.8rem;
            font-weight: 500;
            color: #2d3e5f;
            vertical-align: middle;
            border-bottom: 1px solid #EFF2F9;
        }

        .imei-code {
            font-family: monospace;
            background: #f0f4fa;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--primary-blue);
            white-space: nowrap;
        }

        .badge-income {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 700;
            white-space: nowrap;
        }

        .product-name {
            font-weight: 700;
            color: var(--primary-dark);
        }

        .pagination {
            margin-top: 1.5rem;
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            min-width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 30px;
            text-decoration: none;
            background: white;
            border: 1px solid #E2E8F0;
            color: var(--text-dark);
            font-weight: 500;
            transition: all 0.2s;
        }

        .pagination a:active {
            transform: scale(0.95);
            background: var(--primary-dark);
            color: white;
        }

        .pagination .active {
            background: var(--primary-dark);
            color: white;
            border-color: var(--primary-dark);
        }

        /* Responsive Tablet & Desktop */
        @media (min-width: 768px) {
            .main-wrapper {
                max-width: 1200px;
                margin: 2rem auto;
                padding: 0 1.5rem;
            }
            .card-table {
                padding: 1.5rem;
            }
            .filter-box .row {
                align-items: flex-end;
            }
            .btn-primary {
                padding: 0.6rem 1rem;
                width: auto;
            }
        }

        /* Mobile adjustments */
        @media (max-width: 767px) {
            .filter-box .row > div {
                margin-bottom: 0.75rem;
            }
            .btn-primary {
                width: 100%;
                margin-top: 0.5rem;
                padding: 0.75rem;
            }
            .navbar-brand span {
                font-size: 0.9rem;
            }
            .user-badge {
                display: none;
            }
            .data-table {
                min-width: 650px;
            }
        }
    </style>
</head>
<body>

<nav class="navbar-custom">
    <a href="dashboard.php" class="navbar-brand">
        <img src="/../asset/happy.png" alt="Happy Cell Logo" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=0A192F&color=fff'">
        <span>Happy Cell</span>
    </a>
    <div class="user-badge">
        <i class="fas fa-user-shield text-info"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
    </div>
</nav>

<div class="main-wrapper">
    <div class="card-table">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h5 class="fw-bold mb-0"><i class="fas fa-shopping-cart me-2"></i>Data Penjualan</h5>
            <a href="laporan_keuangan.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="fas fa-chart-line me-1"></i> Grafik
            </a>
        </div>

        <!-- Filter Box -->
        <div class="filter-box">
            <form method="GET" class="row g-2">
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted">🔍 Cari</label>
                    <input type="text" name="search" class="form-control" placeholder="IMEI, nama, tipe..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold text-muted">Dari</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold text-muted">Sampai</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">
                        <i class="fas fa-filter me-1"></i> Terapkan
                    </button>
                </div>
            </form>
        </div>

        <!-- Responsive Table (Scroll horizontal pada mobile) -->
        <div class="table-responsive-custom">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>IMEI</th>
                        <th>Nama Barang</th>
                        <th>Tipe / Warna</th>
                        <th>Internal</th>
                        <th>Supplier</th>
                        <th class="text-end">Harga Jual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td class="text-muted"><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                                <td><span class="imei-code"><?= htmlspecialchars($row['imei']) ?></span></td>
                                <td class="product-name"><?= htmlspecialchars($row['nama']) ?></td>
                                <td><?= htmlspecialchars($row['tipe']) ?> / <?= htmlspecialchars($row['warna']) ?></td>
                                <td><?= htmlspecialchars($row['internal']) ?> GB</td>
                                <td><?= htmlspecialchars($row['supplier']) ?></td>
                                <td class="text-end"><span class="badge-income">Rp <?= number_format($row['harga_jual'], 0, ',', '.') ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Belum ada data penjualan</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=1&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"><i class="fas fa-angle-double-left"></i></a>
                <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"><i class="fas fa-angle-left"></i></a>
            <?php endif; ?>

            <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <?php if($i == $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"><i class="fas fa-angle-right"></i></a>
                <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"><i class="fas fa-angle-double-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>