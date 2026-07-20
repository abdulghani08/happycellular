<?php
session_start();
require_once '../api/auth_helper.php';
restrictTo(['admin']);
include '../connection.php';

// Fungsi flash message
function setFlash($msg, $type) {
    $_SESSION['flash'] = ['message' => $msg, 'type' => $type];
}

// 2. LOGIKA PEMROSESAN DATA (CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- TAMBAH USER ---
    if (isset($_POST['tambah'])) {
        $username = strtolower(trim($_POST['username']));
        $password = $_POST['password'];
        $level    = $_POST['level'];

        if (strlen($password) < 6) {
            setFlash("Gagal: Password minimal 6 karakter!", "danger");
        } else {
            $check = $koneksi->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                setFlash("Gagal: Username <strong>$username</strong> sudah terdaftar!", "danger");
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $koneksi->prepare("INSERT INTO users (username, password, level) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $hashed, $level);
                if ($stmt->execute()) {
                    setFlash("User <strong>$username</strong> berhasil ditambahkan!", "success");
                } else {
                    setFlash("Terjadi kesalahan sistem saat menambah data.", "danger");
                }
            }
        }
        header("Location: kelola_user.php"); exit();
    }

    // --- EDIT USER ---
    if (isset($_POST['edit'])) {
        $id       = (int)$_POST['id'];
        $username = strtolower(trim($_POST['username']));
        $level    = $_POST['level'];
        $password = $_POST['password'];

        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $koneksi->prepare("UPDATE users SET username=?, password=?, level=? WHERE id=?");
            $stmt->bind_param("sssi", $username, $hashed, $level, $id);
        } else {
            $stmt = $koneksi->prepare("UPDATE users SET username=?, level=? WHERE id=?");
            $stmt->bind_param("ssi", $username, $level, $id);
        }
        
        if ($stmt->execute()) {
            setFlash("Data user berhasil diperbarui!", "success");
        } else {
            setFlash("Gagal memperbarui data user.", "danger");
        }
        header("Location: kelola_user.php"); exit();
    }

    // --- HAPUS USER ---
    if (isset($_POST['hapus'])) {
        $id = (int)$_POST['id_hapus'];
        if ($id == $_SESSION['user_id']) { 
            setFlash("Anda tidak bisa menghapus akun Anda sendiri!", "danger");
        } else {
            $stmt = $koneksi->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                setFlash("User telah berhasil dihapus dari sistem.", "success");
            } else {
                setFlash("Gagal menghapus data user.", "danger");
            }
        }
        header("Location: kelola_user.php"); exit();
    }
}

