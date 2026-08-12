<?php
/**
 * get_asset_master_data.php — Asset Master Data API (server-side pagination).
 *
 * Combines all aset_it + aset_ga records into one listing with a global
 * search (?q=), whitelisted column filters (?filters[col]=value), and
 * pagination (?page=, ?limit=). Reads directly from MySQL — never from the
 * Google Spreadsheet copy.
 *
 * Response: { status, data, total, page, limit, total_pages }
 */
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../app/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/koneksi.php';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
$q     = trim((string)($_GET['q'] ?? ''));

// Column filters: allow both flat (?filters[area]=X) and nested (?filters[area][value]=X).
$filters = [];
if (!empty($_GET['filters']) && is_array($_GET['filters'])) {
    foreach ($_GET['filters'] as $key => $val) {
        if (is_array($val)) {
            $filters[$key] = (string)($val['value'] ?? '');
        } else {
            $filters[$key] = (string)$val;
        }
    }
}

$total  = ReportService::assetMasterCount($conn, $q, $filters);
$offset = ($page - 1) * $limit;
$rows   = ReportService::assetMasterRows($conn, $q, $filters, $limit, $offset);

echo json_encode([
    'status'      => 'success',
    'data'        => $rows,
    'total'       => $total,
    'page'        => $page,
    'limit'       => $limit,
    'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
]);
$conn->close();
