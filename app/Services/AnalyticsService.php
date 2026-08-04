<?php
/**
 * AnalyticsService — KPI counts + month-over-month trends + monthly series
 * for the analytics dashboard (laporan.html).
 *
 * All calculations read from MySQL (the primary source of truth).
 * Google Sheets is never queried for reports.
 *
 * Trend formula (per requirement):
 *   (Current Month - Previous Month) / Previous Month * 100
 *   previous == 0 && current > 0  => +100% (green, "up")
 *   previous == 0 && current == 0 => 0%    (flat)
 */
class AnalyticsService
{
    /**
     * Full dashboard payload: KPI values with trends + chart series.
     */
    public static function dashboard(mysqli $conn): array
    {
        return [
            'kpis'   => self::kpis($conn),
            'series' => self::series($conn),
        ];
    }

    /**
     * KPI list: value, previous, trend %, direction (up/down/flat).
     */
    public static function kpis(mysqli $conn): array
    {
        $range = self::monthRange();

        $count = function (string $table, string $dateCol, string $extraWhere = '') use ($conn, $range): array {
            $current  = self::countBetween($conn, $table, $dateCol, $range['current_start'], $range['current_end'], $extraWhere);
            $previous = self::countBetween($conn, $table, $dateCol, $range['previous_start'], $range['previous_end'], $extraWhere);
            $value    = self::countRows($conn, $table, $extraWhere);
            return self::kpiEntry($value, $previous, $current);
        };

        return [
            // ---- Assets ----
            'total_asset'        => self::combineKpis(
                $count('aset_it', 'created_at'),
                $count('aset_ga', 'created_at')
            ),
            'asset_it'           => $count('aset_it', 'created_at'),
            'asset_ga'           => $count('aset_ga', 'created_at'),

            // ---- Barang ----
            'barang_masuk_it'    => $count('barang_masuk_it', 'created_at'),
            'barang_masuk_ga'    => $count('barang_masuk_ga', 'created_at'),
            'barang_keluar_it'   => $count('barang_keluar_it', 'created_at'),
            'barang_keluar_ga'   => $count('barang_keluar_ga', 'created_at'),

            // ---- Approvals (stocktaking submissions) ----
            'approved'           => $count('stocktaking_submissions', 'approval_date', "status = 'Approved'"),
            'rejected'           => $count('stocktaking_submissions', 'rejection_date', "status = 'Rejected'"),
            'pending'            => $count('stocktaking_submissions', 'submission_date', "status = 'Pending'"),

            // ---- Transfer condition (assets flagged as Transfer) ----
            'transfer'           => self::combineKpis(
                $count('aset_it', 'created_at', "kondisi = 'Transfer'"),
                $count('aset_ga', 'created_at', "kondisi = 'Transfer'")
            ),
        ];
    }

    /**
     * Monthly series for the line / area charts (last 6 months).
     */
    public static function series(mysqli $conn): array
    {
        $months = [];
        $labels = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = (new DateTimeImmutable('first day of this month'))->modify("-$i months");
            $months[] = $m;
            $labels[] = $m->format('M Y');
        }

        $monthly = function (string $table, string $dateCol, string $extraWhere = '') use ($conn, $months): array {
            $out = [];
            foreach ($months as $m) {
                $start = $m->format('Y-m-d 00:00:00');
                $end   = $m->modify('+1 month')->format('Y-m-d 00:00:00');
                $out[] = self::countBetween($conn, $table, $dateCol, $start, $end, $extraWhere);
            }
            return $out;
        };

        $addSeries = function (array $a, array $b): array {
            $out = [];
            foreach ($a as $i => $v) {
                $out[] = $v + ($b[$i] ?? 0);
            }
            return $out;
        };

        $assetIt  = $monthly('aset_it', 'created_at');
        $assetGa  = $monthly('aset_ga', 'created_at');
        $masukIt  = $monthly('barang_masuk_it', 'created_at');
        $masukGa  = $monthly('barang_masuk_ga', 'created_at');
        $keluarIt = $monthly('barang_keluar_it', 'created_at');
        $keluarGa = $monthly('barang_keluar_ga', 'created_at');

        // Cumulative asset totals. The last point is forced to equal the KPI
        // total_asset value so cards and charts can never disagree.
        $totalIt = self::countRows($conn, 'aset_it');
        $totalGa = self::countRows($conn, 'aset_ga');
        $assetTotalCumulative = self::cumulativeSeries($assetIt, $assetGa, $totalIt + $totalGa);

