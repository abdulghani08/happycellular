<?php
session_start();
// Pastikan hanya admin yang bisa hapus
if (!isset($_SESSION['username'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$logFile = __DIR__ . '/../logs/print_log.txt';
$hashToRemove = $_POST['hash'] ?? '';

if (empty($hashToRemove)) {
    die(json_encode(['success' => false, 'message' => 'Invalid Request']));
}

if (!file_exists($logFile)) {
    die(json_encode(['success' => false, 'message' => 'File log tidak ditemukan']));
}

// Baca file log
$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$newLines = [];
$found = false;

foreach ($lines as $line) {
    // Jika hash baris saat ini TIDAK SAMA dengan hash yang dikirim, masukkan ke array baru
    if (md5(trim($line)) !== $hashToRemove) {
        $newLines[] = $line;
    } else {
        $found = true; // Baris ditemukan dan akan dihapus
    }
}

// Tulis kembali ke file jika baris ditemukan
if ($found) {
    file_put_contents($logFile, implode(PHP_EOL, $newLines) . PHP_EOL);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
}
?>