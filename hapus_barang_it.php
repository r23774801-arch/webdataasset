<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

require_login();
$user = current_user();

// RBAC: only IT may manage IT barang.
if (!BarangService::canManage($user['role'], 'it')) {
    json_response(['status' => 'error', 'message' => 'Akses ditolak. Anda tidak memiliki izin untuk mengelola barang IT.']);
}

$input = read_json_input();
$id = (int)($input['id'] ?? 0);
$module = strtolower($input['module'] ?? '');
if (!in_array($module, BarangService::MODULES, true)) {
    json_response(['status' => 'error', 'message' => 'Module tidak valid.']);
}

if ($id <= 0 || !BarangService::delete($conn, $module, 'it', $id)) {
    json_response(['status' => 'error', 'message' => 'Gagal menghapus data atau data tidak ditemukan.']);
}

AuditService::log(
    $conn,
    'Deleted Barang ' . ($module === 'masuk' ? 'Masuk' : 'Keluar') . ' IT',
    'barang_' . $module . '_it',
    $id,
    []
);

json_response(['status' => 'success', 'message' => 'Data barang ' . $module . ' IT berhasil dihapus!']);
