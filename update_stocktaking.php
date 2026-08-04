<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');
include 'koneksi.php';

// RBAC: Check if user is logged in
$userRole = strtoupper($_SESSION['role'] ?? '');
if (!$userRole) {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Silakan login kembali."]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id']) || !isset($input['table_type']) || !isset($input['action'])) {
    echo json_encode(["status" => "error", "message" => "Data tidak valid."]);
    exit;
}

$id = (int)$input['id'];
$table_type = $input['table_type']; // 'it' or 'ga'
$action = $input['action']; // 'submit_action' or 'create_document'
$condition = $input['condition'] ?? '';
$photo_path = $input['photo_path'] ?? '';

// Determine table name
$table = ($table_type === 'it') ? 'aset_it' : 'aset_ga';

// RBAC: Admin cannot change stocktaking data (admin only approves).
if ($userRole === 'ADMIN') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Admin hanya dapat menyetujui stocktaking, tidak dapat mengubah data stocktaking."]);
    exit;
}

// RBAC: IT can only update aset_it, GA can only update aset_ga
if ($userRole === 'IT' && $table !== 'aset_it') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Role IT hanya dapat melakukan stocktaking pada aset IT."]);
    exit;
}
if ($userRole === 'GA' && $table !== 'aset_ga') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Role GA hanya dapat melakukan stocktaking pada aset GA."]);
    exit;
}

// RBAC: Users cannot edit stocktaking data while a submission is awaiting approval (Pending).
require_once __DIR__ . '/app/bootstrap.php';
$nrpForGuard = $_SESSION['nrp'] ?? '';
$assetTypeForGuard = strtoupper($table_type);
if ($nrpForGuard !== '' && in_array($assetTypeForGuard, ['IT', 'GA'], true)) {
    $pendingSubmission = ApprovalService::getLatestForUser($conn, $nrpForGuard, $assetTypeForGuard);
    if ($pendingSubmission && $pendingSubmission['status'] === ApprovalService::STATUS_PENDING) {
        echo json_encode(["status" => "error", "message" => "Akses ditolak. Stocktaking masih menunggu persetujuan admin (status Pending). Anda tidak dapat mengubah data stocktaking sekarang."]);
        exit;
    }
}

