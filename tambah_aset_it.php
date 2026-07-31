<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');
include 'koneksi.php';

// RBAC: Only IT role can add IT assets
if (!isset($_SESSION['role']) || strtoupper($_SESSION['role']) !== 'IT') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Hanya role IT yang dapat menambahkan data aset IT."]);
    exit;
}

// Ambil data JSON yang dikirimkan oleh JavaScript
$input = json_decode(file_get_contents('php://input'), true);

if ($input) {
    $asset_number  = $input['asset_number'] ?? '';
    $nama_barang   = $input['nama_barang'] ?? '';
    $serial_number = $input['serial_number'] ?? '';
    $asset_class   = $input['asset_class'] ?? '';
    $pic           = $input['pic'] ?? '';
    $area          = $input['area'] ?? '';
    $location_note = $input['location_note'] ?? '';
    $utilisasi     = $input['utilisasi'] ?? '';
    $date_of_entry = $input['date_of_entry'] ?? null;
    $attachment    = $input['attachment'] ?? '';
    // Ensure path includes uploads/ prefix if a file was uploaded
    if (!empty($attachment) && strpos($attachment, 'uploads/') !== 0 && strpos($attachment, 'img/') !== 0) {
        $attachment = 'uploads/' . $attachment;
    }

    // Hardcode kondisi to '-' for all new entries (pending status)
    $kondisi = '-';

    $query = "INSERT INTO aset_it (asset_number, nama_barang, serial_number, asset_class, pic, area, location_note, utilisasi, date_of_entry, attachment, kondisi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("sssssssssss", $asset_number, $nama_barang, $serial_number, $asset_class, $pic, $area, $location_note, $utilisasi, $date_of_entry, $attachment, $kondisi);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Aset IT berhasil ditambahkan!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak valid."]);
}
