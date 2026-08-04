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
$module = strtolower($input['module'] ?? '');
if (!in_array($module, BarangService::MODULES, true)) {
    json_response(['status' => 'error', 'message' => 'Module tidak valid.']);
}

$id = BarangService::create($conn, $module, 'ga', $input);
if ($id === null) {
    json_response(['status' => 'error', 'message' => 'Data tidak lengkap! Asset Name, Jumlah, dan Tanggal wajib diisi.']);
}

AuditService::log(
    $conn,
    'Created Barang ' . ($module === 'masuk' ? 'Masuk' : 'Keluar') . ' GA',
    'barang_' . $module . '_ga',
    $id,
    ['asset_name' => $input['asset_name'] ?? '', 'area' => $input['area'] ?? '']
);

// Mirror the new record to the matching Barang worksheet (best-effort only).
SpreadsheetService::sync(
    $module === 'masuk' ? SpreadsheetService::SHEET_BARANG_MASUK_GA : SpreadsheetService::SHEET_BARANG_KELUAR_GA,
    [
        'id'           => $id,
        'asset_number' => $input['asset_number'] ?? '',
        'asset_name'   => $input['asset_name'] ?? '',
        'jumlah'       => (int)($input['jumlah'] ?? 0),
        'supplier'     => $module === 'masuk' ? ($input['supplier'] ?? '') : '',
        'tanggal'      => $input['tanggal'] ?? '',
        'pic'          => $input['pic'] ?? '',
        'area'         => $input['area'] ?? '',
        'created_at'   => date('Y-m-d H:i:s'),
    ]
);

json_response(['status' => 'success', 'message' => 'Data barang ' . $module . ' GA berhasil disimpan!', 'id' => $id]);
