<?php
session_start();
header('Content-Type: application/json');
include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

// RBAC: Only IT role can update IT assets. ADMIN is monitoring/approval only.
deny_admin_transaction();
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
    $asset_number = $input['asset_number'] ?? '';
    $nama_barang = $input['nama_barang'];
    $serial_number = $input['serial_number'] ?? '';
    $pic = $input['pic'] ?? '';
    $area = $input['area'];
    $location_note = $input['location_note'] ?? '';
    $utilisasi = $input['utilisasi'] ?? 'No';
    $date_of_entry = $input['date_of_entry'] ?? null;
    $attachment = $input['attachment'] ?? '';
    $kondisi = $input['kondisi'] ?? '';

    // Asset Number OR Serial Number must be provided (both empty is rejected).
    // Photo stays optional on edit — the existing uploaded photo is preserved.
    if (trim($asset_number) === '' && trim($serial_number) === '') {
        echo json_encode(["status" => "error", "message" => "Isi Asset Number atau Serial Number minimal satu."]);
        exit;
    }

    // Condition is only updated when explicitly provided (edit modal).
    $updateKondisi = trim($kondisi) !== '';

    // Build query dynamically based on whether attachment/kondisi are provided.
    if (!empty($attachment) && $updateKondisi) {
        $query = "UPDATE aset_it SET nama_barang = ?, serial_number = ?, pic = ?, area = ?, location_note = ?, utilisasi = ?, date_of_entry = ?, attachment = ?, kondisi = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssssssi", $nama_barang, $serial_number, $pic, $area, $location_note, $utilisasi, $date_of_entry, $attachment, $kondisi, $id);
    } elseif (!empty($attachment)) {
        $query = "UPDATE aset_it SET nama_barang = ?, serial_number = ?, pic = ?, area = ?, location_note = ?, utilisasi = ?, date_of_entry = ?, attachment = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssssi", $nama_barang, $serial_number, $pic, $area, $location_note, $utilisasi, $date_of_entry, $attachment, $id);
    } elseif ($updateKondisi) {
        $query = "UPDATE aset_it SET nama_barang = ?, serial_number = ?, pic = ?, area = ?, location_note = ?, utilisasi = ?, date_of_entry = ?, kondisi = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssssi", $nama_barang, $serial_number, $pic, $area, $location_note, $utilisasi, $date_of_entry, $kondisi, $id);
    } else {
        $query = "UPDATE aset_it SET nama_barang = ?, serial_number = ?, pic = ?, area = ?, location_note = ?, utilisasi = ?, date_of_entry = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssssi", $nama_barang, $serial_number, $pic, $area, $location_note, $utilisasi, $date_of_entry, $id);
    }

    if ($stmt->execute()) {
        AuditService::log($conn, 'Updated Asset', 'aset_it', (int)$id, ['nama_barang' => $nama_barang, 'area' => $area]);

        // Phase 4.20 — mirror the latest asset state to the sheet (upsert by asset_number).
        if ($stmt->affected_rows > 0) {
            SpreadsheetService::syncAsset($conn, 'aset_it', SpreadsheetService::SHEET_ASSET_IT, (int)$id);
        }

        echo json_encode(["status" => "success", "message" => "Data aset IT berhasil diperbarui!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal memperbarui data."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak valid."]);
}
