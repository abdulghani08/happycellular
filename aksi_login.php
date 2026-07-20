<?php
session_start();
include "connection.php";

if (!isset($_POST['username']) || !isset($_POST['password'])) {
    header("Location: login.php?error=kosong");
    exit();
}

$username = trim($_POST['username']);
$password = $_POST['password'];

// ambil user
$stmt = $koneksi->prepare("SELECT id, username, level, password FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if (!$user = $result->fetch_assoc()) {
    header("Location: login.php?error=gagal");
    exit();
}

/* ================= VALIDASI PASSWORD ================= */

// ⚠️ Idealnya HANYA pakai password_verify
$isValid = password_verify($password, $user['password']);

// fallback (kalau masih ada data lama plaintext)
if (!$isValid && $password === $user['password']) {
    $isValid = true;

    // auto upgrade ke hash
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $update = $koneksi->prepare("UPDATE users SET password=? WHERE id=?");
    $update->bind_param("si", $newHash, $user['id']);
    $update->execute();
}

if (!$isValid) {
    header("Location: login.php?error=gagal");
    exit();
}

/* ================= SET SESSION ================= */

session_regenerate_id(true); // penting!

$_SESSION['login'] = true;
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['level'] = strtolower($user['level']);

/* ================= REMEMBER ME ================= */

if (isset($_POST['remember'])) {

    // hapus token lama (optional tapi bagus)
    $clear = $koneksi->prepare("UPDATE users SET remember_token=NULL, token_expired=NULL WHERE id=?");
    $clear->bind_param("i", $user['id']);
    $clear->execute();

    $token = bin2hex(random_bytes(32));
    $expired = date("Y-m-d H:i:s", time() + (86400 * 7));

    $stmt = $koneksi->prepare("UPDATE users SET remember_token=?, token_expired=? WHERE id=?");
    $stmt->bind_param("ssi", $token, $expired, $user['id']);
    $stmt->execute();

    // cookie lebih aman
    setcookie("remember_token", $token, [
        'expires' => time() + (86400 * 7),
        'path' => '/',
        'httponly' => true,
        'secure' => false, // ubah true kalau pakai HTTPS
        'samesite' => 'Strict'
    ]);
}

/* ================= REDIRECT ================= */

if ($_SESSION['level'] === 'admin') {
    header("Location: admin/home_admin.php");
} elseif ($_SESSION['level'] === 'frontliner') {
    header("Location: frontliner/home_frontliner.php");
} else {
    header("Location: login.php?error=level");
}
exit();
?>