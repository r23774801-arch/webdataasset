<?php
session_start();
if (empty($_SESSION['nrp'])) {
    header('Location: login.html');
    exit;
}
include 'koneksi.php';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="laporan_aset_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Get all assets from both tables
$query = "
    SELECT 
        id,
        asset_number,
        nama_barang,
        serial_number,
        pic,
        area,
        kondisi,
        'IT' as type,
        created_at
    FROM aset_it
    
    UNION ALL
    
    SELECT 
        id,
        asset_number,
        nama_barang,
        serial_number,
        pic,
        area,
        kondisi,
        'GA' as type,
        created_at
    FROM aset_ga
    
    ORDER BY id DESC
";

$result = $conn->query($query);

// Start output
echo '<table border="1">';
echo '<thead>';
echo '<tr style="background-color: #1E5AA8; color: #FFCC00; font-weight: bold;">';
echo '<th>Asset Number</th>';
echo '<th>Asset Name</th>';
echo '<th>Serial Number</th>';
echo '<th>PIC</th>';
echo '<th>Location (Area)</th>';
echo '<th>Condition</th>';
echo '<th>Type</th>';
echo '<th>Created At</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

while ($row = $result->fetch_assoc()) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($row['asset_number'] ?: '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['nama_barang']) . '</td>';
    echo '<td>' . htmlspecialchars($row['serial_number'] ?: '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['pic'] ?: '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['area']) . '</td>';
    
    // Color coding for condition
    $kondisi = $row['kondisi'];
    if ($kondisi == 'Normal') {
        echo '<td style="background-color: #d4edda; color: #155724;">' . $kondisi . '</td>';
    } elseif ($kondisi == 'Broken') {
        echo '<td style="background-color: #f8d7da; color: #721c24;">' . $kondisi . '</td>';
    } elseif ($kondisi == '-') {
        echo '<td style="background-color: #fff3cd; color: #856404;">Pending</td>';
    } else {
        echo '<td>' . $kondisi . '</td>';
    }
    
    echo '<td>' . $row['type'] . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($row['created_at'])) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

$conn->close();
?>