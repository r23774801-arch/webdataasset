<?php
header('Content-Type: application/json');

// --- Minimal .env loader (no external dependency) -------------------------
// Guarded so config/*.php can never redeclare it.
if (!function_exists('ut_load_env')) {
    function ut_load_env(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key   = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ($key === '' || getenv($key) !== false || array_key_exists($key, $_ENV)) {
                continue;
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}
ut_load_env(__DIR__ . '/.env');

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db   = getenv('DB_NAME') ?: 'db_ut_assets';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    error_log('[koneksi] database connection failed: ' . $conn->connect_error);
    echo json_encode(["status" => "error", "message" => "Koneksi database gagal."]);
    exit;
}
