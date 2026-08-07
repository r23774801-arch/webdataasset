<?php
header('Content-Type: application/json');
session_start();
include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

// RBAC: ADMIN is monitoring/approval only — never allowed to transact barang.
deny_admin_transaction();

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['asset_name']) || !isset($input['jumlah']) || !isset($input['tanggal'])) {
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap! Asset Name, Jumlah, dan Tanggal wajib diisi."]);
    exit;
}

$asset_number = $conn->real_escape_string($input['asset_number'] ?? '');
$asset_name = $conn->real_escape_string($input['asset_name']);
$jumlah = (int)$input['jumlah'];
$supplier = $conn->real_escape_string($input['supplier'] ?? '');
$tanggal = $conn->real_escape_string($input['tanggal']);
$pic = $conn->real_escape_string($input['pic'] ?? '');
$area = $conn->real_escape_string($input['area'] ?? 'Main Office');

$query = "INSERT INTO barang_masuk (asset_number, asset_name, jumlah, supplier, tanggal, pic, area, created_at) 
          VALUES ('$asset_number', '$asset_name', $jumlah, '$supplier', '$tanggal', '$pic', '$area', NOW())";

if ($conn->query($query)) {
    echo json_encode(["status" => "success", "message" => "Data barang masuk berhasil disimpan!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Gagal menyimpan data: " . $conn->error]);
}

$conn->close();
?>
