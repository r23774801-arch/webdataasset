<?php
/**
 * get_analytics_data.php — thin controller for the analytics dashboard.
 * Returns KPI values with trends + monthly chart series, all from MySQL.
 * The Google Spreadsheet is never queried for reports.
 */
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../app/bootstrap.php';
require_login();

require_once __DIR__ . '/../../config/koneksi.php';

// Note: previously public; now gated by require_login to match the rest of
// the dashboard/report data model.

// Defensive: the report tables must exist (run migrate_db.php once).
if (!table_exists($conn, 'aset_it') || !table_exists($conn, 'aset_ga')) {
    echo json_encode(['status' => 'error', 'message' => 'Database belum siap. Jalankan migrate_db.php terlebih dahulu.']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'data'   => AnalyticsService::dashboard($conn),
]);
