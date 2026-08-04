<?php
/**
 * Centralized Google Spreadsheet Synchronization Configuration
 * =============================================================
 * Single source of truth for the Google Apps Script Web App endpoint
 * used by SpreadsheetService.
 *
 * MySQL remains the PRIMARY data source. The spreadsheet is only a
 * synchronized copy for backup / reporting / analytics.
 *
 * When SPREADSHEET_WEB_APP_URL is empty (default) synchronization is
 * disabled and the application works exactly as before.
 *
 * Copy `.env.example` to `.env` and fill in the real values:
 *   SPREADSHEET_WEB_APP_URL=https://script.google.com/macros/s/XXXX/exec
 *   SPREADSHEET_TOKEN=optional-shared-secret
 */

// --- Minimal .env loader (no external dependency) -------------------------
// config/mail.php may already define this loader; guard against redeclare.
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
 * Returns the spreadsheet configuration array. Cached so it is loaded once.
 */
function spreadsheet_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = [
            // Google Apps Script Web App URL (empty => sync disabled)
            'web_app_url' => trim((string)getenv('SPREADSHEET_WEB_APP_URL')),

            // Optional shared secret; the Apps Script validates it.
            'token' => trim((string)getenv('SPREADSHEET_TOKEN')),

            // HTTP timeout (seconds) — keeps sync best-effort and fast.
            'timeout' => (int)(getenv('SPREADSHEET_TIMEOUT') ?: 3),
        ];
    }
    return $config;
}
