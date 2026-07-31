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

// RBAC: IT can only update aset_it, GA can only update aset_ga, Admin can update both
if ($userRole === 'IT' && $table !== 'aset_it') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Role IT hanya dapat melakukan stocktaking pada aset IT."]);
    exit;
}
if ($userRole === 'GA' && $table !== 'aset_ga') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Role GA hanya dapat melakukan stocktaking pada aset GA."]);
    exit;
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
