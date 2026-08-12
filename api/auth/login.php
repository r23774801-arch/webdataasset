<?php
header("Content-Type: application/json");
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../app/helpers.php';

require_valid_origin();

// Start session for RBAC with hardened cookies (HttpOnly + SameSite=Lax,
// Secure when served over HTTPS). Must run before session_start().
$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $https,
]);
session_start();

// Menangkap data JSON dari JavaScript
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['nrp']) || !isset($data['password'])) {
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap!"]);
    exit;
}

$nrp = trim((string)$data['nrp']);
$password = $data['password']; 

// ---- Rate limiting: max 10 attempts per IP per 5 minutes ----
// Stored per-IP in a temp lock file (cookie-independent). Throttling happens
// BEFORE the expensive DB lookup to also protect against NRP enumeration.
$now       = time();
$ipHash    = md5((string)($_SERVER['REMOTE_ADDR'] ?? 'cli'));
$throttleDir = rtrim(sys_get_temp_dir(), '/\\') . '/webdataaset_login';
if (!is_dir($throttleDir)) {
    @mkdir($throttleDir, 0777, true);
}
$lockFile = $throttleDir . '/' . $ipHash . '.lock';
// Read BEFORE locking: on Windows file_get_contents() on a file held with
// LOCK_EX returns false, which would always read as "no attempts".
// Format: "timestamp|count" — count of attempts since the window started.
$lockData = (string)@file_get_contents($lockFile);
$parts    = explode('|', $lockData);
$windowStart = (int)trim($parts[0] ?? '0');
$attempts    = (int)trim($parts[1] ?? '0');
$lockFp  = @fopen($lockFile, 'c+');
if ($lockFp) {
    if (flock($lockFp, LOCK_EX)) {
        if (($now - $windowStart) >= 300) {
            // Window expired — start a fresh one.
            $windowStart = $now;
            $attempts    = 0;
        }
        if ($attempts >= 10) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
            echo json_encode(["status" => "error", "message" => "Terlalu banyak percobaan login. Silakan coba lagi dalam beberapa menit."]);
            exit;
        }
        $attempts++;
        ftruncate($lockFp, 0);
        rewind($lockFp);
        fwrite($lockFp, $windowStart . '|' . $attempts);
        fflush($lockFp);
        flock($lockFp, LOCK_UN);
    }
    fclose($lockFp);
}

// 1. Cari user berdasarkan NRP
$stmt = $conn->prepare("SELECT * FROM users WHERE nrp = ? LIMIT 1");
if (!$stmt) {
    error_log('[login] prepare failed: ' . $conn->error);
    echo json_encode(["status" => "error", "message" => "Terjadi kesalahan pada server."]);
    exit;
}
$stmt->bind_param('s', $nrp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Generic message — never reveals whether the NRP exists (anti-enumeration).
    echo json_encode(["status" => "error", "message" => "NRP atau password salah."]);
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// 2. Verifikasi Password
if (!password_verify($password, $user['password'])) {
    echo json_encode(["status" => "error", "message" => "NRP atau password salah."]);
    exit;
}

// 2b. Blokir akun yang dinonaktifkan oleh Admin (kolom status bersifat opsional/pre-migrasi)
$checkStatus = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
if ($checkStatus && $checkStatus->num_rows > 0 && strtolower(trim((string)($user['status'] ?? 'Aktif'))) === 'nonaktif') {
    echo json_encode(["status" => "error", "message" => "Akun Anda dinonaktifkan. Silakan hubungi Admin."]);
    exit;
}

// 3. Regenerate the session ID after a successful login (fixes session fixation)
//    and set the session variables used by RBAC.
session_regenerate_id(true);
$_SESSION['nrp'] = $user['nrp'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];

// 4. Kirim respon sukses beserta role asli yang diambil otomatis dari database
echo json_encode([
    "status" => "success",
    "message" => "Login Berhasil!",
    "data" => [
        "nrp" => $user['nrp'],
        "username" => $user['username'],
        "role" => $user['role']
    ]
]);

$conn->close();
?>
