<?php
/**
 * get_approved_submissions.php — list APPROVED stocktaking submissions so the
 * admin can link a Barang Masuk/Keluar transaction to the submission that drove
 * the movement. Admin-only (Barang pages are ADMIN-only).
 */
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

require_admin();

$type = strtoupper(trim((string)($_GET['type'] ?? 'it')));
if (!in_array($type, ['IT', 'GA'], true)) {
    json_response(['status' => 'error', 'message' => 'Tipe asset tidak valid.']);
}

$data = ApprovalService::getApprovedForBarang($conn, $type);

json_response(['status' => 'success', 'data' => $data]);
