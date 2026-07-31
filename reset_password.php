<?php
header("Content-Type: application/json");
require 'koneksi.php';

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["status" => "error", "message" => "Data tidak valid!"]);
    exit;
}

$nrp = $conn->real_escape_string($data['nrp']);
$username = $conn->real_escape_string($data['username']);
$password_baru = password_hash($data['password_baru'], PASSWORD_DEFAULT);

// 1. Verifikasi ketat: Pastikan NRP dan Username adalah milik orang yang sama
$query_cek = "SELECT id FROM users WHERE nrp = '$nrp' AND username = '$username'";
$result = $conn->query($query_cek);

if ($result->num_rows > 0) {
    // 2. Jika cocok, update password dengan enkripsi yang baru
    $query_update = "UPDATE users SET password = '$password_baru' WHERE nrp = '$nrp'";
    
    if ($conn->query($query_update) === TRUE) {
        echo json_encode(["status" => "success", "message" => "Password berhasil di-reset! Silakan login dengan password baru Anda."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal memperbarui database."]);
    }
} else {
    // Jika ada orang iseng mencoba menebak NRP/Username orang lain
    echo json_encode(["status" => "error", "message" => "Data NRP dan Username tidak cocok! Reset dibatalkan."]);
}

$conn->close();
?>