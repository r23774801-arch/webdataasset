<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');
include 'koneksi.php';

// RBAC: Check user role for status update
$userRole = strtoupper($_SESSION['role'] ?? '');

if (!$userRole) {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Silakan login kembali."]);
    exit;
}

// RBAC: Admin cannot edit asset condition data (admin only approves stocktaking).
if ($userRole === 'ADMIN') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Admin tidak dapat mengubah data aset."]);
    exit;
}

// PHASE 4.15 — session lock: no asset edits while a stocktaking cycle is
// Pending or Approved (lock follows the role's own asset type).
require_once __DIR__ . '/app/bootstrap.php';
$lockAssetType = ($userRole === 'GA') ? 'GA' : 'IT';
if (ApprovalService::isStocktakingLocked($conn, $lockAssetType)) {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Stocktaking sedang berlangsung atau telah disetujui. Perubahan aset tidak diizinkan."]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if ($input && isset($input['id']) && isset($input['kondisi'])) {
    $id = $input['id'];
    $kondisi = $input['kondisi']; // 'Baik' or 'Rusak'

    // Determine which table to update based on user role
    // IT role can only update aset_it, GA role can only update aset_ga, Admin can update both
    $updated = false;
    
    if ($userRole === 'IT' || $userRole === 'ADMIN') {
        $query_it = "UPDATE aset_it SET kondisi = ? WHERE id = ?";
        $stmt_it = $conn->prepare($query_it);
        $stmt_it->bind_param("si", $kondisi, $id);
        if ($stmt_it->execute() && $stmt_it->affected_rows > 0) {
            $updated = true;
        }
    }
    
    if (!$updated && ($userRole === 'GA' || $userRole === 'ADMIN')) {
        $query_ga = "UPDATE aset_ga SET kondisi = ? WHERE id = ?";
        $stmt_ga = $conn->prepare($query_ga);
        $stmt_ga->bind_param("si", $kondisi, $id);
        if ($stmt_ga->execute() && $stmt_ga->affected_rows > 0) {
            $updated = true;
        }
    }

    if ($updated) {
        echo json_encode(["status" => "success", "message" => "Status aset berhasil diubah menjadi $kondisi"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal memperbarui status atau data tidak ditemukan"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak valid."]);
}
