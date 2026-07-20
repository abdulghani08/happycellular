<?php
require_once 'auth_helper.php';
restrictAPI(['admin']);
require_once '../connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'msg' => 'Method tidak diizinkan']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$action = $input['action'] ?? '';

switch ($action) {

    // ============================================================
    // MERK
    // ============================================================
    case 'add_merk':
        $nama = strtoupper(trim($input['nama_merk'] ?? ''));
        if ($nama === '') { echo json_encode(['status' => 'error', 'msg' => 'Nama merk wajib diisi']); exit(); }
        $stmt = $koneksi->prepare("INSERT INTO master_merk (nama_merk) VALUES (?)");
        $stmt->bind_param('s', $nama);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'msg' => "Merk '$nama' berhasil ditambahkan", 'id' => $stmt->insert_id]);
        } else {
            $err = $stmt->errno == 1062 ? "Merk '$nama' sudah ada" : 'Gagal: ' . $stmt->error;
            echo json_encode(['status' => 'error', 'msg' => $err]);
        }
        $stmt->close();
        break;

    case 'update_merk':
        $id = (int)($input['id'] ?? 0);
        $nama = strtoupper(trim($input['nama_merk'] ?? ''));
        if ($id <= 0 || $nama === '') { echo json_encode(['status' => 'error', 'msg' => 'Data tidak valid']); exit(); }
        $stmt = $koneksi->prepare("UPDATE master_merk SET nama_merk = ? WHERE id = ?");
        $stmt->bind_param('si', $nama, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Merk berhasil diperbarui' : 'Gagal: ' . $koneksi->error]);
        break;

    case 'delete_merk':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['status' => 'error', 'msg' => 'ID tidak valid']); exit(); }
        $stmt = $koneksi->prepare("DELETE FROM master_merk WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'msg' => 'Merk berhasil dihapus']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Tidak bisa hapus: masih ada barang yang menggunakan merk ini']);
        }
        $stmt->close();
        break;

    case 'toggle_merk':
        $id = (int)($input['id'] ?? 0);
        $status = ($input['status'] ?? '') === 'aktif' ? 'aktif' : 'nonaktif';
        $stmt = $koneksi->prepare("UPDATE master_merk SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Status merk diperbarui' : 'Gagal']);
        break;

    // ============================================================
    // BARANG
    // ============================================================
    case 'add_barang':
        $merkId = (int)($input['merk_id'] ?? 0);
        $nama = strtoupper(trim($input['nama_barang'] ?? ''));
        if ($merkId <= 0 || $nama === '') { echo json_encode(['status' => 'error', 'msg' => 'Merk dan nama barang wajib diisi']); exit(); }
        $stmt = $koneksi->prepare("INSERT INTO master_barang (merk_id, nama_barang) VALUES (?, ?)");
        $stmt->bind_param('is', $merkId, $nama);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'msg' => "Barang '$nama' berhasil ditambahkan", 'id' => $stmt->insert_id]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Gagal menambah barang: ' . $stmt->error]);
        }
        $stmt->close();
        break;

    case 'update_barang':
        $id = (int)($input['id'] ?? 0);
        $merkId = (int)($input['merk_id'] ?? 0);
        $nama = strtoupper(trim($input['nama_barang'] ?? ''));
        if ($id <= 0 || $merkId <= 0 || $nama === '') { echo json_encode(['status' => 'error', 'msg' => 'Data tidak valid']); exit(); }
        $stmt = $koneksi->prepare("UPDATE master_barang SET merk_id = ?, nama_barang = ? WHERE id = ?");
        $stmt->bind_param('isi', $merkId, $nama, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Barang berhasil diperbarui' : 'Gagal: ' . $koneksi->error]);
        break;

    case 'delete_barang':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['status' => 'error', 'msg' => 'ID tidak valid']); exit(); }
        $stmt = $koneksi->prepare("DELETE FROM master_barang WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'msg' => 'Barang berhasil dihapus']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Tidak bisa hapus: masih ada varian atau stok yang menggunakan barang ini']);
        }
        $stmt->close();
        break;

    case 'toggle_barang':
        $id = (int)($input['id'] ?? 0);
        $status = ($input['status'] ?? '') === 'aktif' ? 'aktif' : 'nonaktif';
        $stmt = $koneksi->prepare("UPDATE master_barang SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Status barang diperbarui' : 'Gagal']);
        break;

    // ============================================================
    // VARIAN
    // ============================================================
    case 'add_varian':
        $barangId = (int)($input['barang_id'] ?? 0);
        $kondisi  = trim($input['kondisi'] ?? '');
        $storage  = strtoupper(trim($input['internal_storage'] ?? ''));
        $warna    = strtoupper(trim($input['warna'] ?? ''));
        $allowed  = ['New', 'Second', 'Like New', 'Ex Inter', 'Beacukai'];
        if ($barangId <= 0 || !in_array($kondisi, $allowed, true) || $storage === '' || $warna === '') {
            echo json_encode(['status' => 'error', 'msg' => 'Semua field varian wajib diisi dengan benar']); exit();
        }
        // Cek duplikat
        $chk = $koneksi->prepare("SELECT id FROM master_varian_barang WHERE barang_id=? AND kondisi=? AND internal_storage=? AND warna=?");
        $chk->bind_param('isss', $barangId, $kondisi, $storage, $warna);
        $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) { $chk->close(); echo json_encode(['status' => 'error', 'msg' => 'Varian ini sudah ada!']); exit(); }
        $chk->close();
        $stmt = $koneksi->prepare("INSERT INTO master_varian_barang (barang_id, kondisi, internal_storage, warna) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $barangId, $kondisi, $storage, $warna);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'msg' => 'Varian berhasil ditambahkan', 'id' => $stmt->insert_id]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Gagal: ' . $stmt->error]);
        }
        $stmt->close();
        break;

    case 'update_varian':
        $id      = (int)($input['id'] ?? 0);
        $barangId= (int)($input['barang_id'] ?? 0);
        $kondisi = trim($input['kondisi'] ?? '');
        $storage = strtoupper(trim($input['internal_storage'] ?? ''));
        $warna   = strtoupper(trim($input['warna'] ?? ''));
        $allowed = ['New', 'Second', 'Like New', 'Ex Inter', 'Beacukai'];
        if ($id <= 0 || $barangId <= 0 || !in_array($kondisi, $allowed, true) || $storage === '' || $warna === '') {
            echo json_encode(['status' => 'error', 'msg' => 'Data tidak valid']); exit();
        }
        $stmt = $koneksi->prepare("UPDATE master_varian_barang SET barang_id=?, kondisi=?, internal_storage=?, warna=? WHERE id=?");
        $stmt->bind_param('isssi', $barangId, $kondisi, $storage, $warna, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Varian berhasil diperbarui' : 'Gagal: ' . $koneksi->error]);
        break;

    case 'delete_varian':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['status' => 'error', 'msg' => 'ID tidak valid']); exit(); }
        $stmt = $koneksi->prepare("DELETE FROM master_varian_barang WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'msg' => 'Varian berhasil dihapus']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Tidak bisa hapus: ada stok barang yang terhubung']);
        }
        $stmt->close();
        break;

    case 'toggle_varian':
        $id = (int)($input['id'] ?? 0);
        $status = ($input['status'] ?? '') === 'aktif' ? 'aktif' : 'nonaktif';
        $stmt = $koneksi->prepare("UPDATE master_varian_barang SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Status varian diperbarui' : 'Gagal']);
        break;

    case 'add_varian_batch':
        $barang_id   = (int)($input['barang_id'] ?? 0);
        $varianArray = $input['varian'] ?? [];
        if ($barang_id <= 0 || empty($varianArray)) {
            echo json_encode(['status' => 'error', 'msg' => 'Data tidak lengkap']); exit;
        }
        $allowedKondisi = ['New', 'Second', 'Like New', 'Ex Inter', 'Beacukai'];
        $stmtInsert = $koneksi->prepare("INSERT INTO master_varian_barang (barang_id, kondisi, internal_storage, warna, status) VALUES (?, ?, ?, ?, 'aktif')");
        $stmtCheck  = $koneksi->prepare("SELECT id FROM master_varian_barang WHERE barang_id=? AND kondisi=? AND internal_storage=? AND warna=?");
        $success = $duplicate = $errors = 0;
        foreach ($varianArray as $v) {
            $kondisi = trim($v['kondisi'] ?? '');
            $storage = strtoupper(trim($v['internal_storage'] ?? ''));
            $warna   = strtoupper(trim($v['warna'] ?? ''));
            if (!in_array($kondisi, $allowedKondisi, true) || $storage === '' || $warna === '') { $errors++; continue; }
            $stmtCheck->bind_param('isss', $barang_id, $kondisi, $storage, $warna);
            $stmtCheck->execute(); $stmtCheck->store_result();
            if ($stmtCheck->num_rows > 0) { $duplicate++; $stmtCheck->free_result(); continue; }
            $stmtCheck->free_result();
            $stmtInsert->bind_param('isss', $barang_id, $kondisi, $storage, $warna);
            if ($stmtInsert->execute()) $success++; else $errors++;
        }
        $stmtInsert->close(); $stmtCheck->close();
        $msg = "Berhasil menambahkan $success varian.";
        if ($duplicate > 0) $msg .= " $duplicate duplikat dilewati.";
        if ($errors > 0)    $msg .= " $errors gagal/tidak valid.";
        echo json_encode(['status' => 'success', 'msg' => $msg, 'saved' => $success]);
        break;

    // ============================================================
    // MASTER STORAGE
    // ============================================================
    case 'add_storage':
        $nilai = strtoupper(trim($input['nilai'] ?? ''));
        $urutan = (int)($input['urutan'] ?? 0);
        if ($nilai === '') { echo json_encode(['status' => 'error', 'msg' => 'Nilai storage wajib diisi']); exit(); }
        $stmt = $koneksi->prepare("INSERT INTO master_storage (nilai, urutan) VALUES (?, ?)");
        $stmt->bind_param('si', $nilai, $urutan);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'msg' => "Storage '$nilai' berhasil ditambahkan", 'id' => $stmt->insert_id]);
        } else {
            $err = $stmt->errno == 1062 ? "Storage '$nilai' sudah ada" : 'Gagal: ' . $stmt->error;
            echo json_encode(['status' => 'error', 'msg' => $err]);
        }
        $stmt->close();
        break;

    case 'update_storage':
        $id = (int)($input['id'] ?? 0);
        $nilai = strtoupper(trim($input['nilai'] ?? ''));
        $urutan = (int)($input['urutan'] ?? 0);
        if ($id <= 0 || $nilai === '') { echo json_encode(['status' => 'error', 'msg' => 'Data tidak valid']); exit(); }
        $stmt = $koneksi->prepare("UPDATE master_storage SET nilai = ?, urutan = ? WHERE id = ?");
        $stmt->bind_param('sii', $nilai, $urutan, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Storage berhasil diperbarui' : 'Gagal']);
        break;

    case 'delete_storage':
        $id = (int)($input['id'] ?? 0);
        $stmt = $koneksi->prepare("DELETE FROM master_storage WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Storage berhasil dihapus' : 'Gagal']);
        break;

    case 'toggle_storage':
        $id = (int)($input['id'] ?? 0);
        $status = ($input['status'] ?? '') === 'aktif' ? 'aktif' : 'nonaktif';
        $stmt = $koneksi->prepare("UPDATE master_storage SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Status storage diperbarui' : 'Gagal']);
        break;

    // ============================================================
    // MASTER WARNA
    // ============================================================
    case 'add_warna':
        $nama = strtoupper(trim($input['nama_warna'] ?? ''));
        if ($nama === '') { echo json_encode(['status' => 'error', 'msg' => 'Nama warna wajib diisi']); exit(); }
        $stmt = $koneksi->prepare("INSERT INTO master_warna (nama_warna, aktif) VALUES (?, 1)");
        $stmt->bind_param('s', $nama);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'msg' => "Warna '$nama' berhasil ditambahkan", 'id' => $stmt->insert_id]);
        } else {
            $err = $stmt->errno == 1062 ? "Warna '$nama' sudah ada" : 'Gagal: ' . $stmt->error;
            echo json_encode(['status' => 'error', 'msg' => $err]);
        }
        $stmt->close();
        break;

    case 'update_warna':
        $id = (int)($input['id'] ?? 0);
        $nama = strtoupper(trim($input['nama_warna'] ?? ''));
        if ($id <= 0 || $nama === '') { echo json_encode(['status' => 'error', 'msg' => 'Data tidak valid']); exit(); }
        $stmt = $koneksi->prepare("UPDATE master_warna SET nama_warna = ? WHERE id = ?");
        $stmt->bind_param('si', $nama, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Warna berhasil diperbarui' : 'Gagal']);
        break;

    case 'delete_warna':
        $id = (int)($input['id'] ?? 0);
        $stmt = $koneksi->prepare("DELETE FROM master_warna WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Warna berhasil dihapus' : 'Gagal']);
        break;

    case 'toggle_warna':
        $id = (int)($input['id'] ?? 0);
        $aktif = ($input['aktif'] ?? 0) ? 1 : 0;
        $stmt = $koneksi->prepare("UPDATE master_warna SET aktif = ? WHERE id = ?");
        $stmt->bind_param('ii', $aktif, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Status warna diperbarui' : 'Gagal']);
        break;

    // ============================================================
    // SUPPLIER
    // ============================================================
    case 'add_supplier':
        $nama    = strtoupper(trim($input['nama_supplier'] ?? ''));
        $alamat  = trim($input['alamat'] ?? '');
        $telepon = trim($input['telepon'] ?? '');
        $email   = trim($input['email'] ?? '');
        if ($nama === '') { echo json_encode(['status' => 'error', 'msg' => 'Nama supplier wajib diisi']); exit(); }
        $stmt = $koneksi->prepare("INSERT INTO master_supplier (nama_supplier, alamat, telepon, email) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $nama, $alamat, $telepon, $email);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'msg' => 'Supplier berhasil ditambahkan', 'id' => $stmt->insert_id]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Gagal: ' . $stmt->error]);
        }
        $stmt->close();
        break;

    case 'update_supplier':
        $id      = (int)($input['id'] ?? 0);
        $nama    = strtoupper(trim($input['nama_supplier'] ?? ''));
        $alamat  = trim($input['alamat'] ?? '');
        $telepon = trim($input['telepon'] ?? '');
        $email   = trim($input['email'] ?? '');
        if ($id <= 0 || $nama === '') { echo json_encode(['status' => 'error', 'msg' => 'Data tidak valid']); exit(); }
        $stmt = $koneksi->prepare("UPDATE master_supplier SET nama_supplier=?, alamat=?, telepon=?, email=? WHERE id=?");
        $stmt->bind_param('ssssi', $nama, $alamat, $telepon, $email, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Supplier berhasil diperbarui' : 'Gagal']);
        break;

    case 'toggle_supplier':
        $id = (int)($input['id'] ?? 0);
        $status = ($input['status'] ?? '') === 'aktif' ? 'aktif' : 'nonaktif';
        $stmt = $koneksi->prepare("UPDATE master_supplier SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Status supplier diperbarui' : 'Gagal']);
        break;

    default:
        echo json_encode(['status' => 'error', 'msg' => "Action '$action' tidak dikenali"]);
}