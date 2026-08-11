<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

// Barang Masuk/Keluar is ADMIN-only: ordinary users never see these records.
require_admin();
$module = strtolower($_GET['module'] ?? '');
if (!in_array($module, BarangService::MODULES, true)) {
    json_response(['status' => 'error', 'message' => 'Module tidak valid.']);
}

$data = BarangService::listAll($conn, $module, 'it');
json_response(['status' => 'success', 'data' => $data]);
