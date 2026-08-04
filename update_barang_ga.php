<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

require_login();
$user = current_user();

// RBAC: only GA may manage GA barang.
if (!BarangService::canManage($user['role'], 'ga')) {
    json_response(['status' => 'error', 'message' => 'Akses ditolak. Anda tidak memiliki izin untuk mengelola barang GA.']);
}

$input = read_json_input();
$id = (int)($input['id'] ?? 0);
$module = strtolower($input['module'] ?? '');
if (!in_array($module, BarangService::MODULES, true)) {
    json_response(['status' => 'error', 'message' => 'Module tidak valid.']);
}

if ($id <= 0) {
    json_response(['status' => 'error', 'message' => 'ID tidak valid.']);
}

if (!BarangService::update($conn, $module, 'ga', $id, $input)) {
    json_response(['status' => 'error', 'message' => 'Data tidak lengkap atau gagal diperbarui! Asset Name, Jumlah, dan Tanggal wajib diisi.']);
}

AuditService::log(
    $conn,
    'Updated Barang ' . ($module === 'masuk' ? 'Masuk' : 'Keluar') . ' GA',
    'barang_' . $module . '_ga',
    $id,
    ['asset_name' => $input['asset_name'] ?? '', 'area' => $input['area'] ?? '']
);

json_response(['status' => 'success', 'message' => 'Data barang ' . $module . ' GA berhasil diperbarui!']);
