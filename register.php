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
$nrp = $conn->real_escape_string($data['nrp']);
$username = $conn->real_escape_string($data['username']);
$role = strtolower(trim($data['role'] ?? ''));

// HANYA IT dan GA yang boleh didaftarkan. Role admin tidak pernah diizinkan lewat registrasi.
$allowedRoles = ['it', 'ga'];
if (!in_array($role, $allowedRoles, true)) {
    echo json_encode(["status" => "error", "message" => "Role tidak diizinkan! Hanya IT dan GA yang dapat mendaftar."]);
    exit;
}

// Email wajib diisi, format harus valid, dan tidak boleh sama dengan akun lain (case-insensitive).
$email = trim((string)($data['email'] ?? ''));
if ($email === '') {
    echo json_encode(["status" => "error", "message" => "Email wajib diisi!"]);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Format email tidak valid!"]);
    exit;
}
if (strlen($email) > 100) {
    echo json_encode(["status" => "error", "message" => "Email terlalu panjang (maksimal 100 karakter)!"]);
    exit;
}

// Defensive: the users.email column may not exist yet (pre-migration).
$check_email_col = $conn->query("SHOW COLUMNS FROM users LIKE 'email'");
if (!$check_email_col || $check_email_col->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Fitur email belum tersedia. Jalankan migrate_db.php terlebih dahulu."]);
    exit;
}

$email = $conn->real_escape_string($email);

// ENKRIPSI PASSWORD DI SINI
$password = password_hash($data['password'], PASSWORD_DEFAULT); 

// 1. Cek apakah NRP sudah terdaftar di database
$cek_nrp = $conn->query("SELECT id FROM users WHERE nrp = '$nrp'");

if ($cek_nrp->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "NRP sudah terdaftar di sistem!"]);
} else {
    // ==========================================
    // PHASE 4.22 — Master employee validation:
    // the NRP must belong to a known employee
    // from the master_employee directory.
    // Backward compatible: skipped when the
    // table has not been migrated yet.
    // ==========================================
    $checkMasterTable = $conn->query("SHOW TABLES LIKE 'master_employee'");
    $hasMasterTable = $checkMasterTable && $checkMasterTable->num_rows > 0;

    if ($hasMasterTable) {
        $rawNrp = trim((string)$data['nrp']);
        $cekMaster = $conn->prepare("SELECT id FROM master_employee WHERE nrp = ? LIMIT 1");
        if ($cekMaster) {
            $cekMaster->bind_param('s', $rawNrp);
            $cekMaster->execute();
            $cekMaster->store_result();
            if ($cekMaster->num_rows === 0) {
                echo json_encode(["status" => "error", "message" => "NRP tidak terdaftar sebagai karyawan. Silakan pilih karyawan dari direktori."]);
                exit;
            }
            $cekMaster->close();
        }
    }

    // 1b. Cek apakah email sudah dipakai akun lain (LOWER() = case-insensitive)
    $cek_email = $conn->query("SELECT id FROM users WHERE LOWER(email) = LOWER('$email')");
    if ($cek_email && $cek_email->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Email sudah terdaftar di sistem!"]);
    } else {
        // 2. Jika NRP dan email belum ada, masukkan data ke database
        $query = "INSERT INTO users (nrp, username, password, role, email) VALUES ('$nrp', '$username', '$password', '$role', '$email')";
        
        if ($conn->query($query) === TRUE) {
            echo json_encode(["status" => "success", "message" => "Registrasi Berhasil! Silakan login."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal menyimpan data: " . $conn->error]);
        }
    }
}

$conn->close();
?>