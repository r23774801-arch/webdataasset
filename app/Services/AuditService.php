<?php
/**
 * AuditService — central audit logging.
 * Stores: user, action, table, record id, timestamp (+ optional details).
 * Never throws: failures are logged only, so auditing can never break a request.
 */
class AuditService
{
    /**
     * Write one audit entry. Best-effort — never throws.
     *
     * @param mysqli $conn      active connection
     * @param string $action    e.g. 'Created Asset', 'Approved Stocktaking'
     * @param string $tableName target table name
     * @param int|null $recordId affected record id (when known)
     * @param array  $details   optional extra context
     */
    public static function log(mysqli $conn, string $action, string $tableName = '', ?int $recordId = null, array $details = []): void
    {
        try {
            // Defensive: the audit_logs table may not exist yet (pre-migration).
            static $tableChecked = null;
            if ($tableChecked === null) {
                $res = $conn->query("SHOW TABLES LIKE 'audit_logs'");
                $tableChecked = ($res !== false && $res->num_rows > 0);
            }
            if (!$tableChecked) {
                return;
            }

            $nrp      = $_SESSION['nrp'] ?? null;
            $name     = $_SESSION['username'] ?? null;
            $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $stmt = $conn->prepare(
                "INSERT INTO audit_logs (user_nrp, user_name, action, table_name, record_id, details)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            if (!$stmt) {
                return;
            }
            $stmt->bind_param('ssssis', $nrp, $name, $action, $tableName, $recordId, $detailsJson);
            if (!$stmt->execute()) {
                return;
            }

            // Mirror the audit entry to the Audit_Log worksheet (best-effort).
            // MySQL remains the source of truth; a sync failure is logged only.
            if (class_exists('SpreadsheetService') && SpreadsheetService::enabled()) {
                SpreadsheetService::sync(SpreadsheetService::SHEET_AUDIT_LOG, [
                    'user_nrp'   => $nrp,
                    'user_name'  => $name,
                    'action'     => $action,
                    'table_name' => $tableName,
                    'record_id'  => $recordId,
                    'details'    => $detailsJson,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[AuditService] failed to write audit log: ' . $e->getMessage());
        }
    }
}
