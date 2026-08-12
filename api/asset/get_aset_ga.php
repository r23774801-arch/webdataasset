<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../app/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/koneksi.php';

$query = "SELECT id, asset_number, nama_barang, serial_number, asset_class, pic, area, location_note, utilisasi, date_of_entry, attachment, kondisi, stocktaking_status, stocktaking_photo, stocktaking_condition, 'ga' as type, created_at FROM aset_ga ORDER BY id DESC";
$result = $conn->query($query);

if (!$result) {
    error_log('[get_aset_ga] query failed: ' . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Gagal memuat data.']);
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
