<?php

header('Content-Type: application/json');

require_once 'auth_helper.php';

// Hanya admin
restrictAPI(['admin']);

include '../connection.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    // ================= AMBIL DATA =================
    $imei_list_json = $_POST['imei_list'] ?? '[]';
    $imei_list      = json_decode($imei_list_json, true);

    // Validasi JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Format daftar IMEI tidak valid: ' . json_last_error_msg());
    }

    $nama           = strtoupper(trim($_POST['nama'] ?? ''));
    $tipe           = strtoupper(trim($_POST['tipe'] ?? ''));
    $warna          = strtoupper(trim($_POST['warna'] ?? ''));
    $internal       = strtoupper(trim($_POST['internal'] ?? ''));
    $supplier       = strtoupper(trim($_POST['supplier'] ?? ''));
    $keterangan     = trim($_POST['keterangan'] ?? '');            // ← TIDAK uppercase, bebas
    $modal          = (int) ($_POST['modal'] ?? 0);
    $harga_jual     = (int) ($_POST['harga_jual'] ?? 0);
    $tanggal        = $_POST['tanggal'] ?? date('Y-m-d');

    // Status: wajib sesuai enum, default READY
    $allowedStatus  = ['READY', 'RETURN_SUPPLIER', 'NO READY'];
    $status         = in_array($_POST['status'] ?? '', $allowedStatus) ? $_POST['status'] : 'READY';

    // ================= VALIDASI =================
    if (!is_array($imei_list) || count($imei_list) === 0) {
        throw new Exception('Daftar IMEI kosong');
    }

    if ($nama === '') {
        throw new Exception('Nama barang wajib diisi');
    }

    if ($modal <= 0) {
        throw new Exception('Harga modal wajib diisi');
    }

    // ================= HANDLE GAMBAR =================
    $gambarBlob = null;

    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {

        $maxSize = 2 * 1024 * 1024;

        if ($_FILES['gambar']['size'] > $maxSize) {
            throw new Exception('Ukuran gambar maksimal 2MB');
        }

        $gambarBlob = file_get_contents($_FILES['gambar']['tmp_name']);
    }

    // ================= TRANSACTION =================
    $koneksi->begin_transaction();

    // ================= PREPARE STATEMENT SEKALI SAJA =================
    $sql = "INSERT INTO barang (
                nama,
                tipe,
                imei,
                warna,
                internal,
                supplier,
                keterangan,
                modal,
                harga_jual,
                tanggal,
                gambar,
                status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )";

    $stmt = $koneksi->prepare($sql);

    // Variabel referensi untuk IMEI (akan diubah nilainya di setiap iterasi loop)
    $imei_ref = '';

    // ================= BIND PARAMETER SEKALI SAJA DI LUAR LOOP =================
    // Urutan: nama, tipe, imei, warna, internal, supplier, keterangan, modal, harga_jual, tanggal, gambar, status
    // Tipe:   s     s     s     s      s        s         s           i      i          s        s       s
    $stmt->bind_param(
        "sssssssiisss",    // 12 parameter: 9 string, 2 integer, 1 blob(string)
        $nama,
        $tipe,
        $imei_ref,         // ← variabel referensi, nilainya akan di-update tiap loop
        $warna,
        $internal,
        $supplier,
        $keterangan,
        $modal,
        $harga_jual,
        $tanggal,
        $gambarBlob,
        $status
    );

    $success = 0;
    $errors = [];

    // ================= LOOP SETIAP IMEI =================
    foreach ($imei_list as $imei_raw) {

        // Update nilai variabel referensi yang sudah di-bind
        $imei_ref = strtoupper(trim($imei_raw));

        if ($imei_ref === '') {
            continue;
        }

        // ================= CEK DUPLIKAT =================
        $cek = $koneksi->prepare("SELECT id FROM barang WHERE imei = ?");
        $cek->bind_param("s", $imei_ref);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            $errors[] = "IMEI $imei_ref sudah ada";
            $cek->close();
            continue;
        }

        $cek->close();

        // ================= EXECUTE INSERT =================
        if ($stmt->execute()) {
            $success++;
        } else {
            $errors[] = "Gagal insert IMEI $imei_ref: " . $stmt->error;
        }
    }

    $stmt->close();

    // ================= COMMIT ATAU ROLLBACK =================
    // Jika tidak ada yang berhasil sama sekali, rollback
    if ($success === 0 && count($errors) > 0) {
        $koneksi->rollback();
        throw new Exception("Tidak ada IMEI yang berhasil disimpan. Error: " . implode('; ', $errors));
    }

    $koneksi->commit();

    // ================= RESPONSE =================
    $response = [
        'status'  => 'success',
        'msg'     => "Berhasil menyimpan $success barang",
        'success' => $success,
        'total'   => count($imei_list)
    ];

    if (!empty($errors)) {
        $response['errors'] = $errors;
    }

    echo json_encode($response);

} catch (Exception $e) {

    if (isset($koneksi) && $koneksi->connect_errno === 0) {
        // Cek apakah transaksi masih aktif
        $koneksi->rollback();
    }

    echo json_encode([
        'status' => 'error',
        'msg'    => $e->getMessage()
    ]);
}