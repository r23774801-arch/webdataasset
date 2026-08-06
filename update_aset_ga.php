<?php
session_start();
header('Content-Type: application/json');
include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

// RBAC: Only GA role can update GA assets
if (!isset($_SESSION['role']) || strtoupper($_SESSION['role']) !== 'GA') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Hanya role GA yang dapat mengubah data aset GA."]);
    exit;
}

// PHASE 4.15 — session lock: no asset edits while a GA stocktaking cycle is
// Pending or Approved. Rejecting the submission unlocks editing again.
if (ApprovalService::isStocktakingLocked($conn, 'GA')) {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Stocktaking aset GA sedang berlangsung atau telah disetujui. Perubahan aset tidak diizinkan."]);
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
        $query = "UPDATE aset_ga SET nama_barang = ?, serial_number = ?, asset_class = ?, pic = ?, area = ?, location_note = ?, utilisasi = ?, date_of_entry = ?, attachment = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssssssi", $nama_barang, $serial_number, $asset_class, $pic, $area, $location_note, $utilisasi, $date_of_entry, $attachment, $id);
    } else {
        $query = "UPDATE aset_ga SET nama_barang = ?, serial_number = ?, asset_class = ?, pic = ?, area = ?, location_note = ?, utilisasi = ?, date_of_entry = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssssi", $nama_barang, $serial_number, $asset_class, $pic, $area, $location_note, $utilisasi, $date_of_entry, $id);
    }

    if ($stmt->execute()) {
        AuditService::log($conn, 'Updated Asset', 'aset_ga', (int)$id, ['nama_barang' => $nama_barang, 'area' => $area]);

        // Phase 4.20 — mirror the latest asset state to the sheet (upsert by asset_number).
        if ($stmt->affected_rows > 0) {
            SpreadsheetService::syncAsset($conn, 'aset_ga', SpreadsheetService::SHEET_ASSET_GA, (int)$id);
        }

        echo json_encode(["status" => "success", "message" => "Data aset GA berhasil diperbarui!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal memperbarui data."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak valid."]);
}
