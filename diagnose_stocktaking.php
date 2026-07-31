<?php
include 'koneksi.php';

echo "=== DIAGNOSE STOCKTAKING DATA FLOW ===\n\n";

// 1. Check latest IT asset
echo "--- Latest IT Asset ---\n";
$r = $conn->query("SELECT id, asset_number, nama_barang, kondisi, stocktaking_status, stocktaking_condition, stocktaking_photo FROM aset_it ORDER BY id DESC LIMIT 3");
while ($row = $r->fetch_assoc()) {
    echo "ID: {$row['id']} | Asset: {$row['asset_number']} | Name: {$row['nama_barang']}\n";
    echo "  kondisi: '{$row['kondisi']}' | stocktaking_status: '{$row['stocktaking_status']}' | stocktaking_condition: '{$row['stocktaking_condition']}' | stocktaking_photo: '{$row['stocktaking_photo']}'\n\n";
}

// 2. Check latest GA asset
echo "--- Latest GA Asset ---\n";
$r = $conn->query("SELECT id, asset_number, nama_barang, kondisi, stocktaking_status, stocktaking_condition, stocktaking_photo FROM aset_ga ORDER BY id DESC LIMIT 3");
while ($row = $r->fetch_assoc()) {
    echo "ID: {$row['id']} | Asset: {$row['asset_number']} | Name: {$row['nama_barang']}\n";
    echo "  kondisi: '{$row['kondisi']}' | stocktaking_status: '{$row['stocktaking_status']}' | stocktaking_condition: '{$row['stocktaking_condition']}' | stocktaking_photo: '{$row['stocktaking_photo']}'\n\n";
}

// 3. Check what get_aset_it.php returns
echo "--- Simulating get_aset_it.php response ---\n";
$r = $conn->query("SELECT id, asset_number, nama_barang, serial_number, asset_class, pic, area, location_note, utilisasi, date_of_entry, attachment, kondisi, stocktaking_status, stocktaking_photo, stocktaking_condition, created_at FROM aset_it ORDER BY id DESC LIMIT 1");
$row = $r->fetch_assoc();
echo json_encode($row, JSON_PRETTY_PRINT) . "\n\n";

// 4. Check what get_aset_ga.php returns
echo "--- Simulating get_aset_ga.php response ---\n";
$r = $conn->query("SELECT id, asset_number, nama_barang, serial_number, asset_class, pic, area, location_note, utilisasi, date_of_entry, attachment, kondisi, stocktaking_status, stocktaking_photo, stocktaking_condition, 'ga' as type, created_at FROM aset_ga ORDER BY id DESC LIMIT 1");
$row = $r->fetch_assoc();
echo json_encode($row, JSON_PRETTY_PRINT) . "\n\n";

$conn->close();
echo "=== DIAGNOSE COMPLETE ===\n";
?>