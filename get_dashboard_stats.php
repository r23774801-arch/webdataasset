<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

require_login();

function count_rows(mysqli $conn, string $table): int
{
    $result = $conn->query("SELECT COUNT(*) AS c FROM $table");
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

// Assets
$itAsset  = count_rows($conn, 'aset_it');
$gaAsset  = count_rows($conn, 'aset_ga');

// Barang (typed tables)
$barangMasukIt  = count_rows($conn, 'barang_masuk_it');
$barangMasukGa  = count_rows($conn, 'barang_masuk_ga');
$barangKeluarIt = count_rows($conn, 'barang_keluar_it');
$barangKeluarGa = count_rows($conn, 'barang_keluar_ga');

// Approvals
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

$approvalResult = $conn->query("SELECT status, COUNT(*) AS c FROM stocktaking_submissions GROUP BY status");
if ($approvalResult) {
    while ($row = $approvalResult->fetch_assoc()) {
        $val = (int)$row['c'];
        switch ($row['status']) {
            case 'Pending':
                $pendingCount = $val;
                break;
            case 'Approved':
                $approvedCount = $val;
                break;
            case 'Rejected':
                $rejectedCount = $val;
                break;
        }
    }
}

json_response([
    'status' => 'success',
    'data' => [
        'it_asset'       => $itAsset,
        'ga_asset'       => $gaAsset,
        'barang_masuk_it'  => $barangMasukIt,
        'barang_masuk_ga'  => $barangMasukGa,
        'barang_keluar_it' => $barangKeluarIt,
        'barang_keluar_ga' => $barangKeluarGa,
        'pending_approval' => $pendingCount,
        'approved'         => $approvedCount,
        'rejected'         => $rejectedCount,
    ],
]);
