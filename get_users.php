<?php
/**
 * get_users.php — list all user accounts for the Admin "Data Akun" feature.
 *
 * Rules (server-side, authoritative):
 *  - Requires the ADMIN role (require_admin).
 *  - Uses prepared statements.
 *  - The password hash is NEVER selected or returned.
 *  - Returns the account status column only if it exists (pre-migration safe).
 */
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

require_admin();

// Defensive: the users.status column may not exist yet (pre-migration).
$checkStatus = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
$hasStatus   = $checkStatus && $checkStatus->num_rows > 0;

$statusCol = $hasStatus ? 'status, ' : '';

$stmt = $conn->prepare("SELECT id, nrp, username, role, email, area, department, $statusCol created_at FROM users ORDER BY role ASC, username ASC");
if (!$stmt) {
    json_response(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.']);
}

$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    if (!$hasStatus) {
        $row['status'] = 'Aktif';
    }
    $users[] = [
        'id'         => (int)$row['id'],
        'nrp'        => $row['nrp'],
        'username'   => $row['username'],
        'role'       => $row['role'],
        'email'      => $row['email'] ?? '',
        'area'       => $row['area'] ?? '',
        'department' => $row['department'] ?? '',
        'status'     => $row['status'] ?? 'Aktif',
        'created_at' => $row['created_at'] ?? '',
    ];
}
$stmt->close();

json_response(['status' => 'success', 'data' => $users, 'total' => count($users)]);
