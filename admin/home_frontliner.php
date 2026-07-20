<?php
session_start();
if(!isset($_SESSION['login']) || $_SESSION['level'] != 'frontliner'){
    header("Location: ../login.php");
    exit();
}

include '../connection.php';

// ==================== DATA STOCK BARANG ====================
$stock_ready = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM barang WHERE status = 'READY'"))['total'];
$stock_non_ready = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM barang WHERE status = 'NON READY'"))['total'];
$total_stock = $stock_ready + $stock_non_ready;

// Ambil data barang untuk ditampilkan
$limit_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit_per_page;

$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$search_barang = isset($_GET['search_barang']) ? $_GET['search_barang'] : '';

$where_clause = "WHERE 1=1";
if($filter_status) {
    $where_clause .= " AND status = '$filter_status'";
}
if($search_barang) {
    $where_clause .= " AND (nama LIKE '%$search_barang%' OR imei LIKE '%$search_barang%' OR tipe LIKE '%$search_barang%')";
}

$query_barang = "SELECT * FROM barang $where_clause ORDER BY id_barang DESC LIMIT $offset, $limit_per_page";
$data_barang = mysqli_query($koneksi, $query_barang);

$query_total = "SELECT COUNT(*) as total FROM barang $where_clause";
$total_barang = mysqli_fetch_assoc(mysqli_query($koneksi, $query_total))['total'];
$total_pages = ceil($total_barang / $limit_per_page);

// ==================== DATA PENJUALAN ====================
// Penjualan Hari Ini
$penjualan_hari = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT 
        COUNT(*) as jumlah,
        SUM(harga) as total
    FROM penjualan 
    WHERE DATE(tanggal) = CURDATE()
"));

// Penjualan Bulan Ini
$penjualan_bulan = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT 
        COUNT(*) as jumlah,
        SUM(harga) as total
    FROM penjualan 
    WHERE MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())
"));

// Penjualan Tahun Ini
$penjualan_tahun = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT 
        COUNT(*) as jumlah,
        SUM(harga) as total
    FROM penjualan 
    WHERE YEAR(tanggal) = YEAR(CURDATE())
"));

// ==================== DATA STOCK OUT ====================
// Stock out adalah barang yang sudah terjual (NON READY)
$stock_out_total = $stock_non_ready;

