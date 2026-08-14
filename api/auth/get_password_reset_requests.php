<?php
/**
 * get_password_reset_requests.php — list pending password-reset requests for
 * the Admin dashboard badge.
 *
 * Rules (server-side, authoritative):
 *  - Requires the ADMIN role (require_admin).
 *  - Uses prepared statements.
 */
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../app/bootstrap.php';

require_admin();

// Defensive: the password_reset_requests table may not exist yet if the
// DB migration (docs/migrate_db.php) has not been run. Treat it as "no
// requests" instead of a hard error.
$hasTable = $conn->query("SHOW TABLES LIKE 'password_reset_requests'");
if (!$hasTable || $hasTable->num_rows === 0) {
    json_response(['status' => 'success', 'data' => [], 'total' => 0]);
}

$statusFilter = strtolower(trim($_GET['status'] ?? ''));
$allowed = ['Pending', 'Processed'];
if ($statusFilter !== '' && in_array(ucfirst($statusFilter), $allowed, true)) {
    $statusFilter = ucfirst($statusFilter);
} else {
    $statusFilter = 'Pending';
}

$stmt = $conn->prepare("SELECT id, nrp, username, email, status, requested_at, processed_by, processed_at FROM password_reset_requests WHERE status = ? ORDER BY requested_at DESC");
if (!$stmt) {
    json_response(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.']);
}

$stmt->bind_param('s', $statusFilter);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = [
        'id'           => (int)$row['id'],
        'nrp'          => $row['nrp'],
        'username'     => $row['username'] ?? '',
        'email'        => $row['email'] ?? '',
        'status'       => $row['status'],
        'requested_at' => $row['requested_at'],
        'processed_by' => $row['processed_by'] ?? '',
        'processed_at' => $row['processed_at'] ?? '',
    ];
}
$stmt->close();

json_response(['status' => 'success', 'data' => $rows, 'total' => count($rows)]);