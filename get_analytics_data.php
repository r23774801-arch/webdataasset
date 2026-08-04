<?php
/**
 * get_analytics_data.php — thin controller for the analytics dashboard.
 * Returns KPI values with trends + monthly chart series, all from MySQL.
 * The Google Spreadsheet is never queried for reports.
 */
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

// No login guard: mirrors get_laporan_data.php, the existing report endpoint.

// Defensive: the report tables must exist (run migrate_db.php once).
if (!table_exists($conn, 'aset_it') || !table_exists($conn, 'aset_ga')) {
    echo json_encode(['status' => 'error', 'message' => 'Database belum siap. Jalankan migrate_db.php terlebih dahulu.']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'data'   => AnalyticsService::dashboard($conn),
]);
