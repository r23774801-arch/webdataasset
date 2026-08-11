<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');
include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

// RBAC: All non-ADMIN roles may add assets (IT and GA). ADMIN is monitoring/approval only.
deny_admin_transaction();
if (!isset($_SESSION['role']) || trim((string)$_SESSION['role']) === '') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Silakan login terlebih dahulu."]);
    exit;
}

// Phase 4.11 — asset-type-wide lock: no new GA assets while a GA stocktaking
// cycle is Pending or Approved (single source of truth: stocktaking_submissions.status).
// Rejecting the submission automatically unlocks creation again.
if (ApprovalService::isAssetCreationLocked($conn, 'GA')) {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Stocktaking aset GA sedang berlangsung atau telah disetujui. Penambahan aset GA baru tidak diizinkan."]);
    exit;
}

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

    // Asset Number OR Serial Number must be provided (both empty is rejected)
    if (trim($asset_number) === '' && trim($serial_number) === '') {
        echo json_encode(["status" => "error", "message" => "Isi Asset Number atau Serial Number minimal satu."]);
        exit;
    }

    // Photo is mandatory for new assets
    if (trim($attachment) === '') {
        echo json_encode(["status" => "error", "message" => "Photo wajib diunggah."]);
        exit;
    }

    $query = "INSERT INTO aset_ga (asset_number, nama_barang, serial_number, asset_class, pic, area, location_note, utilisasi, date_of_entry, attachment, kondisi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log('[tambah_aset_ga] prepare failed: ' . $conn->error);
        echo json_encode(["status" => "error", "message" => "Database error."]);
        exit;
    }
    
    $stmt->bind_param("sssssssssss", $asset_number, $nama_barang, $serial_number, $asset_class, $pic, $area, $location_note, $utilisasi, $date_of_entry, $attachment, $kondisi);

    if ($stmt->execute()) {
        $newId = (int)$conn->insert_id;
        AuditService::log($conn, 'Created Asset', 'aset_ga', $newId, ['nama_barang' => $nama_barang, 'area' => $area]);

        // Mirror the new record to the Asset_GA worksheet (best-effort only).
        SpreadsheetService::sync(SpreadsheetService::SHEET_ASSET_GA, [
            'asset_number'   => $asset_number,
            'nama_barang'    => $nama_barang,
            'serial_number'  => $serial_number,
            'asset_class'    => $asset_class,
            'pic'            => $pic,
            'area'           => $area,
            'location_note'  => $location_note,
            'utilisasi'      => $utilisasi,
            'date_of_entry'  => $date_of_entry,
            'attachment'     => $attachment,
            'kondisi'        => $kondisi,
            'stocktaking_status' => 'Pending',
            'created_at'     => date('Y-m-d H:i:s'),
        ], $asset_number !== '' ? 'asset_number' : null);

        // PHASE 4.15 — notify every administrator (best-effort; the asset insert
        // stays valid if mail fails). Recipients come only from the users table.
        try {
            MailService::notifyAdminsAssetCreated($conn, [
                'asset_type'    => 'GA',
                'asset_number'  => $asset_number,
                'nama_barang'   => $nama_barang,
                'serial_number' => $serial_number,
                'asset_class'   => $asset_class,
                'pic'           => $pic,
                'area'          => $area,
                'location_note' => $location_note,
                'utilisasi'     => ($utilisasi !== '' ? $utilisasi : 'No'),
                'date_of_entry' => $date_of_entry,
                'attachment'    => $attachment,
                'user_name'     => $_SESSION['username'] ?? '',
                'user_nrp'      => $_SESSION['nrp'] ?? '',
                'timestamp'     => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // E-mail must never break the insert response — log and continue.
            error_log('[tambah_aset_ga] Email notification failed: ' . $e->getMessage());
        }

        echo json_encode(["status" => "success", "message" => "Aset GA berhasil ditambahkan!"]);
    } else {
        error_log('[tambah_aset_ga] insert failed: ' . $stmt->error);
        echo json_encode(["status" => "error", "message" => "Database error."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak valid."]);
}