try {
    if ($action === 'submit_action') {
        $kondisiMap = [
            'Normal' => 'Normal',
            'Broken' => 'Broken',
            'Lost' => 'Lost'
        ];
        $condition = trim($condition);
        $photo_path = trim($photo_path);

        if ($photo_path === '') {
            if ($condition === '') {
                echo json_encode(["status" => "error", "message" => "Kondisi aset wajib dipilih."]);
                exit;
            }
            if (strcasecmp($condition, 'Transfer') === 0) {
                echo json_encode(["status" => "error", "message" => "Kondisi Transfer harus diproses melalui formulir transfer aset."]);
                exit;
            }

            // First step: save only the stocktaking condition and keep status pending.
            $query = "UPDATE $table SET stocktaking_condition = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $condition, $id);
            
            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Kondisi aset berhasil disimpan. Silakan upload foto untuk menyelesaikan stocktaking."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
            }
            exit;
        }

        if ($condition === '') {
            $existingQuery = "SELECT stocktaking_condition FROM $table WHERE id = ?";
            $existingStmt = $conn->prepare($existingQuery);
            $existingStmt->bind_param("i", $id);
            $existingStmt->execute();
            $existingStmt->bind_result($savedCondition);
            if ($existingStmt->fetch()) {
                $condition = $savedCondition ?? '';
            }
        }

        if ($condition === '') {
            echo json_encode(["status" => "error", "message" => "Kondisi aset belum tersimpan."]);
            exit;
        }

        // Final step: photo is present, complete stocktaking.
        $query = "UPDATE $table SET stocktaking_status = 'Stocktaked', stocktaking_condition = ?, stocktaking_photo = ?, kondisi = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $kondisiVal = $kondisiMap[$condition] ?? $condition;
        $stmt->bind_param("sssi", $condition, $photo_path, $kondisiVal, $id);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Kondisi aset berhasil dilaporkan. Status: Stocktaked."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
        }
    } elseif ($action === 'create_document') {
        // Step 2: Create document - change status to "Stocktaked"
        $query = "UPDATE $table SET stocktaking_status = 'Stocktaked' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Dokumen berhasil dibuat. Status: Stocktaked."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
        }
    } elseif ($action === 'transfer') {
        // ==========================================
        // PHASE 4.5 — Transfer workflow
        // Moves an asset to a new area, records history,
        // audits the change, and mirrors to the spreadsheet.
        // ==========================================
        $newArea       = trim((string)($input['new_area'] ?? ''));
        $newDepartment = trim((string)($input['new_department'] ?? ''));
        $transferPic   = trim((string)($input['transfer_pic'] ?? ''));
        $transferDate  = trim((string)($input['transfer_date'] ?? ''));
        $remarks       = trim((string)($input['remarks'] ?? ''));

        if ($newArea === '') {
            echo json_encode(["status" => "error", "message" => "Area tujuan wajib diisi."]);
            exit;
        }
        if ($transferDate === '') {
            $transferDate = date('Y-m-d');
        }

        // Load the current asset so we can record old values.
        $curStmt = $conn->prepare("SELECT asset_number, nama_barang, area, location_note, pic FROM $table WHERE id = ?");
        $curStmt->bind_param("i", $id);
        $curStmt->execute();
        $cur = $curStmt->get_result()->fetch_assoc();
        if (!$cur) {
            echo json_encode(["status" => "error", "message" => "Aset tidak ditemukan."]);
            exit;
        }

        $oldArea       = trim((string)($cur['area'] ?? ''));
        $oldDepartment = trim((string)($cur['location_note'] ?? ''));
        $oldPic        = trim((string)($cur['pic'] ?? ''));
        $assetNumber   = trim((string)($cur['asset_number'] ?? ''));
        $assetName     = trim((string)($cur['nama_barang'] ?? ''));

        // 1) Update the asset location + condition to Transfer.
        //    (stocktaking_condition mirrors kondisi so the asset page
        //     and reports both show 'Transfer' consistently.)
        // 2) Record the transfer history — both steps run in one
        //    transaction so the asset move and its history stay atomic.
        $transferBy = $_SESSION['username'] ?? ($_SESSION['nrp'] ?? '');
        $assetType  = strtoupper($table_type);

        $conn->begin_transaction();
        try {
            $query = "UPDATE $table SET area = ?, location_note = ?, pic = ?, kondisi = 'Transfer', stocktaking_condition = 'Transfer' WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $newArea, $newDepartment, $transferPic, $id);
            if (!$stmt->execute()) {
                throw new Exception("Database error: " . $stmt->error);
            }

            $hist = $conn->prepare(
                "INSERT INTO transfer_history
                    (asset_id, asset_number, asset_name, asset_type, old_area, new_area,
                     old_department, new_department, pic, transfer_date, transferred_by, remarks)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $hist->bind_param(
                "isssssssssss",
                $id, $assetNumber, $assetName, $assetType,
                $oldArea, $newArea, $oldDepartment, $newDepartment,
                $transferPic, $transferDate, $transferBy, $remarks
            );
            if (!$hist->execute()) {
                throw new Exception("Database error: " . $hist->error);
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            exit;
        }

        // 3) Audit the transfer (Transferred Asset — old/new values).
        AuditService::log($conn, 'Transferred Asset', $table, (int)$id, [
            'old' => ['area' => $oldArea, 'department' => $oldDepartment],
            'new' => ['area' => $newArea, 'department' => $newDepartment],
            'transfer_date' => $transferDate,
            'transferred_by' => $transferBy,
        ]);

        // 4) Mirror the transfer to the Transfer_History worksheet (best-effort).
        SpreadsheetService::sync(SpreadsheetService::SHEET_TRANSFER_HISTORY, [
            'asset_number'   => $assetNumber,
            'asset_name'     => $assetName,
            'asset_type'     => $assetType,
            'old_area'       => $oldArea,
            'new_area'       => $newArea,
            'old_department' => $oldDepartment,
            'new_department' => $newDepartment,
            'pic'            => $transferPic,
            'transfer_date'  => $transferDate,
            'transferred_by' => $transferBy,
            'remarks'        => $remarks,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        echo json_encode(["status" => "success", "message" => "Aset berhasil dipindahkan ke area " . htmlspecialchars($newArea, ENT_QUOTES, 'UTF-8') . ". Kondisi: Transfer."]);
    } elseif ($action === 'update_utilisasi') {
        // Step 3: Update utilisasi status
        $utilisasi_value = $input['utilisasi'] ?? 'No';
        
        $query = "UPDATE $table SET utilisasi = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $utilisasi_value, $id);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Utilisasi berhasil diubah ke '$utilisasi_value'."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Aksi tidak dikenal."]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
}
?>
