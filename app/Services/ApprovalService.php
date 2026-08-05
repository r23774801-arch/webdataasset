<?php
/**
 * ApprovalService — all logic for the stocktaking approval workflow.
 * Responsibilities:
 *  - Create a submission (with asset snapshot + condition summary)
 *  - List / fetch submissions
 *  - Approve or Reject a submission (Reject supported for future use)
 */
class ApprovalService
{
    public const STATUS_PENDING  = 'Pending';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_REJECTED = 'Rejected';

    /**
     * Create a stocktaking submission for one asset table (IT or GA).
     * Returns the stored submission row (with assets) or null on failure.
     */
    public static function createSubmission(mysqli $conn, string $assetType, string $nrp, string $name): ?array
    {
        $assetType = strtoupper($assetType);
        $snapshot  = self::buildSnapshot($conn, $assetType, $nrp);
        if ($snapshot === null) {
            return null;
        }

        $assetsJson = json_encode($snapshot['assets']);

        $stmt = $conn->prepare(
            "INSERT INTO stocktaking_submissions
                (asset_type, submitted_by, submitted_by_name, department, area,
                 total_assets, normal_count, broken_count, lost_count, pending_count,
                 assets_json, status, submission_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '" . self::STATUS_PENDING . "', NOW())"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param(
            'sssssiiiiis',
            $assetType, $nrp, $name, $snapshot['department'], $snapshot['area'],
            $snapshot['total'], $snapshot['normal'], $snapshot['broken'], $snapshot['lost'], $snapshot['pending'],
            $assetsJson
        );
        if (!$stmt->execute()) {
            return null;
        }

        $id = (int)$conn->insert_id;

        // Human-friendly submission code, e.g. STK-20260731-001
        $code = 'STK-' . date('Ymd') . '-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT);
        $conn->query("UPDATE stocktaking_submissions SET submission_code = '" . $conn->real_escape_string($code) . "' WHERE id = " . $id);

        return self::getById($conn, $id);
    }

    /**
     * Build the current asset snapshot + condition summary for one asset table.
     * Returns null when not every asset is Stocktaked yet.
     */
    private static function buildSnapshot(mysqli $conn, string $assetType, string $nrp): ?array
    {
        $table = ($assetType === 'GA') ? 'aset_ga' : 'aset_it';

        // Every asset in the table must already be Stocktaked.
        $check = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN stocktaking_status = 'Stocktaked' THEN 0 ELSE 1 END) AS not_done FROM $table");
        if (!$check) {
            return null;
        }
        $row     = $check->fetch_assoc();
        $total   = (int)$row['total'];
        $notDone = (int)$row['not_done'];
        if ($total === 0 || $notDone > 0) {
            return null;
        }

        // Snapshot the submitted assets and build the condition summary.
        $assets      = [];
        $areas       = [];
        $departments = [];
        $normal = $broken = $lost = 0;

        $sql = "SELECT id, asset_number, nama_barang, serial_number, area, location_note, kondisi, stocktaking_condition, stocktaking_status
                FROM $table
                WHERE stocktaking_status = 'Stocktaked'
                ORDER BY id ASC";
        $result = $conn->query($sql);

        if ($result) {
            while ($a = $result->fetch_assoc()) {
                $condition = trim((string)($a['stocktaking_condition'] ?? ''));
                if ($condition === '') {
                    $condition = trim((string)($a['kondisi'] ?? ''));
                }
                if ($condition === '-') {
                    $condition = '';
                }

                if ($condition === 'Normal') $normal++;
                elseif ($condition === 'Broken') $broken++;
                elseif ($condition === 'Lost') $lost++;

                if (!empty($a['area']))         $areas[$a['area']] = true;
                if (!empty($a['location_note'])) $departments[$a['location_note']] = true;

                $a['type']      = $assetType;
                $a['condition'] = $condition;
                $assets[] = $a;
            }
        }

        $pending   = max(0, $total - $normal - $broken - $lost);
        $area      = $areas ? implode(', ', array_slice(array_keys($areas), 0, 5)) : 'All Areas';
        $department = $departments ? implode(', ', array_slice(array_keys($departments), 0, 3)) : '-';

