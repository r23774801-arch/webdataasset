<?php
session_start();
header('Content-Type: application/json');
include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

// RBAC: Only IT role can update IT assets
if (!isset($_SESSION['role']) || strtoupper($_SESSION['role']) !== 'IT') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Hanya role IT yang dapat mengubah data aset IT."]);
    exit;
}

// PHASE 4.15 — session lock: no asset edits while an IT stocktaking cycle is
// Pending or Approved. Rejecting the submission unlocks editing again.
if (ApprovalService::isStocktakingLocked($conn, 'IT')) {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Stocktaking aset IT sedang berlangsung atau telah disetujui. Perubahan aset tidak diizinkan."]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if ($input && isset($input['id'])) {
    $id = $input['id'];
    $nama_barang = $input['nama_barang'];
    $serial_number = $input['serial_number'] ?? '';
    $pic = $input['pic'] ?? '';
    $area = $input['area'];
    $location_note = $input['location_note'] ?? '';
    $utilisasi = $input['utilisasi'] ?? 'No';
    $date_of_entry = $input['date_of_entry'] ?? null;
    $attachment = $input['attachment'] ?? '';

    // Build query dynamically based on whether attachment is provided (kondisi is not updated here)
    if (!empty($attachment)) {
        $query = "UPDATE aset_it SET nama_barang = ?, serial_number = ?, pic = ?, area = ?, location_note = ?, utilisasi = ?, date_of_entry = ?, attachment = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssssi", $nama_barang, $serial_number, $pic, $area, $location_note, $utilisasi, $date_of_entry, $attachment, $id);
    } else {
        $query = "UPDATE aset_it SET nama_barang = ?, serial_number = ?, pic = ?, area = ?, location_note = ?, utilisasi = ?, date_of_entry = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssssi", $nama_barang, $serial_number, $pic, $area, $location_note, $utilisasi, $date_of_entry, $id);
    }

    if ($stmt->execute()) {
        AuditService::log($conn, 'Updated Asset', 'aset_it', (int)$id, ['nama_barang' => $nama_barang, 'area' => $area]);
        echo json_encode(["status" => "success", "message" => "Data aset IT berhasil diperbarui!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal memperbarui data."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak valid."]);
}
