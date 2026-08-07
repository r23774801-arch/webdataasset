<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

require_login();
$user = current_user();

// RBAC: ADMIN is monitoring/approval only — never allowed to transact barang.
deny_admin_transaction();

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

if ($id <= 0) {
    json_response(['status' => 'error', 'message' => 'ID tidak valid.']);
}

if (!BarangService::update($conn, $module, 'it', $id, $input)) {
    json_response(['status' => 'error', 'message' => 'Data tidak lengkap atau gagal diperbarui! Asset Name, Jumlah, dan Tanggal wajib diisi.']);
}

AuditService::log(
    $conn,
    'Updated Barang ' . ($module === 'masuk' ? 'Masuk' : 'Keluar') . ' IT',
    'barang_' . $module . '_it',
    $id,
    ['asset_name' => $input['asset_name'] ?? '', 'area' => $input['area'] ?? '']
);

// Phase 4.20 — mirror the updated transaction to the sheet (upsert by nomor_tiket).
SpreadsheetService::syncBarang($conn, $module, 'it', $id);

json_response(['status' => 'success', 'message' => 'Data barang ' . $module . ' IT berhasil diperbarui!']);
