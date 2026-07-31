<?php
session_start();
header('Content-Type: application/json');
include 'koneksi.php';

// RBAC: Only IT role can update IT assets
if (!isset($_SESSION['role']) || strtoupper($_SESSION['role']) !== 'IT') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Hanya role IT yang dapat mengubah data aset IT."]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if ($input && isset($input['id'])) {
    $id = $input['id'];
    $nama_barang = $input['nama_barang'];
    $serial_number = $input['serial_number'] ?? '';
    $asset_class = $input['asset_class'] ?? '';
    $pic = $input['pic'] ?? '';
    $area = $input['area'];
    $location_note = $input['location_note'] ?? '';
    $utilisasi = $input['utilisasi'] ?? 'No';
    $date_of_entry = $input['date_of_entry'] ?? null;
    $attachment = $input['attachment'] ?? '';

    // Build query dynamically based on whether attachment is provided (kondisi is not updated here)
    if (!empty($attachment)) {
        $query = "UPDATE aset_it SET nama_barang = ?, serial_number = ?, asset_class = ?, pic = ?, area = ?, location_note = ?, utilisasi = ?, date_of_entry = ?, attachment = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssssssi", $nama_barang, $serial_number, $asset_class, $pic, $area, $location_note, $utilisasi, $date_of_entry, $attachment, $id);
    } else {
        $query = "UPDATE aset_it SET nama_barang = ?, serial_number = ?, asset_class = ?, pic = ?, area = ?, location_note = ?, utilisasi = ?, date_of_entry = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssssi", $nama_barang, $serial_number, $asset_class, $pic, $area, $location_note, $utilisasi, $date_of_entry, $id);
    }

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Data aset IT berhasil diperbarui!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal memperbarui data."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak valid."]);
}
