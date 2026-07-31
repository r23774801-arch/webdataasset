<?php
header('Content-Type: application/json');
include 'koneksi.php';

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['asset_name']) || !isset($input['jumlah']) || !isset($input['tanggal'])) {
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap! Asset Name, Jumlah, dan Tanggal wajib diisi."]);
    exit;
}

$asset_number = $conn->real_escape_string($input['asset_number'] ?? '');
$asset_name = $conn->real_escape_string($input['asset_name']);
$jumlah = (int)$input['jumlah'];
$tanggal = $conn->real_escape_string($input['tanggal']);
$pic = $conn->real_escape_string($input['pic'] ?? '');
$area = $conn->real_escape_string($input['area'] ?? 'Main Office');

$query = "INSERT INTO barang_keluar (asset_number, asset_name, jumlah, tanggal, pic, area, created_at) 
          VALUES ('$asset_number', '$asset_name', $jumlah, '$tanggal', '$pic', '$area', NOW())";

if ($conn->query($query)) {
    echo json_encode(["status" => "success", "message" => "Data barang keluar berhasil disimpan!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Gagal menyimpan data: " . $conn->error]);
}

$conn->close();
?>
