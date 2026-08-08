<?php
/**
 * hapus_user.php — Admin deletes a user account.
 *
 * Rules (server-side, authoritative):
 *  - Requires the ADMIN role (require_admin).
 *  - Uses prepared statements.
 *  - An Admin can NEVER delete their OWN account (compared against the
 *    session NRP, not any client-supplied value).
 *  - The target user is not logged out by this endpoint; deleting an
 *    account simply prevents any future login.
 */
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

require_admin();

$input = read_json_input();
$nrp   = trim((string)($input['nrp'] ?? ''));

if ($nrp === '') {
    json_response(['status' => 'error', 'message' => 'NRP user tidak boleh kosong.']);
}

$me = current_user();
if ($nrp === $me['nrp']) {
    json_response(['status' => 'error', 'message' => 'Anda tidak dapat menghapus akun sendiri.']);
}

// Target must exist.
$check = $conn->prepare("SELECT nrp FROM users WHERE nrp = ? LIMIT 1");
if (!$check) {
    json_response(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.']);
}
$check->bind_param('s', $nrp);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    $check->close();
    json_response(['status' => 'error', 'message' => 'User tidak ditemukan.']);
}
$check->close();

$delete = $conn->prepare("DELETE FROM users WHERE nrp = ?");
if (!$delete) {
    json_response(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.']);
}
$delete->bind_param('s', $nrp);
if (!$delete->execute()) {
    error_log('[hapus_user] delete failed: ' . $conn->error);
    json_response(['status' => 'error', 'message' => 'Gagal menghapus user.']);
}

AuditService::log($conn, 'Hapus User', 'users', null, ['by' => $me['nrp'], 'target' => $nrp]);

json_response(['status' => 'success', 'message' => 'Akun berhasil dihapus.']);
