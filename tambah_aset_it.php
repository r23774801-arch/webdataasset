<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');
include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

// RBAC: Only IT and ADMIN roles can add IT assets
if (!isset($_SESSION['role']) || !in_array(strtoupper($_SESSION['role']), ['IT', 'ADMIN'], true)) {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Hanya role IT yang dapat menambahkan data aset IT."]);
    exit;
}

// Phase 4.11 — asset-type-wide lock: no new IT assets while an IT stocktaking
// cycle is Pending or Approved (single source of truth: stocktaking_submissions.status).
// Rejecting the submission automatically unlocks creation again.
if (ApprovalService::isAssetCreationLocked($conn, 'IT')) {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Stocktaking aset IT sedang berlangsung atau telah disetujui. Penambahan aset IT baru tidak diizinkan."]);
    exit;
}

// Ambil data JSON yang dikirimkan oleh JavaScript
$input = json_decode(file_get_contents('php://input'), true);

if ($input) {
    $asset_number  = $input['asset_number'] ?? '';
    $nama_barang   = $input['nama_barang'] ?? '';
    $serial_number = $input['serial_number'] ?? '';
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

    $query = "INSERT INTO aset_it (asset_number, nama_barang, serial_number, pic, area, location_note, utilisasi, date_of_entry, attachment, kondisi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("ssssssssss", $asset_number, $nama_barang, $serial_number, $pic, $area, $location_note, $utilisasi, $date_of_entry, $attachment, $kondisi);

    if ($stmt->execute()) {
        $newId = (int)$conn->insert_id;
        AuditService::log($conn, 'Created Asset', 'aset_it', $newId, ['nama_barang' => $nama_barang, 'area' => $area]);

        // Mirror the new record to the Asset_IT worksheet (best-effort only).
        SpreadsheetService::sync(SpreadsheetService::SHEET_ASSET_IT, [
            'id'             => $newId,
            'asset_number'   => $asset_number,
            'nama_barang'    => $nama_barang,
            'serial_number'  => $serial_number,
            'pic'            => $pic,
            'area'           => $area,
            'location_note'  => $location_note,
            'utilisasi'      => $utilisasi,
            'date_of_entry'  => $date_of_entry,
            'attachment'     => $attachment,
            'kondisi'        => $kondisi,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        echo json_encode(["status" => "success", "message" => "Aset IT berhasil ditambahkan!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak valid."]);
}
