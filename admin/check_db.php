<?php
include 'connection.php';
$res = $koneksi->query("DESCRIBE barang");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
