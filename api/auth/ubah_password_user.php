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

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../app/bootstrap.php';

require_admin();

$input = read_json_input();
$nrp   = trim((string)($input['nrp'] ?? ''));
$passwordBaru = (string)($input['password_baru'] ?? '');
$passwordKonfirmasi = (string)($input['password_konfirmasi'] ?? '');

if ($nrp === '') {
    json_response(['status' => 'error', 'message' => 'NRP user tidak boleh kosong.']);
}
// Password policy identik dengan daftar akun: min 8, huruf besar, huruf
// kecil, angka, dan simbol. Diterapkan di server (authoritative).
if (strlen($passwordBaru) < 8) {
    json_response(['status' => 'error', 'message' => 'Password baru minimal 8 karakter.']);
}
if (!preg_match('/[A-Z]/', $passwordBaru)) {
    json_response(['status' => 'error', 'message' => 'Password harus mengandung huruf besar (A-Z).']);
}
if (!preg_match('/[a-z]/', $passwordBaru)) {
    json_response(['status' => 'error', 'message' => 'Password harus mengandung huruf kecil (a-z).']);
}
if (!preg_match('/\d/', $passwordBaru)) {
    json_response(['status' => 'error', 'message' => 'Password harus mengandung angka (0-9).']);
}
if (!preg_match('/[^A-Za-z0-9]/', $passwordBaru)) {
    json_response(['status' => 'error', 'message' => 'Password harus mengandung simbol (!@#$% dll).']);
}
if ($passwordBaru !== $passwordKonfirmasi) {
    json_response(['status' => 'error', 'message' => 'Konfirmasi password tidak cocok.']);
}

// Target must exist (prepared).
$check = $conn->prepare("SELECT nrp, username, nama_lengkap, email FROM users WHERE nrp = ? LIMIT 1");
if (!$check) {
    json_response(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.']);
}
$check->bind_param('s', $nrp);
$check->execute();
$result = $check->get_result();
$target = $result->fetch_assoc();
$check->close();
if (!$target) {
    json_response(['status' => 'error', 'message' => 'User tidak ditemukan.']);
}

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

// Flip any pending password-reset requests for this NRP to Processed, so the
// admin dashboard badge clears once the password has actually been changed.
try {
    $markDone = $conn->prepare("UPDATE password_reset_requests SET status = 'Processed', processed_by = ?, processed_at = NOW() WHERE nrp = ? AND status = 'Pending'");
    if ($markDone) {
        $markDone->bind_param('ss', $me['nrp'], $nrp);
        $markDone->execute();
        $markDone->close();
    }
} catch (\Throwable $t) {
    error_log('[ubah_password_user] password_reset_requests update failed: ' . $t->getMessage());
}

// Notify the target user so they know their password has been changed and
// receive the new one. Best-effort: a send failure never fails the update.
$targetEmail = trim((string)($target['email'] ?? ''));
if ($targetEmail !== '' && filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
    try {
        $mailer = MailService::instance();
        $mailer->sendPasswordChanged($targetEmail, [
            'nrp'          => $target['nrp'],
            'username'     => $target['username'] ?? '',
            'nama_lengkap' => $target['nama_lengkap'] ?? '',
            'email'        => $targetEmail,
        ], $passwordBaru);
    } catch (\Throwable $t) {
        error_log('[ubah_password_user] password-change notification failed: ' . $t->getMessage());
    }
} else {
    error_log('[ubah_password_user] target user has no valid e-mail; skipped password-change notification for nrp=' . $nrp);
}

json_response(['status' => 'success', 'message' => 'Password berhasil diubah.']);
