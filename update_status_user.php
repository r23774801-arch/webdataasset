<?php
/**
 * update_status_user.php — Admin toggles a user account Aktif/Nonaktif.
 *
 * Rules (server-side, authoritative):
 *  - Requires the ADMIN role (require_admin).
 *  - Uses prepared statements.
 *  - Only the values Aktif / Nonaktif are accepted.
 *  - An Admin can never deactivate their OWN account (self-lockout guard).
 *  - The target user is not logged out; a Nonaktif account simply cannot
 *    log in again (enforced in login.php).
 */
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

require_admin();

// Defensive: the users.status column must exist for this feature.
$checkStatus = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
if (!$checkStatus || $checkStatus->num_rows === 0) {
    json_response(['status' => 'error', 'message' => 'Kolom status belum tersedia. Jalankan migrate_db.php terlebih dahulu.']);
}

$input = read_json_input();
$nrp     = trim((string)($input['nrp'] ?? ''));
$status  = ucfirst(strtolower(trim((string)($input['status'] ?? ''))));

if ($nrp === '') {
    json_response(['status' => 'error', 'message' => 'NRP user tidak boleh kosong.']);
}
if (!in_array($status, ['Aktif', 'Nonaktif'], true)) {
    json_response(['status' => 'error', 'message' => 'Status tidak valid.']);
}

$me = current_user();
if ($status === 'Nonaktif' && $nrp === $me['nrp']) {
    json_response(['status' => 'error', 'message' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
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

// Akun ADMIN adalah akun sistem — tidak boleh dinonaktifkan oleh siapa pun.
$role = $conn->prepare("SELECT role FROM users WHERE nrp = ? LIMIT 1");
if ($role) {
    $role->bind_param('s', $nrp);
    $role->execute();
    $roleRow = $role->get_result()->fetch_assoc();
    $role->close();
    if (strtoupper((string)($roleRow['role'] ?? '')) === 'ADMIN') {
        json_response(['status' => 'error', 'message' => 'Akun admin tidak dapat dinonaktifkan.']);
    }
}

$update = $conn->prepare("UPDATE users SET status = ? WHERE nrp = ?");
if (!$update) {
    json_response(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.']);
}
$update->bind_param('ss', $status, $nrp);
if (!$update->execute()) {
    error_log('[update_status_user] update failed: ' . $conn->error);
    json_response(['status' => 'error', 'message' => 'Gagal mengubah status.']);
}

AuditService::log($conn, 'Ubah Status User', 'users', null, ['by' => $me['nrp'], 'target' => $nrp, 'status' => $status]);

json_response(['status' => 'success', 'message' => 'Status akun berhasil diubah menjadi ' . $status . '.']);
