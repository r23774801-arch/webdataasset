<?php
header('Content-Type: application/json');
include 'koneksi.php';

$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Build search condition
$searchCondition = '';
if (!empty($searchTerm)) {
    $searchTerm = $conn->real_escape_string($searchTerm);
    $searchCondition = "WHERE (nama_barang LIKE '%$searchTerm%' 
                        OR serial_number LIKE '%$searchTerm%' 
                        OR pic LIKE '%$searchTerm%')";
}

// Get total count
$countQuery_it = "SELECT COUNT(*) as total FROM aset_it $searchCondition";
$countQuery_ga = "SELECT COUNT(*) as total FROM aset_ga $searchCondition";

$result_it = $conn->query($countQuery_it);
$result_ga = $conn->query($countQuery_ga);

$total_it = $result_it->fetch_assoc()['total'];
$total_ga = $result_ga->fetch_assoc()['total'];
$totalRecords = $total_it + $total_ga;

// Fetch data from both tables with LIMIT - include all required fields for stocktaking
$query_it = "SELECT id, asset_number, nama_barang, serial_number, asset_class, pic, area, location_note, utilisasi, date_of_entry, attachment, kondisi, stocktaking_status, stocktaking_photo, stocktaking_condition, 'it' as type 
             FROM aset_it $searchCondition 
             ORDER BY id DESC 
             LIMIT $limit OFFSET $offset";
$query_ga = "SELECT id, asset_number, nama_barang, serial_number, asset_class, pic, area, location_note, utilisasi, date_of_entry, attachment, kondisi, stocktaking_status, stocktaking_photo, stocktaking_condition, 'ga' as type 
             FROM aset_ga $searchCondition 
             ORDER BY id DESC 
             LIMIT $limit OFFSET $offset";

$result_it = $conn->query($query_it);
$result_ga = $conn->query($query_ga);

$data = [];

// Add IT assets
while ($row = $result_it->fetch_assoc()) {
    $data[] = $row;
}

// Add GA assets
while ($row = $result_ga->fetch_assoc()) {
    $data[] = $row;
}

// Sort by ID descending (combine both results)
usort($data, function($a, $b) {
    return $b['id'] - $a['id'];
});

// Re-apply limit after merge
$data = array_slice($data, 0, $limit);

echo json_encode([
    "status" => "success",
    "data" => $data,
    "total" => $totalRecords,
    "page" => $page,
    "limit" => $limit,
    "totalPages" => ceil($totalRecords / $limit)
]);
?>