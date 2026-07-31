<?php
session_start();
header('Content-Type: application/json');
include 'koneksi.php';

// RBAC: Only admin can verify/approve assets
if (!isset($_SESSION['role']) || strtoupper($_SESSION['role']) !== 'ADMIN') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Hanya admin yang dapat memverifikasi data aset."]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if ($input && isset($input['id']) && isset($input['status'])) {
    $id = $input['id'];
    $status = $input['status']; // 'Approved' atau 'Rejected'

    $query = "UPDATE aset_ga SET status_approval = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Status aset berhasil diubah menjadi " . $status]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal memperbarui status di database."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak valid."]);
}
