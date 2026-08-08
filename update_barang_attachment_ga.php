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

$table = BarangService::table($module, 'ga');
if (!$table) {
    json_response(['status' => 'error', 'message' => 'Table tidak valid.']);
}

// Attachment-only update — all other transaction fields stay untouched.
$attachment = trim((string)($input['attachment'] ?? ''));
if (!empty($attachment) && strpos($attachment, 'uploads/') !== 0 && strpos($attachment, 'img/') !== 0) {
    $attachment = 'uploads/' . $attachment;
}

// When no attachment is provided the existing value is preserved (never blanked out).
if ($attachment === '') {
    $keepStmt = $conn->prepare("SELECT attachment FROM $table WHERE id = ?");
    $keepStmt->bind_param('i', $id);
    $keepStmt->execute();
    $keepStmt->bind_result($existingAttachment);
    if ($keepStmt->fetch()) {
        $attachment = trim((string)$existingAttachment);
    }
    $keepStmt->close();
}

$stmt = $conn->prepare("UPDATE $table SET attachment = ? WHERE id = ?");
$stmt->bind_param('si', $attachment, $id);
if (!$stmt->execute()) {
    error_log('[update_barang_attachment_ga] update failed: ' . $stmt->error);
    json_response(['status' => 'error', 'message' => 'Database error.']);
}

AuditService::log(
    $conn,
    'Updated Barang Attachment ' . ($module === 'masuk' ? 'Masuk' : 'Keluar') . ' GA',
    'barang_' . $module . '_ga',
    $id,
    ['attachment' => $attachment]
);

// Phase 4.20 — mirror the updated attachment to the sheet (upsert by nomor_tiket).
SpreadsheetService::syncBarang($conn, $module, 'ga', $id);

json_response(['status' => 'success', 'message' => 'Attachment berhasil diperbarui!']);
