<?php
header("Content-Type: application/json");
require 'koneksi.php';

// Start session for RBAC
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

// 3. Jika lolos, set session untuk RBAC
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
