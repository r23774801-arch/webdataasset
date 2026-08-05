<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

require_login();

$module = strtolower($_GET['module'] ?? '');
$type   = strtolower($_GET['type'] ?? '');
$format = strtolower($_GET['format'] ?? 'excel');

if (!in_array($module, BarangService::MODULES, true) || !in_array($type, BarangService::TYPES, true)) {
    http_response_code(400);
    echo 'Parameter module/type tidak valid.';
    exit;
}

$table = BarangService::table($module, $type);
if (!$table) {
    http_response_code(400);
    echo 'Parameter tidak valid.';
    exit;
}

$result = $conn->query("SELECT * FROM $table ORDER BY created_at DESC, id DESC");
$rows = ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];

$title = ucwords("Laporan Barang $module $type");
$isKeluar = ($module === 'keluar');

if ($format === 'pdf') {
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($title); ?> - PT United Tractors</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; color: #333; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #1E5AA8; padding-bottom: 10px; }
        .header h2 { color: #1E5AA8; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #FFCC00; color: #000; padding: 10px; border: 1px solid #999; text-align: left; }
        td { padding: 8px; border: 1px solid #999; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h2><?php echo htmlspecialchars($title); ?></h2>
        <p>PT United Tractors Tbk</p>
        <p><small>Tanggal Cetak: <?php echo date('d F Y'); ?></small></p>
    </div>
    <table>
        <thead>
            <tr>
                <th class="text-center" width="50px">No</th>
                <th>Asset Number</th>
                <th>Nomor Tiket / Resi</th>
                <th>Asset Name</th>
                <th class="text-center">Jumlah</th>
                <th>Unit</th>
                <?php if (!$isKeluar) { ?><th>Supplier</th><?php } ?>
                <th>Tanggal</th>
                <th>PIC</th>
                <th>Area</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($rows) > 0) { $no = 1; foreach ($rows as $row) { ?>
            <tr>
                <td class="text-center"><?php echo $no++; ?></td>
                <td><?php echo htmlspecialchars($row['asset_number'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($row['nomor_tiket'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                <td class="text-center"><?php echo (int)$row['jumlah']; ?></td>
                <td><?php echo htmlspecialchars($row['unit'] ?: '-'); ?></td>
                <?php if (!$isKeluar) { ?><td><?php echo htmlspecialchars($row['supplier'] ?: '-'); ?></td><?php } ?>
                <td><?php echo htmlspecialchars($row['tanggal'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($row['pic'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($row['area'] ?: '-'); ?></td>
            </tr>
            <?php } } else { ?>
            <tr><td colspan="<?php echo $isKeluar ? 8 : 9; ?>" class="text-center">Belum ada data</td></tr>
            <?php } ?>
        </tbody>
    </table>
    <script>window.onload = function() { setTimeout(function() { window.print(); }, 500); };</script>
</body>
</html>
    <?php
    exit;
}

// Default: Excel (HTML table)
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $table . '_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<table border="1">';
echo '<thead>';
echo '<tr style="background-color: #1E5AA8; color: #FFCC00; font-weight: bold;">';
echo '<th>No</th>';
echo '<th>Asset Number</th>';
echo '<th>Nomor Tiket / Resi</th>';
echo '<th>Asset Name</th>';
echo '<th>Jumlah</th>';
echo '<th>Unit</th>';
if (!$isKeluar) {
    echo '<th>Supplier</th>';
}
echo '<th>Tanggal</th>';
echo '<th>PIC</th>';
echo '<th>Area</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
foreach ($rows as $row) {
    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars($row['asset_number'] ?: '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['nomor_tiket'] ?: '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['asset_name']) . '</td>';
    echo '<td>' . (int)$row['jumlah'] . '</td>';
    echo '<td>' . htmlspecialchars($row['unit'] ?: '-') . '</td>';
    if (!$isKeluar) {
        echo '<td>' . htmlspecialchars($row['supplier'] ?: '-') . '</td>';
    }
    echo '<td>' . htmlspecialchars($row['tanggal'] ?: '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['pic'] ?: '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['area'] ?: '-') . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
$conn->close();
