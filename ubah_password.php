<?php
header("Content-Type: application/json");
require 'koneksi.php';

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) exit;

$nrp_session = $conn->real_escape_string($data['nrp_session']);
$verifikasi = $conn->real_escape_string($data['verifikasi']);
$password_baru_hash = password_hash($data['password_baru'], PASSWORD_DEFAULT);

// Ambil data user saat ini berdasarkan sesi login
$query = "SELECT * FROM users WHERE nrp = '$nrp_session'";
$result = $conn->query($query);
$user = $result->fetch_assoc();

// Cek apakah input verifikasi cocok dengan salah satu kriteria
$isVerified = false;

// 1. Apakah cocok dengan password lama?
if (password_verify($verifikasi, $user['password'])) {
    $isVerified = true;
} 
// 2. ATAU apakah cocok dengan Username?
else if (strtolower($verifikasi) === strtolower($user['username'])) {
    $isVerified = true;
} 
// 3. ATAU apakah cocok dengan NRP?
else if ($verifikasi === $user['nrp']) {
    $isVerified = true;
}

if ($isVerified) {
    // Update password di database
    $update_query = "UPDATE users SET password = '$password_baru_hash' WHERE nrp = '$nrp_session'";
    if ($conn->query($update_query) === TRUE) {
        echo json_encode(["status" => "success", "message" => "Password berhasil diubah!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal mengubah password."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data verifikasi salah!"]);
}

$conn->close();
?>