        // Prefer the user's assigned area/department when available.
        $userStmt = $conn->prepare("SELECT area, department FROM users WHERE nrp = ? LIMIT 1");
        if ($userStmt) {
            $userStmt->bind_param('s', $nrp);
            $userStmt->execute();
            $uResult = $userStmt->get_result();
            if ($u = $uResult->fetch_assoc()) {
                if (!empty($u['area']))       $area = $u['area'];
                if (!empty($u['department'])) $department = $u['department'];
            }
            $userStmt->close();
        }

        return [
            'assets'      => $assets,
            'total'       => $total,
            'normal'      => $normal,
            'broken'      => $broken,
            'lost'        => $lost,
            'pending'     => $pending,
            'area'        => $area,
            'department'  => $department,
        ];
    }

    /**
     * Resubmit a previously rejected submission by reusing the same row:
     * status back to Pending, approval + rejection fields cleared, snapshot refreshed.
     * Returns the updated submission or null on failure. Never duplicates.
     */
    public static function resubmit(mysqli $conn, int $id, string $nrp): ?array
    {
        $existing = self::getById($conn, $id);
        if (!$existing || $existing['status'] !== self::STATUS_REJECTED) {
            return null;
        }

        $assetType = (string)($existing['asset_type'] ?? '');
        $snapshot  = self::buildSnapshot($conn, $assetType, $nrp);
        if ($snapshot === null) {
            return null;
        }

        $assetsJson = json_encode($snapshot['assets']);

        // Guard on the current status so a concurrent admin status change
        // (Phase 3.1 allows changes at any time) can never be overwritten.
        $stmt = $conn->prepare(
            "UPDATE stocktaking_submissions
             SET status = '" . self::STATUS_PENDING . "',
                 approved_by = NULL, approved_by_name = NULL, approval_date = NULL,
                 rejected_by = NULL, rejected_by_name = NULL,
                 rejection_date = NULL, rejection_reason = NULL,
                 department = ?, area = ?,
                 total_assets = ?, normal_count = ?, broken_count = ?, lost_count = ?, pending_count = ?,
                 assets_json = ?, submission_date = NOW()
             WHERE id = ? AND status = '" . self::STATUS_REJECTED . "'"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param(
            'ssiiiiisi',
            $snapshot['department'], $snapshot['area'],
            $snapshot['total'], $snapshot['normal'], $snapshot['broken'], $snapshot['lost'], $snapshot['pending'],
            $assetsJson,
            $id
        );
        if (!$stmt->execute() || $stmt->affected_rows <= 0) {
            return null;
        }
        return self::getById($conn, $id);
    }

    /**
     * Fetch a single submission by id (includes the decoded asset snapshot).
     */
    public static function getById(mysqli $conn, int $id): ?array
    {
        $stmt = $conn->prepare("SELECT * FROM stocktaking_submissions WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return null;
        }
        $row = $result->fetch_assoc();
        $row['assets'] = json_decode((string)($row['assets_json'] ?? '[]'), true) ?: [];
        return $row;
    }

    /**
     * List submissions with optional filters: status, asset_type, submitted_by.
     */
    public static function getSubmissions(mysqli $conn, array $filters = []): array
    {
        $where  = [];
        $params = [];
        $types  = '';

        if (!empty($filters['status'])) {
            $where[]  = 'status = ?';
            $params[] = $filters['status'];
            $types   .= 's';
        }
        if (!empty($filters['asset_type'])) {
            $where[]  = 'asset_type = ?';
            $params[] = $filters['asset_type'];
            $types   .= 's';
        }
        if (!empty($filters['submitted_by'])) {
            $where[]  = 'submitted_by = ?';
            $params[] = $filters['submitted_by'];
            $types   .= 's';
        }

        $sql = "SELECT id, submission_code, asset_type, submitted_by, submitted_by_name,
                       department, area, total_assets, normal_count, broken_count,
                       lost_count, pending_count, status, approved_by, approved_by_name,
                       rejected_by, rejected_by_name, rejection_date, rejection_reason,
                       submission_date, approval_date
                FROM stocktaking_submissions";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Fetch the latest submission of a user for a given asset type.
     */
    public static function getLatestForUser(mysqli $conn, string $nrp, string $assetType): ?array
    {
        $stmt = $conn->prepare(
            "SELECT id, submission_code, asset_type, submitted_by, submitted_by_name,
                    department, area, total_assets, normal_count, broken_count,
                    lost_count, pending_count, status, approved_by, approved_by_name,
                    rejected_by, rejected_by_name, rejection_date, rejection_reason,
                    submission_date, approval_date
             FROM stocktaking_submissions
             WHERE submitted_by = ? AND asset_type = ?
             ORDER BY id DESC
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ss', $nrp, $assetType);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0 ? $result->fetch_assoc() : null;
    }

    /**
     * Whether asset creation is locked for an entire asset type (IT or GA).
     *
     * Phase 4.11 — single source of truth is stocktaking_submissions.status:
     * locked while the LATEST submission for the type (across all users) is
     * Pending or Approved; a Rejected submission or no submission at all
     * leaves creation open.
     */
    public static function isAssetCreationLocked(mysqli $conn, string $assetType): bool
    {
        $assetType = strtoupper($assetType);
        if (!in_array($assetType, ['IT', 'GA'], true)) {
            return false;
        }
        $stmt = $conn->prepare(
            "SELECT status FROM stocktaking_submissions
             WHERE asset_type = ?
             ORDER BY id DESC
             LIMIT 1"
        );
        if (!$stmt) {
            // Fail closed: if the approval table cannot be queried the guard
            // must not silently allow creation. Visible in the error log.
            error_log('[ApprovalService] isAssetCreationLocked: cannot query stocktaking_submissions: ' . $conn->error);
            return true;
        }
        $stmt->bind_param('s', $assetType);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $status = (string)($row['status'] ?? '');
        return in_array($status, [self::STATUS_PENDING, self::STATUS_APPROVED], true);
    }

    /**
     * Update the approval status of an existing submission (never duplicates).
     * Allowed transitions: Pending -> Approved, Pending -> Rejected,
     * Approved -> Rejected, Rejected -> Approved (back to Pending is accepted
     * for future use). Admin-only callers. When moving back to Pending the
     * approval + rejection fields are cleared.
     *
     * When rejecting, a non-empty reason is stored (required by the caller).
     */
    public static function approve(mysqli $conn, int $id, string $status, string $adminNrp, string $adminName, ?string $reason = null): ?array
    {
        if (!in_array($status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            return null;
        }

        if ($status === self::STATUS_PENDING) {
            $stmt = $conn->prepare(
                "UPDATE stocktaking_submissions
                 SET status = ?,
                     approved_by = NULL, approved_by_name = NULL, approval_date = NULL,
                     rejected_by = NULL, rejected_by_name = NULL,
                     rejection_date = NULL, rejection_reason = NULL
                 WHERE id = ?"
            );
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('si', $status, $id);
        } elseif ($status === self::STATUS_APPROVED) {
            $stmt = $conn->prepare(
                "UPDATE stocktaking_submissions
                 SET status = ?, approved_by = ?, approved_by_name = ?, approval_date = NOW(),
                     rejected_by = NULL, rejected_by_name = NULL,
                     rejection_date = NULL, rejection_reason = NULL
                 WHERE id = ?"
            );
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('sssi', $status, $adminNrp, $adminName, $id);
        } else {
            // REJECTED — store who rejected, when, and why.
            $reason = trim((string)$reason);
            $stmt = $conn->prepare(
                "UPDATE stocktaking_submissions
                 SET status = ?, rejected_by = ?, rejected_by_name = ?,
                     rejection_date = NOW(), rejection_reason = ?,
                     approved_by = NULL, approved_by_name = NULL, approval_date = NULL
                 WHERE id = ?"
            );
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('ssssi', $status, $adminNrp, $adminName, $reason, $id);
        }

        if (!$stmt->execute() || $stmt->affected_rows < 0) {
            return null;
        }
        return self::getById($conn, $id);
    }
}
