<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

// Barang Masuk/Keluar is ADMIN-only: ordinary users never transact barang.
require_admin();

$input = read_json_input();
$id = (int)($input['id'] ?? 0);
$module = strtolower($input['module'] ?? '');
if (!in_array($module, BarangService::MODULES, true)) {
    json_response(['status' => 'error', 'message' => 'Module tidak valid.']);
}

if ($id <= 0 || !BarangService::delete($conn, $module, 'ga', $id)) {
    json_response(['status' => 'error', 'message' => 'Gagal menghapus data atau data tidak ditemukan.']);
}

AuditService::log(
    $conn,
    'Deleted Barang ' . ($module === 'masuk' ? 'Masuk' : 'Keluar') . ' GA',
    'barang_' . $module . '_ga',
    $id,
    []
);

json_response(['status' => 'success', 'message' => 'Data barang ' . $module . ' GA berhasil dihapus!']);
