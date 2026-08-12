<?php
/**
 * update_foto_profil.php — upload/replace the logged-in user's profile photo.
 *
 * Rules (server-side, authoritative):
 *  - Requires an authenticated session (any role, including Admin).
 *  - Accepts multipart field "photo" (image only: JPG/JPEG/PNG/WEBP).
 *  - Stores the file under uploads/profil/ and saves the relative path in
 *    users.photo for the logged-in user (keyed by users.nrp).
 *  - Deletes the previous photo file so storage does not accumulate.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['photo'])) {
    json_response(['status' => 'error', 'message' => 'Tidak ada file yang diunggah.']);
}

$file = $_FILES['photo'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    json_response(['status' => 'error', 'message' => 'Upload gagal dengan kode: ' . $file['error']]);
}

// Profile photos are images only (no PDF).
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
$mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : ($file['type'] ?? '');
if (!in_array($mime, $allowedMimes, true)) {
    json_response(['status' => 'error', 'message' => 'Tipe file tidak didukung. Hanya JPG, JPEG, PNG, dan WEBP yang diizinkan.']);
}

$mimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
$ext = $mimeToExt[$mime] ?? '';
if ($ext === '') {
    json_response(['status' => 'error', 'message' => 'Tipe file tidak didukung.']);
}

// Configurable maximum size (same limit as other uploads, default 5 MB).
require_once __DIR__ . '/../../config/upload.php';
$cfg = upload_config();
if ($file['size'] > $cfg['max_size']) {
    $maxMb = round($cfg['max_size'] / 1024 / 1024, 1);
    json_response(['status' => 'error', 'message' => "Ukuran file melebihi batas maksimum ({$maxMb} MB)."]);
}

// Dedicated folder for profile photos (absolute path resolved from the
// project root so the stored URL-relative path always matches the file).
$rootDir = realpath(__DIR__ . '/../../');
$uploadDir = ($rootDir !== false ? $rootDir : __DIR__ . '/../../') . '/uploads/profil/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}
if (!is_dir($uploadDir)) {
    json_response(['status' => 'error', 'message' => 'Gagal membuat direktori upload.']);
}

$filename = 'profil_' . (string)$user['nrp'] . '_' . uniqid() . '.' . $ext;
$destination = $uploadDir . $filename;
$webPath = 'uploads/profil/' . $filename; // URL-relative path stored in the DB.

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    json_response(['status' => 'error', 'message' => 'Gagal menyimpan file.']);
}

$nrp = (string)$user['nrp'];

// Fetch the previous photo (to delete the file after the DB update succeeds).
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

$update = $conn->prepare("UPDATE users SET photo = ? WHERE nrp = ?");
if (!$update) {
    json_response(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.']);
}
$update->bind_param('ss', $webPath, $nrp);
if (!$update->execute()) {
    error_log('[update_foto_profil] update failed: ' . $conn->error);
    @unlink($destination);
    json_response(['status' => 'error', 'message' => 'Gagal menyimpan foto profil.']);
}

// Remove the previous profile photo file (never the same path as the new one).
if ($oldPhoto !== '' && $oldPhoto !== $webPath) {
    $oldPath = ($rootDir !== false ? $rootDir : __DIR__ . '/../../') . '/' . $oldPhoto;
    if (is_file($oldPath) && strpos($oldPhoto, 'uploads/profil/') === 0) {
        @unlink($oldPath);
    }
}

AuditService::log($conn, 'Updated Profile Photo', 'users', null, ['nrp' => $nrp, 'photo' => $webPath]);

json_response(['status' => 'success', 'message' => 'Foto profil berhasil diperbarui.', 'data' => ['photo' => $webPath]]);
