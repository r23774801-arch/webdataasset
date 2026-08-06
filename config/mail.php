<?php
/**
 * Centralized Mail Configuration
 * =============================================
 * Single source of truth for all SMTP / e-mail settings.
 * Sensitive credentials are read from the environment (.env file or
 * server environment variables) — never hardcoded in source code.
 *
 * Copy `.env.example` to `.env` and fill in the real values.
 */

// --- Minimal .env loader (no external dependency) -------------------------
// Guarded so config/spreadsheet.php (same loader) can never redeclare it.
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
 * Returns the mail configuration array. Cached so it is loaded only once.
 */
function mail_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = [
            // SMTP Server
            'smtp_host'       => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
            'smtp_port'       => (int)(getenv('SMTP_PORT') ?: 587),
            'smtp_username'   => getenv('SMTP_USERNAME') ?: '',
            'smtp_password'   => getenv('SMTP_PASSWORD') ?: '',

            // Encryption: 'tls' (STARTTLS, port 587) or 'ssl' (port 465)
            'smtp_encryption' => strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls'),

            // Sender identity
            'sender_name'     => getenv('MAIL_FROM_NAME') ?: 'UT Asset Management System',
            'sender_email'    => getenv('MAIL_FROM_ADDRESS') ?: '',

            // Base URL of the application (used to build absolute links in e-mails)
            'app_url'         => rtrim(getenv('APP_URL') ?: '', '/'),
        ];
    }
    return $config;
}
