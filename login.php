<?php
header("Content-Type: application/json");
require 'koneksi.php';

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

$nrp = $conn->real_escape_string($data['nrp']);
$password = $data['password']; 

// 1. Cari user berdasarkan NRP
$query = "SELECT * FROM users WHERE nrp = '$nrp'";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "NRP tidak ditemukan! Silakan daftar terlebih dahulu."]);
    exit;
}

$user = $result->fetch_assoc();

// 2. Verifikasi Password
if (!password_verify($password, $user['password'])) {
    echo json_encode(["status" => "error", "message" => "Password salah!"]);
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
