<?php
/**
 * Password Reset Request (public endpoint — no login required).
 *
 * Triggered from the Login page ("Lupa password? Hubungi Admin"). The user
 * submits their NRP + Username. When the combination matches a real account,
 * every administrator is e-mailed so they can look the user up in the Data
 * Akun page and change the password.
 *
 * Security notes:
 *  - Never reveals whether the account exists (no user enumeration): the
 *    response is always the same success message.
 *  - Rate-limited per IP (5 minutes) to prevent e-mail spam / abuse.
 *  - E-mail delivery is best-effort: a failure is logged, never returned.
 */
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../app/bootstrap.php';

$input    = read_json_input();
$nrp      = trim((string)($input['nrp'] ?? ''));
$username = trim((string)($input['username'] ?? ''));

if ($nrp === '' || $username === '') {
    json_response(['status' => 'error', 'message' => 'NRP dan Username wajib diisi.']);
}

// ---- Rate limiting: max 1 request per IP per 5 minutes ----
// Stored per-IP in a temp lock file (cookie-independent, so it also protects
// against clients that discard the session cookie).
$now       = time();
$ipHash    = md5((string)($_SERVER['REMOTE_ADDR'] ?? 'cli'));
$throttleDir = rtrim(sys_get_temp_dir(), '/\\') . '/webdataaset_prr';
if (!is_dir($throttleDir)) {
    @mkdir($throttleDir, 0777, true);
}
$lockFile = $throttleDir . '/' . $ipHash . '.lock';
// Read BEFORE locking: on Windows file_get_contents() on a file held with
// LOCK_EX returns false, which would always read as "no last request".
$last    = (int)trim((string)@file_get_contents($lockFile));
$lockFp  = @fopen($lockFile, 'c+');
if ($lockFp) {
    if (flock($lockFp, LOCK_EX)) {
        if ($last > 0 && ($now - $last) < 300) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
            json_response(['status' => 'error', 'message' => 'Permintaan terlalu cepat. Silakan coba lagi dalam beberapa menit.']);
        }
        ftruncate($lockFp, 0);
        rewind($lockFp);
        fwrite($lockFp, (string)$now);
        fflush($lockFp);
        flock($lockFp, LOCK_UN);
    }
    fclose($lockFp);
}

// ---- Look up the user by NRP + Username (no enumeration on failure) ----
$user = null;
$stmt = $conn->prepare("SELECT nrp, username, email, role FROM users WHERE nrp = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('s', $nrp);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (strcasecmp(trim((string)$row['username']), $username) === 0) {
            $user = $row;
        }
    }
    $stmt->close();
}

if ($user) {
    $admins  = MailService::adminEmails($conn);
    $mailer  = MailService::instance();
    $sent    = 0;
    foreach ($admins as $adminEmail) {
        if ($mailer->sendPasswordResetRequest($adminEmail, $user)) {
            $sent++;
        }
    }

    // Persist the request so the admin dashboard can show a pending badge
    // (password_reset_requests). Best-effort: the notification from the Login
    // page must never fail just because the tracking insert did.
    try {
        $ins = $conn->prepare("INSERT INTO password_reset_requests (nrp, username, email, status, requested_at) VALUES (?, ?, ?, 'Pending', NOW())");
        if ($ins) {
            $ins->bind_param('sss', $user['nrp'], $user['username'], $user['email']);
            $ins->execute();
            $ins->close();
        }
    } catch (\Throwable $t) {
        error_log('[request_password_reset] tracking insert failed: ' . $t->getMessage());
    }

    AuditService::log($conn, 'Password Reset Request', 'users', null, [
        'nrp'         => $user['nrp'],
        'username'    => $user['username'],
        'emails_sent' => $sent,
        'admins'      => count($admins),
    ]);

    if ($sent === 0) {
        error_log('[request_password_reset] No admin e-mail delivered for nrp=' . $nrp . ' (admins=' . count($admins) . ').');
    }
} else {
    // Account not found — silently ignore. The visible response is identical
    // to a successful request so the endpoint cannot be used to enumerate users.
    AuditService::log($conn, 'Password Reset Request', 'users', null, [
        'matched' => false,
        'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
}

json_response(['status' => 'success', 'message' => 'Permintaan telah dikirim. Admin akan memproses reset password Anda.']);
