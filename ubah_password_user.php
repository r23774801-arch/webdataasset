<?php
/**
 * ubah_password_user.php — Admin changes another user's password.
 *
 * Rules (server-side, authoritative):
 *  - Requires the ADMIN role (require_admin).
 *  - Uses prepared statements and password_hash().
 *  - The new password must match its confirmation on the SERVER too.
 *  - Only updates users.password — the target user's current session
 *    (which stores nrp/username/role, not the password) stays valid,
 *    so the target is never logged out.
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
$passwordBaru = (string)($input['password_baru'] ?? '');
$passwordKonfirmasi = (string)($input['password_konfirmasi'] ?? '');

if ($nrp === '') {
    json_response(['status' => 'error', 'message' => 'NRP user tidak boleh kosong.']);
}
if (strlen($passwordBaru) < 4) {
    json_response(['status' => 'error', 'message' => 'Password baru minimal 4 karakter.']);
}
if ($passwordBaru !== $passwordKonfirmasi) {
    json_response(['status' => 'error', 'message' => 'Konfirmasi password tidak cocok.']);
}

// Target must exist (prepared).
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

$hash = password_hash($passwordBaru, PASSWORD_DEFAULT);

$update = $conn->prepare("UPDATE users SET password = ? WHERE nrp = ?");
if (!$update) {
    json_response(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.']);
}
$update->bind_param('ss', $hash, $nrp);
if (!$update->execute()) {
    error_log('[ubah_password_user] update failed: ' . $conn->error);
    json_response(['status' => 'error', 'message' => 'Gagal mengubah password.']);
}

$me = current_user();
AuditService::log($conn, 'Ubah Password User', 'users', null, ['by' => $me['nrp'], 'target' => $nrp]);

json_response(['status' => 'success', 'message' => 'Password berhasil diubah.']);
