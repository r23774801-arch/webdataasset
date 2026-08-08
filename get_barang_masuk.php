<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/app/bootstrap.php';
require_login();
include 'koneksi.php';

$query = "SELECT id, asset_number, asset_name, jumlah, supplier, tanggal, pic, area, created_at FROM barang_masuk ORDER BY created_at DESC";
$result = $conn->query($query);

$data = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode([
    "status" => "success",
    "data" => $data
]);

$conn->close();
?>