        return [
            'labels'             => $labels,
            // Line chart: cumulative asset totals (last point == total_asset KPI)
            'asset_it'           => self::cumulativeSeries($assetIt, [], $totalIt),
            'asset_ga'           => self::cumulativeSeries($assetGa, [], $totalGa),
            'asset_total'        => $assetTotalCumulative,
            // Area chart: barang activity per month
            'barang_masuk_it'    => $masukIt,
            'barang_masuk_ga'    => $masukGa,
            'barang_masuk_total' => $addSeries($masukIt, $masukGa),
            'barang_keluar_it'   => $keluarIt,
            'barang_keluar_ga'   => $keluarGa,
            'barang_keluar_total'=> $addSeries($keluarIt, $keluarGa),
            // Approval trend per month
            'approved'           => $monthly('stocktaking_submissions', 'approval_date', "status = 'Approved'"),
            'rejected'           => $monthly('stocktaking_submissions', 'rejection_date', "status = 'Rejected'"),
            'pending'            => $monthly('stocktaking_submissions', 'submission_date', "status = 'Pending'"),
        ];
    }

    /**
     * Build a cumulative (running-total) series from monthly additions.
     * The final element is always set to $finalTotal so the chart's last
     * point exactly matches the matching KPI value.
     */
    private static function cumulativeSeries(array $monthlyAdditions, array $secondary, int $finalTotal): array
    {
        $running = 0;
        $out = [];
        $count = max(count($monthlyAdditions), count($secondary));
        for ($i = 0; $i < $count; $i++) {
            $running += (int)($monthlyAdditions[$i] ?? 0) + (int)($secondary[$i] ?? 0);
            $out[] = $running;
        }
        // Force the last point to the authoritative total (single source of truth).
        if ($out !== []) {
            $out[count($out) - 1] = $finalTotal;
        }
        return $out;
    }

    // ==========================================================
    // Internals
    // ==========================================================

    /**
     * First day boundaries for current + previous month.
     */
    private static function monthRange(): array
    {
        $now = new DateTimeImmutable('first day of this month');
        return [
            'current_start'  => $now->format('Y-m-d 00:00:00'),
            'current_end'    => $now->modify('+1 month')->format('Y-m-d 00:00:00'),
            'previous_start' => $now->modify('-1 month')->format('Y-m-d 00:00:00'),
            'previous_end'   => $now->format('Y-m-d 00:00:00'),
        ];
    }

    /**
     * Row count within a date range, with an optional extra WHERE clause.
     */
    private static function countBetween(mysqli $conn, string $table, string $dateCol, string $start, string $end, string $extraWhere = ''): int
    {
        try {
            $where = "$dateCol >= ? AND $dateCol < ?";
            if ($extraWhere !== '') {
                $where .= ' AND ' . $extraWhere;
            }
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM `$table` WHERE $where");
            if (!$stmt) {
                return 0;
            }
            $stmt->bind_param('ss', $start, $end);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            return (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
            error_log('[AnalyticsService] countBetween error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Total row count with an optional extra WHERE clause.
     */
    private static function countRows(mysqli $conn, string $table, string $extraWhere = ''): int
    {
        try {
            $where = $extraWhere !== '' ? ' WHERE ' . $extraWhere : '';
            $result = $conn->query("SELECT COUNT(*) AS c FROM `$table`$where");
            if (!$result) {
                return 0;
            }
            $row = $result->fetch_assoc();
            return (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
            error_log('[AnalyticsService] countRows error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Build a KPI entry from value / previous / current month counts.
     */
    private static function kpiEntry(int $value, int $previous, int $current): array
    {
        if ($previous > 0) {
            $trend = round((($current - $previous) / $previous) * 100, 1);
        } elseif ($current > 0) {
            $trend = 100.0;
        } else {
            $trend = 0.0;
        }

        return [
            'value'     => $value,
            'previous'  => $previous,
            'current'   => $current,
            'trend'     => $trend,
            'direction' => $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'flat'),
        ];
    }

    /**
     * Merge two KPIs (used for IT + GA totals).
     */
    private static function combineKpis(array $a, array $b): array
    {
        return self::kpiEntry(
            $a['value'] + $b['value'],
            $a['previous'] + $b['previous'],
            $a['current'] + $b['current']
        );
    }
}
