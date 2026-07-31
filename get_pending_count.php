<?php
header('Content-Type: application/json');
include 'koneksi.php';

// Count pending records from asset tables only (barang_masuk/barang_keluar no longer have status_approval)
$counts = [
    'aset_it' => 0,
    'aset_ga' => 0
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

// Calculate total
$totalPending = array_sum($counts);

echo json_encode([
    "status" => "success",
    "total" => $totalPending,
    "details" => $counts
]);
?>
