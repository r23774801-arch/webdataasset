<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

require_login();
$user = current_user();

$nrp        = $conn->real_escape_string($user['nrp']);
$area       = '';
$department = '';

// area / department columns may not exist yet (before migration) — stay defensive.
$checkArea = $conn->query("SHOW COLUMNS FROM users LIKE 'area'");
$checkDept = $conn->query("SHOW COLUMNS FROM users LIKE 'department'");
$hasArea   = $checkArea && $checkArea->num_rows > 0;
$hasDept   = $checkDept && $checkDept->num_rows > 0;

if ($hasArea || $hasDept) {
    $cols       = array_filter(['area' => $hasArea, 'department' => $hasDept]);
    $selectCols = implode(', ', array_keys($cols));
    $result     = $conn->query("SELECT $selectCols FROM users WHERE nrp = '$nrp' LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $area       = (string)($row['area'] ?? '');
        $department = (string)($row['department'] ?? '');
    }
}

json_response([
    'status' => 'success',
    'data'   => [
        'nrp'        => $user['nrp'],
        'username'   => $user['username'],
        'role'       => $user['role'],
        'area'       => $area,
        'department' => $department,
    ],
]);
