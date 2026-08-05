<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

require_login();
$user = current_user();
$role = $user['role'];

// -- Single submission detail (with asset snapshot) --
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $sub = ApprovalService::getById($conn, (int)$_GET['id']);
    if (!$sub) {
        json_response(['status' => 'error', 'message' => 'Data tidak ditemukan.']);
    }
    if ($role !== 'ADMIN' && $sub['submitted_by'] !== $user['nrp']) {
        json_response(['status' => 'error', 'message' => 'Akses ditolak.']);
    }
    json_response(['status' => 'success', 'data' => $sub]);
}

$filters = [
    'status'     => $_GET['status'] ?? '',
    'asset_type' => $_GET['asset_type'] ?? '',
];

// -- Asset creation lock status for an asset type (Phase 4.11, used by the asset pages) --
if (isset($_GET['lock']) && $_GET['lock'] === '1' && !empty($filters['asset_type'])) {
    json_response([
        'status' => 'success',
        'data'   => ['locked' => ApprovalService::isAssetCreationLocked($conn, $filters['asset_type'])],
    ]);
}

// -- Latest submission for the current user + asset type (used by the asset pages) --
if (isset($_GET['latest']) && $_GET['latest'] === '1' && !empty($filters['asset_type'])) {
    $sub = ApprovalService::getLatestForUser($conn, $user['nrp'], $filters['asset_type']);
    json_response(['status' => 'success', 'data' => $sub]);
}

// -- List --
$mine = isset($_GET['mine']) && $_GET['mine'] === '1';
if ($role !== 'ADMIN' || $mine) {
    $filters['submitted_by'] = $user['nrp'];
}

$list = ApprovalService::getSubmissions($conn, array_filter($filters));

json_response(['status' => 'success', 'data' => $list, 'total' => count($list)]);
