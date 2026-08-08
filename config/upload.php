<?php
/**
 * Centralized Upload Configuration
 * =============================================
 * Single source of truth for photo-upload limits used by
 * upload_photo.php (Phase 4.9 — data-entry forms).
 *
 * The maximum size can be overridden via the environment
 * (e.g. UPLOAD_MAX_SIZE=5242880 in .env); the default is 5 MB.
 * Note: the configured value must not exceed the PHP limits
 * upload_max_filesize / post_max_size in php.ini.
 */

// --- Minimal .env loader (no external dependency) -------------------------
// Guarded so config/mail.php / config/spreadsheet.php can never redeclare it.
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
ut_load_env(__DIR__ . '/../.env');

/**
 * Returns the upload configuration array. Cached so it is loaded once.
 */
function upload_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = [
            // Maximum allowed file size in bytes (default: 5 MB).
            'max_size' => (int)(getenv('UPLOAD_MAX_SIZE') ?: (5 * 1024 * 1024)),

            // Allowed attachment extensions (mime type is validated separately).
            // Images (JPG/JPEG/PNG/WEBP) plus PDF documents.
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        ];
    }
    return $config;
}
