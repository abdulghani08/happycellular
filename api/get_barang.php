<?php
include '../connection.php';

header('Content-Type: application/json');

$imei = $_GET['imei'] ?? '';

$stmt = mysqli_prepare($koneksi, "SELECT * FROM barang WHERE imei=?");
mysqli_stmt_bind_param($stmt, "s", $imei);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if($row = mysqli_fetch_assoc($result)){

echo json_encode([
'status'=>'success',
'data'=>[
'nama'=>$row['nama'],
'imei'=>$row['imei'],
'harga_jual'=>number_format($row['harga_jual'],0,',','.'),
]
]);

}else{
echo json_encode(['status'=>'not_found']);
}