$data = $koneksi->query("SELECT id, username, level FROM users ORDER BY id DESC");
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kelola User · Happy Cellular</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F4F7FA;
            --surface-card: #FFFFFF;
            --primary-dongker: #1E4A7A;
            --primary-dongker-soft: #EEF4FA;
            --accent-blue: #3B82F6;
            --text-main: #1E293B;
            --text-muted: #64748B;
            --success-green: #10B981;
            --bottom-nav-height: 72px;
            --sidebar-width: 260px;
        }

        * { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            min-height: 100vh;
            color: var(--text-main);
            padding-bottom: calc(var(--bottom-nav-height) + 2rem);
        }

        .navbar-top {
            background: var(--surface-card);
            padding: 1.2rem 1.5rem 0.8rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        .logo-area { display:flex; align-items:center; gap:10px; }
        .navbar-logo { width:32px; height:32px; object-fit:contain; border-radius:8px; }
        .logo-text { font-weight:800; font-size:1.3rem; color:var(--primary-dongker); letter-spacing:-0.5px; }
        .logo-text small { font-size:0.75rem; font-weight:500; color:var(--text-muted); display:block; margin-top:-2px; }
        
        .user-badge {
            background: var(--primary-dongker-soft);
            padding: 8px 16px;
            border-radius: 20px;
            color: var(--primary-dongker);
            font-weight: 700;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
        }
        .dropdown-menu-custom {
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(30,41,59,0.1);
            border:1px solid rgba(0,0,0,0.05);
            padding:8px;
        }
        .dropdown-menu-custom .dropdown-item {
            font-size:0.85rem; font-weight:600; color:var(--text-main);
            padding:10px 14px; border-radius:10px; display:flex; align-items:center; gap:10px;
        }
        .dropdown-menu-custom .dropdown-item i { color:var(--primary-dongker); font-size:0.95rem; width:16px; text-align:center; }
        .dropdown-menu-custom .dropdown-item:hover { background:var(--primary-dongker-soft); color:var(--primary-dongker); }
        .dropdown-menu-custom .dropdown-item.text-danger { color:#EF4444; }
        .dropdown-menu-custom .dropdown-item.text-danger i { color:#EF4444; }

        .main-wrapper { max-width: 1200px; margin:0 auto; padding:1rem 1.2rem; }

        .section-heading {
            display: flex; justify-content: space-between; align-items: center;
            margin: 1.5rem 0 1rem;
        }
        .section-heading h3 { font-weight:700; font-size:1.1rem; color:var(--text-main); margin-bottom:0; }

        .card-custom {
            background: var(--surface-card);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 18px rgba(0,0,0,0.02);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0,0,0,0.01);
        }
        .card-header-custom {
            font-weight: 700;
            font-size: 1rem;
            color: var(--primary-dongker);
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        .form-control-service {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            font-size: 0.85rem;
            background: #FFFFFF;
            transition: 0.2s;
        }
        .form-control-service:focus {
            border-color: var(--primary-dongker);
            box-shadow: 0 0 0 3px rgba(30,74,122,0.1);
            outline: none;
        }
        .btn-primary-service {
            background: var(--primary-dongker);
            border: none; color: white; font-weight: 600;
            padding: 12px 28px; border-radius: 50px;
            transition: 0.2s; font-size: 0.85rem;
        }
        .btn-primary-service:hover { background:#0F3A5C; transform:translateY(-1px); }

        .table-responsive-stack {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 0;
        }
        .table-responsive-stack table {
            min-width: 700px;
            width: max-content;
        }
        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-custom thead th {
            background: var(--primary-dongker);
            color: white;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.8rem 1rem;
            border: none;
        }
        .table-custom thead th:first-child { border-radius: 12px 0 0 0; }
        .table-custom thead th:last-child { border-radius: 0 12px 0 0; }
        .table-custom tbody td {
            padding: 0.8rem 1rem;
            vertical-align: middle;
            font-size: 0.85rem;
            border-bottom: 1px solid #F1F5F9;
            background: white;
        }
        .table-custom tbody tr:last-child td:first-child { border-radius: 0 0 0 12px; }
        .table-custom tbody tr:last-child td:last-child { border-radius: 0 0 12px 0; }

        .badge-admin { background: #DCFCE7; color: #166534; padding: 5px 12px; border-radius: 20px; font-weight: 700; font-size: 0.75rem; }
        .badge-frontliner { background: #FEF9C3; color: #854D0E; padding: 5px 12px; border-radius: 20px; font-weight: 700; font-size: 0.75rem; }

        .btn-action {
            padding: 0.4rem 0.7rem; border-radius: 8px;
            font-size: 0.75rem; font-weight: 600;
            border: none; cursor: pointer;
            transition: all 0.2s;
        }
        .btn-edit { background: rgba(59,130,246,0.1); color: var(--accent-blue); }
        .btn-edit:hover { background: rgba(59,130,246,0.2); }
        .btn-delete { background: rgba(239,68,68,0.1); color: #EF4444; }
        .btn-delete:hover { background: rgba(239,68,68,0.2); }

        /* Bottom Nav */
        .bottom-nav {
            position: fixed; bottom:0; left:0; right:0;
            height:var(--bottom-nav-height); background:var(--surface-card);
            display:flex; justify-content:space-around; align-items:center;
            box-shadow:0 -8px 30px rgba(0,0,0,0.04);
            border-top:1px solid #EDF2F7; z-index:1050;
        }
        .nav-item {
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            gap:4px; text-decoration:none; color:#A0AEC0;
            font-size:0.7rem; font-weight:600; flex:1; height:100%;
        }
        .nav-item i { font-size:1.25rem; }
        .nav-item.active { color:var(--primary-dongker); }

        .more-menu-popup {
            position: fixed;
            bottom: calc(var(--bottom-nav-height) + 12px);
            left: 50%;
            transform: translateX(-50%) scale(0.95);
            width: calc(100% - 2.4rem);
            max-width: 500px;
            background: var(--surface-card);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(30,41,59,0.15);
            padding: 10px;
            display: none;
            grid-template-columns: repeat(2,1fr);
            gap: 8px;
            z-index: 1100;
            opacity: 0;
            transition: all 0.2s ease;
        }
        .more-menu-popup.show { display:grid; transform:translateX(-50%) scale(1); opacity:1; }
        .more-menu-popup a {
            padding:12px; text-decoration:none; color:var(--text-main);
            font-weight:600; font-size:0.8rem; border-radius:12px;
            display:flex; align-items:center; gap:10px; background:#F8FAFC;
        }
        .more-menu-popup a i { color:var(--primary-dongker); font-size:1rem; }
        .popup-overlay {
            position:fixed; inset:0; background:rgba(15,23,42,0.12);
            backdrop-filter:blur(2px); z-index:1090; display:none;
        }
        .popup-overlay.show { display:block; }

        .desktop-sidebar { display:none; }

        @media (min-width: 992px) {
            body { padding-bottom:0; display:flex; background:#F8FAFC; }
            .bottom-nav, .popup-overlay, .more-menu-popup { display:none !important; }
            .navbar-top { position:static; box-shadow:none; background:transparent; padding:1.5rem 2rem 0; width:100%; }
            .navbar-top .logo-area { display:none; }
            .desktop-sidebar {
                display:flex; flex-direction:column; width:var(--sidebar-width);
                background:var(--surface-card); height:100vh; position:sticky;
                top:0; border-right:1px solid #E2E8F0; padding:1.5rem; z-index:1010;
            }
            .sidebar-brand { display:flex; align-items:center; gap:10px; margin-bottom:2rem; padding-bottom:1rem; border-bottom:1px solid #F1F5F9; }
            .sidebar-menu { display:flex; flex-direction:column; gap:5px; flex-grow:1; }
            .sidebar-link {
                display:flex; align-items:center; gap:12px; padding:12px 16px;
                color:var(--text-muted); text-decoration:none; font-weight:600;
                font-size:0.85rem; border-radius:12px; transition:all 0.2s;
            }
            .sidebar-link i { font-size:1.1rem; width:20px; text-align:center; }
            .sidebar-link:hover, .sidebar-link.active { background:var(--primary-dongker-soft); color:var(--primary-dongker); }
            .content-wrapper { flex-grow:1; display:flex; flex-direction:column; min-width:0; }
            .main-wrapper { max-width:1200px; width:100%; padding:1.5rem 2rem; margin:0; }
        }

        /* Modal */
        .modal-content { border-radius: 20px; border: none; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .modal-header { border-bottom: none; padding: 1.5rem 1.5rem 0; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { border-top: none; padding: 0 1.5rem 1.5rem; }
        .btn-light { border-radius: 12px; font-weight: 600; }
        .btn-danger { border-radius: 12px; font-weight: 600; }
        .btn-primary { background: var(--primary-dongker); border: none; border-radius: 12px; font-weight: 600; }
        .btn-primary:hover { background: #0F3A5C; }
    </style>
</head>
<body>

<!-- SIDEBAR DESKTOP -->
<div class="desktop-sidebar">
    <div class="sidebar-brand">
        <img src="asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
        <div class="logo-text">Happy Cell<small>Admin Panel</small></div>
    </div>
    <div class="sidebar-menu">
        <a href="index.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="stock_in.php" class="sidebar-link"><i class="fas fa-arrow-circle-down" style="color: #10B981;"></i> Stock In</a>
        <a href="admin_stock_out.php" class="sidebar-link"><i class="fas fa-arrow-circle-up" style="color: #EF4444;"></i> Stock Out</a>
        <a href="return.php" class="sidebar-link"><i class="fas fa-rotate-left" style="color: #F59E0B;"></i> Retur</a>
        <div class="sidebar-divider" style="height:1px; background:#F1F5F9; margin:10px 0;"></div>
        <a href="data_master.php" class="sidebar-link"><i class="fas fa-database"></i> Data Master</a>
        <a href="data_barang.php" class="sidebar-link"><i class="fas fa-box"></i> Data Produk</a>
        <a href="penjualan.php" class="sidebar-link"><i class="fas fa-shopping-cart"></i> Transaksi Jual</a>
        <a href="keuangan.php" class="sidebar-link"><i class="fas fa-chart-pie"></i> Analisis Laba</a>
        <a href="manajemen_label.php" class="sidebar-link"><i class="fas fa-print"></i> Cetak Label</a>
        <a href="tukar_tambah.php" class="sidebar-link"><i class="fas fa-exchange-alt"></i> Tukar Tambah</a>
        <a href="service_hp.php" class="sidebar-link"><i class="fas fa-tools"></i> Service HP</a>
        <a href="kelola_user.php" class="sidebar-link active"><i class="fas fa-users-cog"></i> Kelola User</a>
    </div>
</div>

<!-- CONTENT WRAPPER -->
<div class="content-wrapper">
    <div class="navbar-top">
        <div class="logo-area">
            <img src="asset/Happy.png" alt="Happy Cell" class="navbar-logo" onerror="this.src='https://ui-avatars.com/api/?name=HC&background=1E4A7A&color=fff'">
            <div class="logo-text">Happy Cell<small>Admin Panel</small></div>
        </div>
        <div class="dropdown">
            <button class="user-badge" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom mt-2">
                <li><a class="dropdown-item" href="kelola_user.php"><i class="fas fa-users-cog"></i> Kelola User</a></li>
                <li><a class="dropdown-item" href="ganti_password.php"><i class="fas fa-key"></i> Ganti Password</a></li>
                <li><hr class="dropdown-divider" style="border-color:#EDF2F7;"></li>
                <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
            </ul>
        </div>
    </div>

    <div class="main-wrapper">
        
        <!-- Notifikasi Flash -->
        <?php if($flash): ?>
        <div class="alert alert-<?= $flash['type'] == 'success' ? 'success' : 'danger' ?> alert-dismissible fade show rounded-4" role="alert">
            <i class="fas <?= $flash['type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Form Tambah User -->
        <div class="card-custom">
            <div class="card-header-custom">
                <i class="fas fa-user-plus"></i> Tambah Pengguna Baru
            </div>
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" name="username" class="form-control-service" placeholder="Username" required autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <input type="password" name="password" class="form-control-service" placeholder="Password" required>
                    </div>
                    <div class="col-md-3">
                        <select name="level" class="form-control-service" required>
                            <option value="frontliner">Frontliner</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="tambah" class="btn-primary-service w-100">
                            <i class="fas fa-save me-1"></i> Simpan
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Daftar User -->
        <div class="card-custom">
            <div class="card-header-custom">
                <i class="fas fa-users"></i> Daftar Pengguna
            </div>
            <div class="table-responsive-stack">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while($u = $data->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($u['username']) ?></td>
                            <td>
                                <span class="badge-<?= $u['level'] == 'admin' ? 'admin' : 'frontliner' ?>">
                                    <?= ucfirst($u['level']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button class="btn-action btn-edit me-1" data-bs-toggle="modal" data-bs-target="#editModal<?= $u['id'] ?>" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-action btn-delete" data-bs-toggle="modal" data-bs-target="#hapusModal<?= $u['id'] ?>" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>

                        <!-- Modal Edit -->
                        <div class="modal fade" id="editModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold" style="color:var(--primary-dongker);"><i class="fas fa-edit me-2"></i>Edit Pengguna</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold text-muted">Username</label>
                                                <input type="text" name="username" class="form-control form-control-service" value="<?= htmlspecialchars($u['username']) ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold text-muted">Password Baru <span class="text-muted">(Kosongkan jika tidak diganti)</span></label>
                                                <input type="password" name="password" class="form-control form-control-service">
                                            </div>
                                            <div>
                                                <label class="form-label small fw-bold text-muted">Level</label>
                                                <select name="level" class="form-control form-control-service">
                                                    <option value="admin" <?= $u['level'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                                    <option value="frontliner" <?= $u['level'] == 'frontliner' ? 'selected' : '' ?>>Frontliner</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" name="edit" class="btn btn-primary px-4">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Hapus -->
                        <div class="modal fade" id="hapusModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-sm modal-dialog-centered">
                                <div class="modal-content text-center p-4">
                                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                    <h5 class="fw-bold">Hapus Pengguna?</h5>
                                    <p class="text-muted small">Tindakan ini tidak dapat dibatalkan.</p>
                                    <form method="POST">
                                        <input type="hidden" name="id_hapus" value="<?= $u['id'] ?>">
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="hapus" class="btn btn-danger rounded-3">Ya, Hapus</button>
                                            <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- BOTTOM NAV MOBILE -->
<nav class="bottom-nav">
    <a href="index.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a href="stock_in.php" class="nav-item"><i class="fas fa-arrow-circle-down" style="color:#10B981;"></i><span>Stock In</span></a>
    <a href="admin_stock_out.php" class="nav-item"><i class="fas fa-arrow-circle-up" style="color:#EF4444;"></i><span>Stock Out</span></a>
    <a href="return.php" class="nav-item"><i class="fas fa-rotate-left" style="color:#F59E0B;"></i><span>Retur</span></a>
    <div class="nav-item active" id="moreMenuBtn" role="button"><i class="fas fa-bars"></i><span>Menu</span></div>
</nav>

<div class="more-menu-popup" id="moreMenuPopup">
    <a href="data_master.php"><i class="fas fa-database"></i> Data Master</a>
    <a href="data_barang.php"><i class="fas fa-box"></i> Data Produk</a>
    <a href="penjualan.php"><i class="fas fa-shopping-cart"></i> Transaksi Jual</a>
    <a href="keuangan.php"><i class="fas fa-chart-pie"></i> Analisis Laba</a>
    <a href="manajemen_label.php"><i class="fas fa-print"></i> Cetak Label</a>
    <a href="tukar_tambah.php"><i class="fas fa-exchange-alt"></i> Tukar Tambah</a>
    <a href="service_hp.php"><i class="fas fa-tools"></i> Service HP</a>
</div>
<div class="popup-overlay" id="popupOverlay"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Popup Menu Mobile
    const moreBtn = document.getElementById('moreMenuBtn');
    const popup = document.getElementById('moreMenuPopup');
    const overlay = document.getElementById('popupOverlay');

    if(moreBtn) {
        moreBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            popup.classList.toggle('show');
            overlay.classList.toggle('show');
        });
    }

    if(overlay) {
        overlay.addEventListener('click', function() {
            popup.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    document.addEventListener('click', function(e) {
        if (popup && !popup.contains(e.target) && e.target !== moreBtn && !moreBtn.contains(e.target)) {
            popup.classList.remove('show');
            overlay.classList.remove('show');
        }
    });
</script>
</body>
</html>