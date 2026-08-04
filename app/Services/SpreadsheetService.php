<?php
/**
 * SpreadsheetService — best-effort one-way synchronization of MySQL records
 * to a Google Spreadsheet via a Google Apps Script Web App endpoint.
 *
 * MySQL remains the PRIMARY data source. This service only mirrors data
 * for backup / reporting / analytics. It NEVER blocks or fails the caller:
 * every failure is logged via error_log() and the request continues normally.
 */
class SpreadsheetService
{
    // Worksheet name constants — the sheet names used inside the spreadsheet.
    public const SHEET_ASSET_IT         = 'Asset_IT';
    public const SHEET_ASSET_GA         = 'Asset_GA';
    public const SHEET_BARANG_MASUK_IT  = 'Barang_Masuk_IT';
    public const SHEET_BARANG_MASUK_GA  = 'Barang_Masuk_GA';
    public const SHEET_BARANG_KELUAR_IT = 'Barang_Keluar_IT';
    public const SHEET_BARANG_KELUAR_GA = 'Barang_Keluar_GA';
    public const SHEET_APPROVAL         = 'Approval';
    public const SHEET_AUDIT_LOG        = 'Audit_Log';
    public const SHEET_TRANSFER_HISTORY = 'Transfer_History';

    /** @var array|null cached config */
    private static $config = null;

    /**
     * Loaded once per request; never throws (falls back to safe defaults).
     */
    private static function config(): array
    {
        if (self::$config === null) {
            require_once __DIR__ . '/../../config/spreadsheet.php';
            self::$config = spreadsheet_config();
        }
        return self::$config;
    }

    /**
     * Whether synchronization is configured (Web App URL present).
     */
    public static function enabled(): bool
    {
        return self::config()['web_app_url'] !== '';
    }

    /**
     * Push one row to a worksheet. Best-effort: returns true only when the
     * remote Web App acknowledged the row. Failures are logged, never thrown.
     *
     * @param string $worksheet one of the SHEET_* constants
     * @param array  $row       associative array of column => value
     */
    public static function sync(string $worksheet, array $row): bool
    {
        try {
            $cfg = self::config();
            if ($cfg['web_app_url'] === '') {
                return false; // not configured — silent no-op
            }

            $payload = [
                'worksheet' => $worksheet,
                'row'       => $row,
                'token'     => $cfg['token'],
            ];

            $ok = self::httpPost($cfg['web_app_url'], $payload, (int)$cfg['timeout']);
            if (!$ok) {
                error_log('[SpreadsheetService] Sync to worksheet "' . $worksheet . '" failed.');
            }
            return $ok;
        } catch (\Throwable $e) {
            // A synchronization failure must never break the application.
            error_log('[SpreadsheetService] Sync error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Single HTTP POST helper (reused by every sync call).
     * Returns true on HTTP 200 with a JSON body whose status === 'success'.
     */
    private static function httpPost(string $url, array $payload, int $timeout): bool
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $curl = function_exists('curl_init');
        $ctx = null;

        if (!$curl) {
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n",
                    'content' => $json,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
            ]);
            $response = @file_get_contents($url, false, $ctx);
        } else {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = curl_exec($ch);
            $err = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false || $err !== '') {
                error_log('[SpreadsheetService] HTTP error: ' . $err);
                return false;
            }
            if ($httpCode !== 200) {
                error_log('[SpreadsheetService] Unexpected HTTP ' . $httpCode . ' from Web App.');
                return false;
            }
        }

        if ($response === false) {
            error_log('[SpreadsheetService] Unable to reach Web App: ' . $url);
            return false;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) && ($decoded['status'] ?? '') === 'success';
    }
}
