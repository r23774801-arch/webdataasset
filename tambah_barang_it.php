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
$module = strtolower($input['module'] ?? '');
if (!in_array($module, BarangService::MODULES, true)) {
    json_response(['status' => 'error', 'message' => 'Module tidak valid.']);
}

$id = BarangService::create($conn, $module, 'it', $input);
if ($id === null) {
    json_response(['status' => 'error', 'message' => 'Data tidak lengkap! Asset Name, Jumlah, dan Tanggal wajib diisi.']);
}

AuditService::log(
    $conn,
    'Created Barang Masuk' . ($module === 'keluar' ? ' Keluar' : ''),
    'barang_' . $module . '_it',
    $id,
    ['asset_name' => $input['asset_name'] ?? '', 'area' => $input['area'] ?? '']
);

// Mirror the new record to the matching Barang worksheet (best-effort only).
SpreadsheetService::sync(
    $module === 'masuk' ? SpreadsheetService::SHEET_BARANG_MASUK_IT : SpreadsheetService::SHEET_BARANG_KELUAR_IT,
    [
        'id'           => $id,
        'asset_number' => $input['asset_number'] ?? '',
        'nomor_tiket'  => $input['nomor_tiket'] ?? '',
        'asset_name'   => $input['asset_name'] ?? '',
        'jumlah'       => (int)($input['jumlah'] ?? 0),
        'unit'         => $input['unit'] ?? '',
        'supplier'     => $module === 'masuk' ? ($input['supplier'] ?? '') : '',
        'tanggal'      => $input['tanggal'] ?? '',
        'pic'          => $input['pic'] ?? '',
        'area'         => $input['area'] ?? '',
        'attachment'   => $input['attachment'] ?? '',
        'created_at'   => date('Y-m-d H:i:s'),
    ],
    ($input['nomor_tiket'] ?? '') !== '' ? 'nomor_tiket' : 'id'
);

// Phase 4.12B — notify every administrator about the new transaction
// (best-effort: a mail failure never fails the transaction).
$emailSent = false;
try {
    $adminEmails = MailService::adminEmails($conn);
    if (empty($adminEmails)) {
        error_log('[tambah_barang_it] No admin e-mail address found in users table; barang notification skipped.');
        AuditService::log($conn, 'Notification Warning', 'barang_' . $module . '_it', (int)$id, [
            'reason' => 'No admin e-mail address in users table; barang notification not sent.',
        ]);
    } else {
        $sentCount = MailService::notifyAdminsBarangTransaction($conn, [
            'module'       => $module,
            'department'   => 'IT',
            'asset_number' => $input['asset_number'] ?? '',
            'nomor_tiket'  => $input['nomor_tiket'] ?? '',
            'asset_name'   => $input['asset_name'] ?? '',
            'jumlah'       => (int)($input['jumlah'] ?? 0),
            'unit'         => $input['unit'] ?? '',
            'supplier'     => $module === 'masuk' ? ($input['supplier'] ?? '') : '',
            'pic'          => $input['pic'] ?? '',
            'area'         => $input['area'] ?? '',
            'tanggal'      => $input['tanggal'] ?? '',
            'attachment'   => $input['attachment'] ?? '',
            'user_name'    => $user['username'] ?? '',
            'user_nrp'     => $user['nrp'] ?? '',
            'timestamp'    => date('Y-m-d H:i:s'),
        ]);
        $emailSent = ($sentCount > 0);
        if ($sentCount < count($adminEmails)) {
            error_log('[tambah_barang_it] Notification sent to ' . $sentCount . '/' . count($adminEmails) . ' admins for barang_' . $module . '_it #' . $id);
        }
    }
} catch (\Throwable $e) {
    // E-mail must never break the transaction response — log and continue.
    error_log('[tambah_barang_it] Email notification failed: ' . $e->getMessage());
}

json_response(['status' => 'success', 'message' => 'Data barang ' . $module . ' IT berhasil disimpan!', 'id' => $id, 'email_sent' => $emailSent]);
