<?php
/**
 * Phase 4.22 — Master Employee search (AJAX).
 *
 * Public endpoint used by the registration page autocomplete.
 * Query parameter: q (keyword). Requires at least 2 characters.
 * Case-insensitive partial match on NRP or employee name, LIMIT 20.
 */
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

include 'koneksi.php';
require_once __DIR__ . '/app/helpers.php';

$q = trim((string)($_GET['q'] ?? ''));

if (mb_strlen($q) < 2) {
    json_response(['status' => 'success', 'data' => []]);
}

// Backward compatible: if the table hasn't been migrated yet, return empty.
if (!table_exists($conn, 'master_employee')) {
    json_response(['status' => 'success', 'data' => []]);
}

$like = '%' . $q . '%';

$stmt = $conn->prepare(
    "SELECT nrp, employee_name, email
     FROM master_employee
     WHERE nrp LIKE ? OR employee_name LIKE ?
     ORDER BY employee_name ASC
     LIMIT 20"
);

if (!$stmt) {
    json_response(['status' => 'error', 'message' => 'Gagal menyiapkan query: ' . $conn->error]);
}

$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'nrp'           => $row['nrp'],
        'employee_name' => $row['employee_name'],
        'email'         => $row['email'] ?? '',
    ];
}

json_response(['status' => 'success', 'data' => $data]);