// 7 hari terakhir stock out
$stock_out_7hari = mysqli_query($koneksi, "
    SELECT 
        DATE(tanggal) as tgl,
        COUNT(*) as jumlah
    FROM penjualan 
    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(tanggal)
    ORDER BY tgl ASC
");

$stock_out_labels = [];
$stock_out_values = [];
for($i = 6; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i days"));
    $label = date('d M', strtotime($tgl));
    $stock_out_labels[] = $label;
    
    $found = false;
    mysqli_data_seek($stock_out_7hari, 0);
    while($row = mysqli_fetch_assoc($stock_out_7hari)) {
        if($row['tgl'] == $tgl) {
            $stock_out_values[] = (int)$row['jumlah'];
            $found = true;
            break;
        }
    }
    if(!$found) {
        $stock_out_values[] = 0;
    }
}

// Data barang yang sudah terjual (stock out) untuk tabel
$query_stock_out = "SELECT p.*, b.nama as nama_barang, b.tipe, b.warna 
                    FROM penjualan p 
                    LEFT JOIN barang b ON p.imei = b.imei 
                    ORDER BY p.tanggal DESC 
                    LIMIT 20";
$data_stock_out = mysqli_query($koneksi, $query_stock_out);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Inventory - Happy Cell</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f0f4f9 0%, #e8edf5 100%);
            min-height: 100vh;
        }

        /* NAVBAR DONGKER */
        .navbar-dongker {
            background: linear-gradient(135deg, #0A192F 0%, #0F2A3F 100%);
            padding: 1rem 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            background: linear-gradient(135deg, #FFFFFF 0%, #B0C4DE 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent !important;
        }

        .navbar-brand i {
            background: none;
            -webkit-background-clip: unset;
            background-clip: unset;
            color: #5D9BEC;
            margin-right: 8px;
        }

        .user-info {
            background: rgba(255,255,255,0.12);
            padding: 8px 18px;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .btn-logout {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 40px;
            padding: 6px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-logout:hover {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem 2rem;
        }

        /* Stat Cards */
        .stat-card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            border: 1px solid rgba(30, 74, 122, 0.1);
            box-shadow: 0 10px 25px -5px rgba(10, 25, 47, 0.08);
            transition: all 0.3s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 35px -10px rgba(10, 25, 47, 0.15);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .stat-icon-ready {
            background: linear-gradient(135deg, #059669 0%, #10B981 100%);
        }

        .stat-icon-nonready {
            background: linear-gradient(135deg, #DC2626 0%, #EF4444 100%);
        }

        .stat-icon-sales {
            background: linear-gradient(135deg, #0A192F 0%, #1E4A7A 100%);
        }

        .stat-icon-out {
            background: linear-gradient(135deg, #D97706 0%, #F59E0B 100%);
        }

        .stat-icon i {
            font-size: 1.5rem;
            color: white;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0A192F;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #5F7F9E;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .stat-sub {
            font-size: 0.75rem;
            color: #8AA9C9;
            margin-top: 0.5rem;
        }

        /* Card Modern */
        .card-modern {
            background: #FFFFFF;
            border-radius: 28px;
            border: 1px solid rgba(30, 74, 122, 0.1);
            box-shadow: 0 20px 40px -12px rgba(10, 25, 47, 0.1);
            overflow: hidden;
            margin-bottom: 1.8rem;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #F8FAFE 0%, #FFFFFF 100%);
            padding: 1.25rem 1.8rem;
            border-bottom: 1px solid rgba(30, 74, 122, 0.08);
        }

        .card-header-custom h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            color: #0A192F;
        }

        /* Filter Section */
        .filter-section {
            background: #F8FAFE;
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-control-search, .select-custom {
            border: 1.5px solid #E2EAF3;
            border-radius: 50px;
            padding: 0.7rem 1.2rem;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .form-control-search:focus, .select-custom:focus {
            border-color: #1E4A7A;
            box-shadow: 0 0 0 3px rgba(30, 74, 122, 0.1);
            outline: none;
        }

        .btn-dongker {
            background: linear-gradient(135deg, #0A192F 0%, #1E4A7A 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.7rem 1.8rem;
            border-radius: 50px;
            transition: all 0.2s;
        }

        .btn-dongker:hover {
            background: linear-gradient(135deg, #0F2A3F 0%, #25669B 100%);
            transform: translateY(-2px);
            color: white;
        }

        .btn-outline-dongker {
            border: 1.5px solid #1E4A7A;
            background: transparent;
            color: #1E4A7A;
            font-weight: 600;
            padding: 0.7rem 1.8rem;
            border-radius: 50px;
            transition: all 0.2s;
        }

        .btn-outline-dongker:hover {
            background: rgba(30, 74, 122, 0.08);
            transform: translateY(-2px);
        }

        /* Table */
        .table-custom thead th {
            background: #0A192F;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 1rem;
        }

        .table-custom tbody td {
            padding: 1rem;
            vertical-align: middle;
            color: #1A2C3E;
            border-bottom: 1px solid #EEF2F7;
        }

        .table-custom tbody tr:hover {
            background: #F8FAFE;
        }

        /* Badge */
        .badge-ready {
            background: #10B981;
            color: white;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge-nonready {
            background: #EF4444;
            color: white;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Chart Container */
        .chart-container {
            padding: 1rem;
            height: 280px;
        }

        /* Pagination */
        .pagination-custom {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        .page-link-custom {
            padding: 0.5rem 1rem;
            border-radius: 12px;
            background: white;
            border: 1px solid #E2EAF3;
            color: #1E4A7A;
            text-decoration: none;
            transition: all 0.2s;
        }

        .page-link-custom:hover, .page-link-custom.active {
            background: #1E4A7A;
            color: white;
            border-color: #1E4A7A;
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }
            .stat-value {
                font-size: 1.3rem;
            }
            .chart-container {
                height: 200px;
            }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-dongker px-3">
    <span class="navbar-brand">
        <i class="fas fa-chart-line"></i> Happy Cell · Dashboard Inventory
    </span>
    <div class="d-flex align-items-center gap-3">
        <div class="user-info">
            <i class="fas fa-user-circle"></i> <?= $_SESSION['username'] ?? 'Admin' ?>
        </div>
        <a href="../logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>

<div class="main-container">
    <!-- ==================== SECTION 1: STOCK BARANG ==================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon-ready">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?= $stock_ready ?></div>
                <div class="stat-label">Stock Ready (Tersedia)</div>
                <div class="stat-sub">Barang siap jual</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon-nonready">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-value"><?= $stock_non_ready ?></div>
                <div class="stat-label">Stock Non Ready (Terjual)</div>
                <div class="stat-sub">Barang sudah keluar</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon-out">
                    <i class="fas fa-box-open"></i>
                </div>
                <div class="stat-value"><?= $total_stock ?></div>
                <div class="stat-label">Total Seluruh Stock</div>
                <div class="stat-sub">Semua barang di database</div>
            </div>
        </div>
    </div>

    <!-- Tabel Stock Barang -->
    <div class="card-modern">
        <div class="card-header-custom d-flex flex-wrap align-items-center justify-content-between">
            <h5 class="mb-0">
                <i class="fas fa-boxes me-2" style="color: #1E4A7A;"></i>
                Data Stock Barang
            </h5>
            <a href="stock_in.php" class="btn-dongker mt-2 mt-sm-0">
                <i class="fas fa-plus-circle me-2"></i>Tambah Barang
            </a>
        </div>
        <div class="p-4">
            <!-- Filter -->
            <div class="filter-section">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <input type="text" name="search_barang" class="form-control-search w-100" 
                               placeholder="Cari nama, IMEI, atau tipe..." 
                               value="<?= htmlspecialchars($search_barang) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="filter_status" class="select-custom w-100">
                            <option value="">Semua Status</option>
                            <option value="READY" <?= $filter_status == 'READY' ? 'selected' : '' ?>>READY</option>
                            <option value="NON READY" <?= $filter_status == 'NON READY' ? 'selected' : '' ?>>NON READY</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn-dongker">
                                <i class="fas fa-search me-2"></i>Cari
                            </button>
                            <a href="dashboard_inventory.php" class="btn-outline-dongker">
                                <i class="fas fa-undo-alt me-2"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabel -->
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>IMEI</th>
                            <th>Nama Barang</th>
                            <th>Tipe</th>
                            <th>Warna</th>
                            <th>Internal</th>
                            <th>Supplier</th>
                            <th>Harga Modal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($data_barang) > 0): 
                            $no = $offset + 1;
                            while($row = mysqli_fetch_assoc($data_barang)):
                        ?>
                         hilab
                            <td><?= $no++ ?></td>
                            <td><code><?= htmlspecialchars($row['imei']) ?></code></td>
                            <td><strong><?= htmlspecialchars($row['nama']) ?></strong></td>
                            <td><?= htmlspecialchars($row['tipe']) ?></td>
                            <td><?= htmlspecialchars($row['warna']) ?></td>
                            <td><?= htmlspecialchars($row['internal']) ?> GB</td>
                            <td><?= htmlspecialchars($row['supplier']) ?></td>
                            <td>Rp <?= number_format($row['modal'], 0, ',', '.') ?></td>
                            <td>
                                <span class="<?= $row['status'] == 'READY' ? 'badge-ready' : 'badge-nonready' ?>">
                                    <?= $row['status'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <i class="fas fa-box-open fa-2x mb-2 d-block" style="color: #B8CCE4;"></i>
                                Tidak ada data barang
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pagination-custom">
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>&filter_status=<?= urlencode($filter_status) ?>&search_barang=<?= urlencode($search_barang) ?>" 
                       class="page-link-custom <?= $i == $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ==================== SECTION 2: PENJUALAN PER HARI/BULAN/TAHUN ==================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon-sales">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-value">Rp <?= number_format($penjualan_hari['total'] ?? 0, 0, ',', '.') ?></div>
                <div class="stat-label">Penjualan Hari Ini</div>
                <div class="stat-sub"><?= $penjualan_hari['jumlah'] ?? 0 ?> transaksi</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon-sales">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-value">Rp <?= number_format($penjualan_bulan['total'] ?? 0, 0, ',', '.') ?></div>
                <div class="stat-label">Penjualan Bulan Ini</div>
                <div class="stat-sub"><?= $penjualan_bulan['jumlah'] ?? 0 ?> transaksi</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon-sales">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value">Rp <?= number_format($penjualan_tahun['total'] ?? 0, 0, ',', '.') ?></div>
                <div class="stat-label">Penjualan Tahun Ini</div>
                <div class="stat-sub"><?= $penjualan_tahun['jumlah'] ?? 0 ?> transaksi</div>
            </div>
        </div>
    </div>

    <!-- ==================== SECTION 3: STOCK OUT (BARANG TERJUAL) ==================== -->
    <div class="card-modern">
        <div class="card-header-custom">
            <h5 class="mb-0">
                <i class="fas fa-chart-bar me-2" style="color: #1E4A7A;"></i>
                Grafik Stock Out (7 Hari Terakhir)
            </h5>
            <p class="text-muted small mb-0 mt-1">Jumlah barang yang terjual per hari</p>
        </div>
        <div class="chart-container">
            <canvas id="stockOutChart"></canvas>
        </div>
    </div>

    <!-- Tabel Stock Out (Barang Terjual) -->
    <div class="card-modern">
        <div class="card-header-custom">
            <h5 class="mb-0">
                <i class="fas fa-sign-out-alt me-2" style="color: #1E4A7A;"></i>
                Riwayat Stock Out (Barang Terjual)
            </h5>
            <p class="text-muted small mb-0 mt-1">Daftar barang yang sudah keluar / terjual</p>
        </div>
        <div class="p-4">
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Transaksi</th>
                            <th>IMEI</th>
                            <th>Nama Barang</th>
                            <th>Tipe</th>
                            <th>Warna</th>
                            <th>Harga Jual</th>
                            <th>Tanggal Terjual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no_stock_out = 1;
                        if(mysqli_num_rows($data_stock_out) > 0):
                            while($row = mysqli_fetch_assoc($data_stock_out)):
                        ?>
                        <tr>
                            <td><?= $no_stock_out++ ?></td>
                            <td><span class="badge bg-secondary bg-opacity-10 text-dark px-3 py-2 rounded-pill">#<?= $row['id'] ?></span></td>
                            <td><code><?= htmlspecialchars($row['imei']) ?></code></td>
                            <td><strong><?= htmlspecialchars($row['nama_barang'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars($row['tipe'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['warna'] ?? '-') ?></td>
                            <td class="fw-semibold" style="color:#1E4A7A;">Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                            <td><?= date('d/m/Y H:i:s', strtotime($row['tanggal'])) ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-box-open fa-2x mb-2 d-block" style="color: #B8CCE4;"></i>
                                Belum ada data penjualan / stock out
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-3">
                <a href="penjualan.php" class="btn-outline-dongker">
                    <i class="fas fa-arrow-right me-2"></i>Lihat Semua Penjualan
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Grafik Stock Out (7 Hari Terakhir)
    const stockOutLabels = <?= json_encode($stock_out_labels) ?>;
    const stockOutValues = <?= json_encode($stock_out_values) ?>;

    const ctx = document.getElementById('stockOutChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: stockOutLabels,
            datasets: [{
                label: 'Jumlah Barang Terjual',
                data: stockOutValues,
                backgroundColor: 'rgba(30, 74, 122, 0.7)',
                borderRadius: 8,
                borderColor: '#0A192F',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { font: { family: 'Inter', size: 12 } }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.raw + ' barang terjual';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, font: { size: 11 } },
                    grid: { color: '#E2EAF3' }
                },
                x: {
                    ticks: { font: { size: 11 } },
                    grid: { display: false }
                }
            }
        }
    });
</script>
</body>
</html>