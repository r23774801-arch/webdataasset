<?php
// Mengatur header agar bisa mengembalikan respon dalam format JSON
header("Content-Type: application/json");
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Memanggil file koneksi
require 'koneksi.php';

// Menangkap data JSON yang dikirim oleh JavaScript (Fetch API)
$data = json_decode(file_get_contents("php://input"), true);

// Jika tidak ada data yang masuk
if (!$data) {
    echo json_encode(["status" => "error", "message" => "Data tidak valid!"]);
    exit;
}

// ---- Rate limiting: max 5 registrations per IP per 5 minutes ----
// Stored per-IP in a temp lock file (cookie-independent) to prevent sign-up
// spam / account flooding. Same pattern as login.php.
$now       = time();
$ipHash    = md5((string)($_SERVER['REMOTE_ADDR'] ?? 'cli'));
$throttleDir = rtrim(sys_get_temp_dir(), '/\\') . '/webdataaset_register';
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
        if ($attempts >= 5) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
            echo json_encode(["status" => "error", "message" => "Terlalu banyak percobaan pendaftaran. Silakan coba lagi dalam beberapa menit."]);
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

// Membersihkan input agar terhindar dari SQL Injection
$nrp = $conn->real_escape_string($data['nrp']);

// Username diketik bebas oleh pengguna (bukan lagi otomatis = NRP). Wajib
// diisi dan dibatasi panjangnya agar muat di kolom VARCHAR(100).
$username = trim((string)($data['username'] ?? ''));
if ($username === '') {
    echo json_encode(["status" => "error", "message" => "Username wajib diisi!"]);
    exit;
}
if (strlen($username) > 100) {
    echo json_encode(["status" => "error", "message" => "Username terlalu panjang (maksimal 100 karakter)!"]);
    exit;
}
$username = $conn->real_escape_string($username);

// Role umum non-admin: semua user baru mendapat role 'user' (dapat mengelola
// aset IT dan GA). Role admin tidak pernah diizinkan lewat registrasi.
$role = 'user';

// Departemen sudah tidak dipakai pada alur registrasi — field dihilangkan dari
// form Daftar Akun (login.html). Nama lengkap diisi otomatis dari direktori
// karyawan saat NRP dipilih; username diketik bebas oleh pengguna.
$nama_lengkap = trim((string)($data['nama_lengkap'] ?? ''));
if ($nama_lengkap === '') {
    echo json_encode(["status" => "error", "message" => "Nama lengkap wajib diisi!"]);
    exit;
}
if (strlen($nama_lengkap) > 100) {
    echo json_encode(["status" => "error", "message" => "Nama lengkap terlalu panjang (maksimal 100 karakter)!"]);
    exit;
}
$nama_lengkap = $conn->real_escape_string($nama_lengkap);

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

// Password policy: min 8, huruf besar, huruf kecil, angka, dan simbol.
// Diterapkan di server (authoritative), sama dengan validasi client.
$password = (string)($data['password'] ?? '');
if (strlen($password) < 8) {
    echo json_encode(["status" => "error", "message" => "Password minimal 8 karakter!"]);
    exit;
}
if (!preg_match('/[A-Z]/', $password)) {
    echo json_encode(["status" => "error", "message" => "Password harus mengandung huruf besar (A-Z)!"]);
    exit;
}
if (!preg_match('/[a-z]/', $password)) {
    echo json_encode(["status" => "error", "message" => "Password harus mengandung huruf kecil (a-z)!"]);
    exit;
}
if (!preg_match('/\d/', $password)) {
    echo json_encode(["status" => "error", "message" => "Password harus mengandung angka (0-9)!"]);
    exit;
}
if (!preg_match('/[^A-Za-z0-9]/', $password)) {
    echo json_encode(["status" => "error", "message" => "Password harus mengandung simbol (!@#$% dll)!"]);
    exit;
}

// ENKRIPSI PASSWORD DI SINI
$password = password_hash($password, PASSWORD_DEFAULT); 

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
        // 1c. Cek apakah NRP masih mengantre persetujuan (Pending/Approved belum dipindah).
        $cek_pending = $conn->query("SELECT id, status FROM user_approvals WHERE nrp = '$nrp' AND status IN ('Pending','Approved')");
        if ($cek_pending && $cek_pending->num_rows > 0) {
            $pendingRow = $cek_pending->fetch_assoc();
            if ($pendingRow['status'] === 'Approved') {
                echo json_encode(["status" => "error", "message" => "NRP sudah terdaftar di sistem!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Permintaan pendaftaran dengan NRP ini masih menunggu persetujuan admin."]);
            }
            exit;
        }

        // 2. Antrekan registrasi ke tabel user_approvals (status Pending) —
        //    akun baru hanya aktif setelah ADMIN menyetujuinya.
        $checkApprovalsTable = $conn->query("SHOW TABLES LIKE 'user_approvals'");
        $hasApprovalsTable = $checkApprovalsTable && $checkApprovalsTable->num_rows > 0;

        if (!$hasApprovalsTable) {
            echo json_encode(["status" => "error", "message" => "Sistem persetujuan akun belum tersedia. Jalankan migrate_db.php terlebih dahulu."]);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO user_approvals (nrp, username, nama_lengkap, email, password, status, requested_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
        if (!$stmt) {
            error_log('[register] prepare failed: ' . $conn->error);
            echo json_encode(["status" => "error", "message" => "Gagal menyimpan data. Silakan coba lagi."]);
            exit;
        }
        $stmt->bind_param('sssss', $nrp, $username, $nama_lengkap, $email, $password);

        if ($stmt->execute()) {
            // Beri tahu admin agar segera memproses permintaan pendaftaran.
            // Kegagalan kirim email TIDAK mengubah hasil registrasi.
            try {
                require_once __DIR__ . '/app/bootstrap.php';
                MailService::notifyAdminsUserRegistration($conn, [
                    'nrp'         => $nrp,
                    'username'    => $username,
                    'nama_lengkap'=> $nama_lengkap,
                    'email'       => $email,
                ]);
            } catch (\Throwable $t) {
                error_log('[register] admin notification failed: ' . $t->getMessage());
            }
            echo json_encode(["status" => "success", "message" => "Permintaan pendaftaran dikirim. Silakan tunggu persetujuan admin sebelum dapat login."]);
        } else {
            error_log('[register] insert failed: ' . $stmt->error);
            echo json_encode(["status" => "error", "message" => "Gagal menyimpan data. Silakan coba lagi."]);
        }
        $stmt->close();
    }
}

$conn->close();
?>