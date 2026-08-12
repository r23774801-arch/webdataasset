<?php
/**
 * approve_user.php — ADMIN approves/rejects a pending user registration.
 *
 * Rules (server-side, authoritative):
 *  - Requires the ADMIN role (require_admin).
 *  - Approved: copies the queued account into users (role = 'user',
 *    status = 'Aktif'), then marks the request Approved.
 *  - Rejected: keeps the row, stores the reason, status = 'Rejected'.
 *  - Idempotent: already-reviewed requests are not re-processed.
 */
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../app/bootstrap.php';

require_admin();
$me = current_user();

$input = read_json_input();
$id     = (int)($input['id'] ?? 0);
$status = strtoupper(trim((string)($input['status'] ?? '')));
$reason = trim((string)($input['reason'] ?? ''));

if ($id <= 0) {
    json_response(['status' => 'error', 'message' => 'ID permintaan tidak valid.']);
}
if (!in_array($status, ['APPROVED', 'REJECTED'], true)) {
    json_response(['status' => 'error', 'message' => 'Status tidak valid.']);
}
if ($status === 'REJECTED' && $reason === '') {
    json_response(['status' => 'error', 'message' => 'Alasan penolakan wajib diisi.']);
}

$fetch = $conn->prepare("SELECT id, nrp, username, nama_lengkap, email, password, status FROM user_approvals WHERE id = ? LIMIT 1");
if (!$fetch) {
    json_response(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.']);
}
$fetch->bind_param('i', $id);
$fetch->execute();
$fetch->store_result();
if ($fetch->num_rows === 0) {
    $fetch->close();
    json_response(['status' => 'error', 'message' => 'Permintaan tidak ditemukan.']);
}
$fetch->bind_result($rowId, $rowNrp, $rowUsername, $rowNamaLengkap, $rowEmail, $rowPassword, $rowStatus);
$fetch->fetch();
$fetch->close();

if ($rowStatus !== 'Pending') {
    json_response(['status' => 'error', 'message' => 'Permintaan ini sudah diproses.']);
}

// Guard against NRP collisions that could have appeared while pending.
$checkUser = $conn->prepare("SELECT id FROM users WHERE nrp = ? LIMIT 1");
$checkUser->bind_param('s', $rowNrp);
$checkUser->execute();
$checkUser->store_result();
$nrpExists = $checkUser->num_rows > 0;
$checkUser->close();

if ($status === 'APPROVED') {
    if ($nrpExists) {
        // Mark rejected with a note instead of duplicating the account.
        $update = $conn->prepare("UPDATE user_approvals SET status = 'Rejected', rejection_reason = ?, reviewed_by = ?, reviewed_by_name = ?, review_date = NOW() WHERE id = ?");
        $dupReason = 'NRP sudah terdaftar di sistem saat persetujuan diproses.';
        $update->bind_param('sssi', $dupReason, $me['nrp'], $me['username'], $id);
        $update->execute();
        $update->close();
        json_response(['status' => 'error', 'message' => 'NRP sudah terdaftar di sistem. Permintaan ditolak otomatis.']);
    }

    // Approve: copy into users with the generic non-admin role.
    $role = 'user';
    $insert = $conn->prepare("INSERT INTO users (nrp, username, nama_lengkap, password, role, email, status) VALUES (?, ?, ?, ?, ?, ?, 'Aktif')");
    $insert->bind_param('ssssss', $rowNrp, $rowUsername, $rowNamaLengkap, $rowPassword, $role, $rowEmail);
    if (!$insert->execute()) {
        error_log('[approve_user] insert user failed: ' . $insert->error);
        $insert->close();
        json_response(['status' => 'error', 'message' => 'Gagal mengaktifkan akun.']);
    }
    $insert->close();

    $update = $conn->prepare("UPDATE user_approvals SET status = 'Approved', reviewed_by = ?, reviewed_by_name = ?, review_date = NOW() WHERE id = ?");
    $update->bind_param('ssi', $me['nrp'], $me['username'], $id);
    $update->execute();
    $update->close();

    AuditService::log($conn, 'Approved User Registration', 'user_approvals', $id, ['nrp' => $rowNrp, 'username' => $rowUsername, 'by' => $me['nrp']]);
    json_response(['status' => 'success', 'message' => 'Akun ' . $rowUsername . ' disetujui dan aktif.']);
}

// REJECTED
$update = $conn->prepare("UPDATE user_approvals SET status = 'Rejected', rejection_reason = ?, reviewed_by = ?, reviewed_by_name = ?, review_date = NOW() WHERE id = ?");
$update->bind_param('sssi', $reason, $me['nrp'], $me['username'], $id);
$update->execute();
$update->close();

AuditService::log($conn, 'Rejected User Registration', 'user_approvals', $id, ['nrp' => $rowNrp, 'username' => $rowUsername, 'by' => $me['nrp'], 'reason' => $reason]);
json_response(['status' => 'success', 'message' => 'Permintaan pendaftaran ' . $rowUsername . ' ditolak.']);
