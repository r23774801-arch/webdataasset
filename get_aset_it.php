<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');
include 'koneksi.php';

$query = "SELECT id, asset_number, nama_barang, serial_number, asset_class, pic, area, location_note, utilisasi, date_of_entry, attachment, kondisi, stocktaking_status, stocktaking_photo, stocktaking_condition, created_at FROM aset_it ORDER BY id DESC";
$result = $conn->query($query);

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . mysqli_error($conn)]);
    exit;
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $data
]);
?>
