<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../app/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/koneksi.php';

// Count pending records from asset tables only (barang_masuk/barang_keluar no longer have status_approval)
$counts = [
    'aset_it' => 0,
    'aset_ga' => 0,
    'barang_masuk' => 0,
    'barang_keluar' => 0,
    'pending_approval' => 0,
    'pending_users' => 0
];

// Count pending aset_it (where kondisi is still '-', meaning unverified)
$result = $conn->query("SELECT COUNT(*) as count FROM aset_it WHERE kondisi = '-'");
if ($row = $result->fetch_assoc()) {
    $counts['aset_it'] = (int)$row['count'];
}

// Count pending aset_ga (where kondisi is still '-', meaning unverified)
$result = $conn->query("SELECT COUNT(*) as count FROM aset_ga WHERE kondisi = '-'");
if ($row = $result->fetch_assoc()) {
    $counts['aset_ga'] = (int)$row['count'];
}

// Count typed barang tables (IT + GA combined per module)
$result = $conn->query("SELECT COUNT(*) as count FROM barang_masuk_it");
if ($row = $result->fetch_assoc()) {
    $counts['barang_masuk'] += (int)$row['count'];
}
$result = $conn->query("SELECT COUNT(*) as count FROM barang_masuk_ga");
if ($row = $result->fetch_assoc()) {
    $counts['barang_masuk'] += (int)$row['count'];
}
$result = $conn->query("SELECT COUNT(*) as count FROM barang_keluar_it");
if ($row = $result->fetch_assoc()) {
    $counts['barang_keluar'] += (int)$row['count'];
}
$result = $conn->query("SELECT COUNT(*) as count FROM barang_keluar_ga");
if ($row = $result->fetch_assoc()) {
    $counts['barang_keluar'] += (int)$row['count'];
}

// Count pending stocktaking submissions (awaiting admin approval)
$result = $conn->query("SELECT COUNT(*) as count FROM stocktaking_submissions WHERE status = 'Pending'");
if ($row = $result->fetch_assoc()) {
    $counts['pending_approval'] = (int)$row['count'];
}

// Count pending user registrations (awaiting admin approval)
$counts['pending_users'] = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM user_approvals WHERE status = 'Pending'");
if ($row = $result->fetch_assoc()) {
    $counts['pending_users'] = (int)$row['count'];
}

// Calculate total
$totalPending = array_sum($counts);

echo json_encode([
    "status" => "success",
    "total" => $totalPending,
    "details" => $counts
]);
?>
