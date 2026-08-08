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
     * Phase 4.20 — when $uniqueKey is provided the Web App performs an UPSERT:
     * it searches the worksheet for a row whose key column equals the row's
     * value and updates that row in place. When no key is given (or the value
     * is empty) the behavior stays exactly as before: a new row is appended.
     *
     * @param string      $worksheet one of the SHEET_* constants
     * @param array       $row       associative array of column => value
     * @param string|null $uniqueKey column name used as the unique key, or null
     *                               to keep append-only behavior (backward compatible)
     */
    public static function sync(string $worksheet, array $row, ?string $uniqueKey = null): bool
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
            if ($uniqueKey !== null && $uniqueKey !== '') {
                $payload['key'] = $uniqueKey;
            }

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
     * Re-fetch one Asset row (aset_it / aset_ga) from MySQL and upsert it to
     * its worksheet keyed by asset_number (falls back to serial_number when the
     * asset number is empty). Phase 4.23 — the database primary key ('id') is
     * no longer exported to the spreadsheet. Best-effort — never throws, never
     * blocks the caller. Used by every endpoint that mutates an asset record.
     */
    public static function syncAsset(mysqli $conn, string $table, string $worksheet, int $id): bool
    {
        try {
            if (!self::enabled()) {
                return false;
            }
            $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id = ?");
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) {
                return false;
            }

            $assetNumber  = trim((string)($row['asset_number'] ?? ''));
            $serialNumber = trim((string)($row['serial_number'] ?? ''));
            // Phase 4.23 — the database primary key ('id') is intentionally NOT
            // exported to the spreadsheet.
            $payload = [
                'asset_number'  => $assetNumber,
                'nama_barang'   => (string)($row['nama_barang'] ?? ''),
                'serial_number' => $serialNumber,
            ];
            if ($table === 'aset_ga' && array_key_exists('asset_class', $row)) {
                $payload['asset_class'] = (string)($row['asset_class'] ?? '');
            }
            $payload['pic']               = (string)($row['pic'] ?? '');
            $payload['area']              = (string)($row['area'] ?? '');
            $payload['location_note']     = (string)($row['location_note'] ?? '');
            $payload['utilisasi']         = (string)($row['utilisasi'] ?? '');
            $payload['date_of_entry']     = $row['date_of_entry'] ?? null;
            $payload['attachment']        = (string)($row['attachment'] ?? '');
            $payload['kondisi']           = (string)($row['kondisi'] ?? '-');
            $payload['stocktaking_status'] = (string)($row['stocktaking_status'] ?? 'Pending');
            if (array_key_exists('created_at', $row) && $row['created_at'] !== null) {
                $payload['created_at'] = (string)$row['created_at'];
            }

            // Phase 4.23 — upsert key: asset_number, else serial_number (assets
            // require at least one of them), else append (backward compatible).
            $key = $assetNumber !== '' ? 'asset_number' : ($serialNumber !== '' ? 'serial_number' : null);
            return self::sync($worksheet, $payload, $key);
        } catch (\Throwable $e) {
            error_log('[SpreadsheetService] syncAsset error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Re-fetch one Barang row (barang_{module}_{type}) from MySQL and upsert it
     * to its worksheet keyed by nomor_tiket. Phase 4.23 — the database primary
     * key ('id') is no longer exported to the spreadsheet. Best-effort — never
     * throws, never blocks the caller.
     */
    public static function syncBarang(mysqli $conn, string $module, string $type, int $id): bool
    {
        try {
            $module = strtolower($module);
            $type   = strtolower($type);
            if (!in_array($module, ['masuk', 'keluar'], true) || !in_array($type, ['it', 'ga'], true)) {
                return false;
            }
            $table = "barang_{$module}_{$type}";
            $worksheet = ($module === 'masuk')
                ? ($type === 'it' ? self::SHEET_BARANG_MASUK_IT : self::SHEET_BARANG_MASUK_GA)
                : ($type === 'it' ? self::SHEET_BARANG_KELUAR_IT : self::SHEET_BARANG_KELUAR_GA);

            if (!self::enabled()) {
                return false;
            }
            $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id = ?");
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) {
                return false;
            }

            $nomorTiket = trim((string)($row['nomor_tiket'] ?? ''));
            // Phase 4.23 — the database primary key ('id') is intentionally NOT
            // exported to the spreadsheet.
            $payload = [
                'asset_number' => (string)($row['asset_number'] ?? ''),
                'nomor_tiket'  => $nomorTiket,
                'asset_name'   => (string)($row['asset_name'] ?? ''),
                'jumlah'       => (int)($row['jumlah'] ?? 0),
                'unit'         => (string)($row['unit'] ?? ''),
                'supplier'     => array_key_exists('supplier', $row) ? (string)($row['supplier'] ?? '') : '',
                'tanggal'      => (string)($row['tanggal'] ?? ''),
                'pic'          => (string)($row['pic'] ?? ''),
                'area'         => (string)($row['area'] ?? ''),
                'attachment'   => (string)($row['attachment'] ?? ''),
            ];
            if (array_key_exists('created_at', $row) && $row['created_at'] !== null) {
                $payload['created_at'] = (string)$row['created_at'];
            }

            return self::sync($worksheet, $payload, $nomorTiket !== '' ? 'nomor_tiket' : null);
        } catch (\Throwable $e) {
            error_log('[SpreadsheetService] syncBarang error: ' . $e->getMessage());
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
