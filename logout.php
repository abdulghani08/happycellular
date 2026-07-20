<?php
session_start();
include 'connection.php';

/* ================= HAPUS COOKIE REMEMBER ================= */
if (isset($_COOKIE['remember_token'])) {

    $token = $_COOKIE['remember_token'];

    // Hapus token di database
    $stmt = $koneksi->prepare("UPDATE users SET remember_token=NULL, token_expired=NULL WHERE remember_token=?");
    $stmt->bind_param("s", $token);
    $stmt->execute();

    // Hapus cookie browser
    setcookie(
        'remember_token',
        '',
        time() - 3600,
        '/'
    );
}

/* ================= HAPUS SESSION ================= */
$_SESSION = [];
session_unset();
session_destroy();

/* ================= REDIRECT ================= */
header("Location: /login");
exit();
?>