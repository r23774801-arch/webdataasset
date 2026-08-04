<?php
/**
 * ReportService — shared aggregation queries for the report module.
 *
 * Single source of truth for the per-area asset summary used by the report
 * table (get_laporan_data.php) and the PDF export (export_pdf.php), so the
 * two can never drift apart.
 */
class ReportService
{
    /**
     * Aggregated counts for one asset table, optionally filtered by area.
     * Condition counts use the `kondisi` column (mirrors existing report logic).
     */
    public static function stats(mysqli $conn, string $table, ?string $area = null): array
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN stocktaking_status = 'Stocktaked' THEN 1 ELSE 0 END) AS stocktaked,
                    SUM(CASE WHEN kondisi = 'Normal' THEN 1 ELSE 0 END) AS normal,
                    SUM(CASE WHEN kondisi = 'Broken' THEN 1 ELSE 0 END) AS broken,
                    SUM(CASE WHEN kondisi = 'Lost' THEN 1 ELSE 0 END) AS lost,
                    SUM(CASE WHEN kondisi IS NULL OR kondisi = '-' OR kondisi = '' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN kondisi = 'Transfer' THEN 1 ELSE 0 END) AS transfer
                FROM $table";
        if ($area !== null) {
            $sql .= " WHERE area = '" . $conn->real_escape_string($area) . "'";
        }
        $result = $conn->query($sql);
        $row = $result ? $result->fetch_assoc() : [];
        return [
            'total'      => (int)($row['total'] ?? 0),
            'stocktaked' => (int)($row['stocktaked'] ?? 0),
            'normal'     => (int)($row['normal'] ?? 0),
            'broken'     => (int)($row['broken'] ?? 0),
            'lost'       => (int)($row['lost'] ?? 0),
            'pending'    => (int)($row['pending'] ?? 0),
            'transfer'   => (int)($row['transfer'] ?? 0),
        ];
    }

    /** Sum two stat arrays element-wise. */
    public static function merge(array $a, array $b): array
    {
        foreach ($b as $key => $value) {
            $a[$key] += $value;
        }
        return $a;
    }

    /** Add the real stocktaking progress percentage (Stocktaked / Total). */
    public static function finalize(array $stats): array
    {
        $total = (int)($stats['total'] ?? 0);
        $stats['progress'] = $total > 0
            ? round(((int)($stats['stocktaked'] ?? 0) / $total) * 100, 1)
            : 0;
        return $stats;
    }

    /**
     * Per-area summary across both asset tables (IT + GA combined).
     * Areas are read from the database — never hardcoded.
     */
    public static function areaSummary(mysqli $conn): array
    {
        $areas = [];
        $result = $conn->query(
            "SELECT DISTINCT area FROM aset_it UNION SELECT DISTINCT area FROM aset_ga"
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $area = trim((string)($row['area'] ?? ''));
                if ($area !== '' && !in_array($area, $areas, true)) {
                    $areas[] = $area;
                }
            }
        }

        $summary = [];
        foreach ($areas as $area) {
            $stats = self::merge(
                self::stats($conn, 'aset_it', $area),
                self::stats($conn, 'aset_ga', $area)
            );
            $summary[] = [
                'area'             => $area,
                'total'            => $stats['total'],
                'normal'           => $stats['normal'],
                'broken'           => $stats['broken'],
                'pending'          => $stats['pending'],
                'lost'             => $stats['lost'],
                'transfer'         => $stats['transfer'],
                'stocktaking_done' => $stats['stocktaked'],
            ];
        }
        return $summary;
    }

    // ---------------------------------------------------------------
    // Asset Master Data (combined aset_it + aset_ga listing)
    // Single source of truth for the Asset Master table API and its
    // Excel/PDF exports, so the exported data always matches the UI.
    // ---------------------------------------------------------------

    /**
     * Whitelisted searchable/filterable columns for the combined asset query.
     * Key = external (API/UI) name, value = actual SQL column in the union.
     */
    public static function assetMasterColumns(): array
    {
        return [
            'asset_type'         => 'asset_type',
            'asset_number'       => 'asset_number',
            'nama_barang'        => 'nama_barang',
            'serial_number'      => 'serial_number',
            'asset_class'        => 'asset_class',
            'pic'                => 'pic',
            'area'               => 'area',
            'location_note'      => 'location_note',
            'date_of_entry'      => 'date_of_entry',
            'utilisasi'          => 'utilisasi',
            'kondisi'            => 'kondisi',
            'stocktaking_status' => 'stocktaking_status',
        ];
    }

    /**
     * Build the WHERE clause for the combined asset query.
     * $q = global search across text columns; $filters = whitelisted column filters.
     */
    public static function assetMasterWhere(mysqli $conn, string $q = '', array $filters = []): string
    {
        $where = [];
        $q = trim($q);
        if ($q !== '') {
            $like = '%' . $conn->real_escape_string($q) . '%';
            $where[] = "(asset_number LIKE '$like' OR nama_barang LIKE '$like' OR serial_number LIKE '$like'"
                . " OR asset_class LIKE '$like' OR pic LIKE '$like' OR area LIKE '$like' OR location_note LIKE '$like')";
        }
        $cols = self::assetMasterColumns();
        foreach ($filters as $key => $val) {
            if (!isset($cols[$key]) || trim((string)$val) === '') {
                continue;
            }
            $col = $cols[$key];
            $v   = $conn->real_escape_string(trim((string)$val));
            if ($key === 'asset_type') {
                $where[] = "asset_type = '$v'";
            } else {
                $where[] = "$col LIKE '%$v%'";
            }
        }
        return $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    }

    /**
     * Combined asset master rows (aset_it + aset_ga) with optional search/filters.
     * Returns rows as associative arrays with asset_type = 'IT' | 'GA'.
     */
    public static function assetMasterRows(mysqli $conn, string $q = '', array $filters = [], ?int $limit = null, int $offset = 0): array
    {
        $sql = "SELECT * FROM (
                    SELECT id, 'IT' AS asset_type, asset_number, nama_barang, serial_number, asset_class,
                           pic, area, location_note, utilisasi, date_of_entry, kondisi,
                           stocktaking_status, created_at
                    FROM aset_it
                    UNION ALL
                    SELECT id, 'GA' AS asset_type, asset_number, nama_barang, serial_number, asset_class,
                           pic, area, location_note, utilisasi, date_of_entry, kondisi,
                           stocktaking_status, created_at
                    FROM aset_ga
                ) AS master"
            . self::assetMasterWhere($conn, $q, $filters)
            . ' ORDER BY created_at DESC, id DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, (int)$limit) . ' OFFSET ' . max(0, (int)$offset);
        }
        $result = $conn->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Total count for the combined asset query (same filters as assetMasterRows).
     */
    public static function assetMasterCount(mysqli $conn, string $q = '', array $filters = []): int
    {
        // The subquery must expose every searchable/filterable column so the
        // shared WHERE clause (which references asset_number, nama_barang, ...)
        // also applies to the COUNT query.
        $sql = "SELECT COUNT(*) AS c FROM (
                    SELECT id, 'IT' AS asset_type, asset_number, nama_barang, serial_number, asset_class,
                           pic, area, location_note, utilisasi, date_of_entry, kondisi,
                           stocktaking_status, created_at
                    FROM aset_it
                    UNION ALL
                    SELECT id, 'GA' AS asset_type, asset_number, nama_barang, serial_number, asset_class,
                           pic, area, location_note, utilisasi, date_of_entry, kondisi,
                           stocktaking_status, created_at
                    FROM aset_ga
                ) AS master"
            . self::assetMasterWhere($conn, $q, $filters);
        $result = $conn->query($sql);
        return $result ? (int)$result->fetch_assoc()['c'] : 0;
    }
}
