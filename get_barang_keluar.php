<?php
header('Content-Type: application/json');
include 'koneksi.php';

$query = "SELECT id, asset_number, asset_name, jumlah, tanggal, pic, area, created_at FROM barang_keluar ORDER BY created_at DESC";
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
