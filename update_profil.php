<?php
/**
 * update_profil.php — save the logged-in user's own e-mail address.
 *
 * Rules (server-side, authoritative):
 *  - Requires an authenticated session (any role, including Admin).
 *  - Validates the e-mail format with FILTER_VALIDATE_EMAIL.
 *  - Rejects an e-mail already used by another account (users.email).
 *  - Updates only the logged-in user's own row (keyed by users.nrp).
 */
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

require_login();
$user = current_user();

$input = read_json_input();
$email = trim((string)($input['email'] ?? ''));

if ($email === '') {
    json_response(['status' => 'error', 'message' => 'Email tidak boleh kosong.']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['status' => 'error', 'message' => 'Format email tidak valid.']);
}

// users.email is VARCHAR(100) — reject anything longer instead of truncating.
if (strlen($email) > 100) {
    json_response(['status' => 'error', 'message' => 'Email terlalu panjang (maksimal 100 karakter).']);
}

// Defensive: the users.email column may not exist yet (pre-migration).
$checkEmail = $conn->query("SHOW COLUMNS FROM users LIKE 'email'");
if (!$checkEmail || $checkEmail->num_rows === 0) {
    json_response(['status' => 'error', 'message' => 'Kolom email belum tersedia. Jalankan migrate_db.php terlebih dahulu.']);
}

$nrp = (string)$user['nrp'];

// Duplicate check: the e-mail must not belong to any other account.
// LOWER() comparison keeps the check case-insensitive regardless of collation.
$stmt = $conn->prepare("SELECT nrp FROM users WHERE LOWER(email) = LOWER(?) AND nrp <> ? LIMIT 1");
if (!$stmt) {
    json_response(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.']);
}
$stmt->bind_param('ss', $email, $nrp);
$stmt->execute();
$dup = $stmt->get_result();
if ($dup && $dup->num_rows > 0) {
    $stmt->close();
    json_response(['status' => 'error', 'message' => 'Email sudah digunakan oleh akun lain.']);
}
$stmt->close();

$update = $conn->prepare("UPDATE users SET email = ? WHERE nrp = ?");
if (!$update) {
    json_response(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.']);
}
$update->bind_param('ss', $email, $nrp);
if (!$update->execute()) {
    json_response(['status' => 'error', 'message' => 'Gagal menyimpan email: ' . $conn->error]);
}

AuditService::log($conn, 'Updated Profile Email', 'users', null, ['nrp' => $nrp, 'email' => $email]);

json_response(['status' => 'success', 'message' => 'Email berhasil disimpan.', 'data' => ['email' => $email]]);
