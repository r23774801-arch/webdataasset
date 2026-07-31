<?php
include "koneksi.php";
$db_connection = isset($conn) ? $conn : (isset($koneksi) ? $koneksi : null);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Aset - PT United Tractors</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; color: #333; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #1E5AA8; padding-bottom: 10px; }
        .header h2 { color: #1E5AA8; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; margin-bottom: 30px; }
        th { background-color: #FFCC00; color: #000; padding: 10px; border: 1px solid #999; text-align: left; }
        td { padding: 8px; border: 1px solid #999; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .text-center { text-align: center; }
        .section-title { font-size: 14px; font-weight: bold; background: #1E5AA8; color: #FFCC00; padding: 8px 12px; margin-top: 20px; }
    </style>
</head>
<body>

<div class="header">
    <h2>LAPORAN REKAPITULASI ASET</h2>
    <p>PT United Tractors Tbk</p>
    <p><small>Tanggal Cetak: <?php echo date('d F Y'); ?></small></p>
</div>

<div class="section-title">RINGKASAN ASET PER LOKASI (AREA)</div>
<table>
    <thead>
        <tr>
            <th class="text-center" width="50px">No</th>
            <th>Lokasi (Area)</th>
            <th class="text-center">Total Aset</th>
            <th class="text-center">Normal</th>
            <th class="text-center">Broken</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($db_connection) {
            $query = mysqli_query($db_connection, "SELECT area, COUNT(*) as total, SUM(CASE WHEN kondisi='Normal' THEN 1 ELSE 0 END) as normal, SUM(CASE WHEN kondisi='Broken' THEN 1 ELSE 0 END) as broken FROM aset_it GROUP BY area");
            if ($query && mysqli_num_rows($query) > 0) {
                $no = 1;
                while ($row = mysqli_fetch_assoc($query)) {
                    echo "<tr>";
                    echo "<td class='text-center'>" . $no++ . "</td>";
                    echo "<td><strong>" . htmlspecialchars($row['area']) . "</strong></td>";
                    echo "<td class='text-center'>" . $row['total'] . "</td>";
                    echo "<td class='text-center' style='color:green; font-weight:bold;'>" . $row['normal'] . "</td>";
                    echo "<td class='text-center' style='color:red; font-weight:bold;'>" . $row['broken'] . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5' class='text-center'>Belum ada data aset IT</td></tr>";
            }
        } else {
            echo "<tr><td colspan='5' class='text-center' style='color:red;'>Koneksi database gagal</td></tr>";
        }
        ?>
    </tbody>
</table>

<script>
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500);
    };
</script>

</body>
</html>