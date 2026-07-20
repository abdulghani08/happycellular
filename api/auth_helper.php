<?php
// Mencegah error jika session_start dipanggil dua kali
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Menggunakan __DIR__ agar path ke connection.php selalu benar 
// meskipun file ini di-include dari subfolder yang berbeda-beda
require_once __DIR__ . '/../connection.php';

/**
 * Fungsi Inti: Mengecek Sesi & Validasi Cookie Remember Me
 */
function checkAuth() {
    global $koneksi;

    // 1. Jika sudah ada sesi login, langsung berikan izin (true)
    if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
        return true;
    }

    // 2. Jika tidak ada sesi, cek apakah ada Cookie Remember Me
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];

        // Cari token di database yang belum expired
        $stmt = $koneksi->prepare("SELECT id, username, level FROM users WHERE remember_token = ? AND token_expired > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // Token valid! Buat ulang sesi untuk user ini
            $_SESSION['login'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['level'] = strtolower($user['level']);
            
            return true;
        }
    }

    // Jika semua pengecekan gagal
    return false;
}

/**
 * Fungsi Proteksi Halaman (Redirect ke Login jika gagal)
 * @param array $allowed_levels Contoh: ['admin', 'frontliner']
 */
function restrictTo($allowed_levels = []) {
    if (!checkAuth()) {
        header("Location: ../login.php?error=sesi_habis");
        exit();
    }

    // Cek apakah level user saat ini ada dalam daftar yang diizinkan
    if (!in_array($_SESSION['level'], $allowed_levels)) {
        // Jika level tidak cocok, lempar ke halaman sesuai levelnya (atau login)
        if ($_SESSION['level'] === 'admin') {
            header("Location: ../admin/home_admin.php");
        } elseif ($_SESSION['level'] === 'frontliner') {
            header("Location: ../frontliner/home_frontliner.php");
        } else {
            header("Location: ../login.php?error=akses_ditolak");
        }
        exit();
    }
}

/**
 * Fungsi Proteksi API (Output JSON jika gagal)
 */
function restrictAPI($allowed_levels = []) {
    header('Content-Type: application/json');

    if (!checkAuth()) {
        echo json_encode(['status' => 'error', 'msg' => 'Sesi berakhir, silakan login ulang']);
        exit();
    }

    if (!in_array($_SESSION['level'], $allowed_levels)) {
        echo json_encode(['status' => 'error', 'msg' => 'Anda tidak memiliki hak akses (Level: ' . $_SESSION['level'] . ')']);
        exit();
    }
}