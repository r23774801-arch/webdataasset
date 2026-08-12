<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../app/bootstrap.php';

// Barang Masuk/Keluar is ADMIN-only: ordinary users never transact barang.
// This legacy table is fed by the redirect-stub pages (barang-keluar.html).
require_admin();

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['asset_name']) || !isset($input['jumlah']) || !isset($input['tanggal'])) {
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap! Asset Name, Jumlah, dan Tanggal wajib diisi."]);
    exit;
}

$asset_number = trim((string)($input['asset_number'] ?? ''));
$asset_name   = trim((string)$input['asset_name']);
$jumlah       = max(0, (int)$input['jumlah']);
$tanggal      = trim((string)$input['tanggal']);
$pic          = trim((string)($input['pic'] ?? ''));
$area         = trim((string)($input['area'] ?? 'Main Office'));

if ($asset_name === '' || $jumlah <= 0 || $tanggal === '') {
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap! Asset Name, Jumlah, dan Tanggal wajib diisi."]);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO barang_keluar (asset_number, asset_name, jumlah, tanggal, pic, area, created_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW())"
);
$stmt->bind_param("ssisss", $asset_number, $asset_name, $jumlah, $tanggal, $pic, $area);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Data barang keluar berhasil disimpan!"]);
} else {
    error_log('[tambah_barang_keluar] insert failed: ' . $conn->error);
    echo json_encode(["status" => "error", "message" => "Gagal menyimpan data."]);
}

$conn->close();
?>