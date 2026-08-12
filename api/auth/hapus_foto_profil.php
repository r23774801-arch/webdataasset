<?php
/**
 * hapus_foto_profil.php — remove the logged-in user's profile photo.
 *
 * Rules (server-side, authoritative):
 *  - Requires an authenticated session (any role, including Admin).
 *  - Clears users.photo for the logged-in user (keyed by users.nrp).
 *  - Deletes the stored photo file so storage does not accumulate.
 */
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../app/bootstrap.php';

require_login();
$user = current_user();

// Defensive: the users.photo column must exist (added by migrate_db.php).
$checkPhoto = $conn->query("SHOW COLUMNS FROM users LIKE 'photo'");
if (!$checkPhoto || $checkPhoto->num_rows === 0) {
    json_response(['status' => 'error', 'message' => 'Kolom foto profil belum tersedia. Jalankan migrate_db.php terlebih dahulu.']);
}

$nrp = (string)$user['nrp'];

// Fetch the current photo (to delete the file after the DB update succeeds).
$oldPhoto = '';
$stmt = $conn->prepare("SELECT photo FROM users WHERE nrp = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('s', $nrp);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $oldPhoto = (string)($row['photo'] ?? '');
    }
    $stmt->close();
}

$update = $conn->prepare("UPDATE users SET photo = NULL WHERE nrp = ?");
if (!$update) {
    json_response(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.']);
}
$update->bind_param('s', $nrp);
if (!$update->execute()) {
    error_log('[hapus_foto_profil] update failed: ' . $conn->error);
    json_response(['status' => 'error', 'message' => 'Gagal menghapus foto profil.']);
}

// Remove the stored profile photo file (never allow a path outside the folder).
if ($oldPhoto !== '') {
    $rootDir = realpath(__DIR__ . '/../../');
    $oldPath = ($rootDir !== false ? $rootDir : __DIR__ . '/../../') . '/' . $oldPhoto;
    if (is_file($oldPath) && strpos($oldPhoto, 'uploads/profil/') === 0) {
        @unlink($oldPath);
    }
}

AuditService::log($conn, 'Removed Profile Photo', 'users', null, ['nrp' => $nrp]);

json_response(['status' => 'success', 'message' => 'Foto profil berhasil dihapus.']);