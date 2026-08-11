<?php
/**
 * get_user_approvals.php — list pending/processed user registration requests
 * for the Admin "Persetujuan User" feature.
 *
 * Rules (server-side, authoritative):
 *  - Requires the ADMIN role (require_admin).
 *  - Uses prepared statements.
 *  - The password hash is NEVER selected or returned.
 */
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

require_admin();

$statusFilter = strtolower(trim($_GET['status'] ?? ''));
$allowed = ['Pending', 'Approved', 'Rejected'];
if ($statusFilter !== '' && in_array(ucfirst($statusFilter), $allowed, true)) {
    $statusFilter = ucfirst($statusFilter);
} else {
    $statusFilter = '';
}

if ($statusFilter !== '') {
    $stmt = $conn->prepare("SELECT id, nrp, username, nama_lengkap, email, status, requested_at, reviewed_by, reviewed_by_name, review_date, rejection_reason FROM user_approvals WHERE status = ? ORDER BY requested_at DESC");
    $stmt->bind_param('s', $statusFilter);
} else {
    $stmt = $conn->prepare("SELECT id, nrp, username, nama_lengkap, email, status, requested_at, reviewed_by, reviewed_by_name, review_date, rejection_reason FROM user_approvals ORDER BY requested_at DESC");
}
if (!$stmt) {
    json_response(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.']);
}

$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = [
        'id'              => (int)$row['id'],
        'nrp'             => $row['nrp'],
        'username'        => $row['username'],
        'nama_lengkap'    => $row['nama_lengkap'] ?? '',
        'email'           => $row['email'] ?? '',
        'status'          => $row['status'],
        'requested_at'    => $row['requested_at'],
        'reviewed_by'     => $row['reviewed_by'] ?? '',
        'reviewed_by_name'=> $row['reviewed_by_name'] ?? '',
        'review_date'     => $row['review_date'] ?? '',
        'rejection_reason'=> $row['rejection_reason'] ?? '',
    ];
}
$stmt->close();

json_response(['status' => 'success', 'data' => $rows, 'total' => count($rows)]);
