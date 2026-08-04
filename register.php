<?php
// Mengatur header agar bisa mengembalikan respon dalam format JSON
header("Content-Type: application/json");

// Memanggil file koneksi
require 'koneksi.php';

// Menangkap data JSON yang dikirim oleh JavaScript (Fetch API)
$data = json_decode(file_get_contents("php://input"), true);

// Jika tidak ada data yang masuk
if (!$data) {
    echo json_encode(["status" => "error", "message" => "Data tidak valid!"]);
    exit;
}

// Membersihkan input agar terhindar dari SQL Injection
// ... (kode atas sama)
$nrp = $conn->real_escape_string($data['nrp']);
$username = $conn->real_escape_string($data['username']);
$role = strtolower(trim($data['role'] ?? ''));

// HANYA IT dan GA yang boleh didaftarkan. Role admin tidak pernah diizinkan lewat registrasi.
$allowedRoles = ['it', 'ga'];
if (!in_array($role, $allowedRoles, true)) {
    echo json_encode(["status" => "error", "message" => "Role tidak diizinkan! Hanya IT dan GA yang dapat mendaftar."]);
    exit;
}

// ENKRIPSI PASSWORD DI SINI
$password = password_hash($data['password'], PASSWORD_DEFAULT); 



// 1. Cek apakah NRP sudah terdaftar di database
$cek_nrp = $conn->query("SELECT id FROM users WHERE nrp = '$nrp'");

if ($cek_nrp->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "NRP sudah terdaftar di sistem!"]);
} else {
    // 2. Jika NRP belum ada, masukkan data ke database
    $query = "INSERT INTO users (nrp, username, password, role) VALUES ('$nrp', '$username', '$password', '$role')";
    
    if ($conn->query($query) === TRUE) {
        echo json_encode(["status" => "success", "message" => "Registrasi Berhasil! Silakan login."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan data: " . $conn->error]);
    }
}

$conn->close();
?>