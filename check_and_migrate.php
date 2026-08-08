<?php
session_start();
require_once __DIR__ . '/app/bootstrap.php';
require_admin();
include 'koneksi.php';

echo "Checking aset_ga table structure...\n\n";

// Check if stocktaking columns exist
$columns_to_check = ['stocktaking_status', 'stocktaking_photo', 'stocktaking_condition'];
$missing_columns = [];

foreach ($columns_to_check as $col) {
    $result = $conn->query("SHOW COLUMNS FROM aset_ga LIKE '$col'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Column '$col' exists\n";
    } else {
        echo "✗ Column '$col' MISSING\n";
        $missing_columns[] = $col;
    }
}

if (!empty($missing_columns)) {
    echo "\n=== Adding missing columns ===\n";
    foreach ($missing_columns as $col) {
        $type = ($col === 'stocktaking_status') ? "ENUM('Pending','Document Needed','Stocktaked') DEFAULT 'Pending'" : "VARCHAR(255) DEFAULT NULL";
        $sql = "ALTER TABLE aset_ga ADD COLUMN $col $type";
        if ($conn->query($sql)) {
            echo "✓ Added column '$col'\n";
        } else {
            echo "✗ Failed to add column '$col': " . $conn->error . "\n";
        }
    }
}

// Also check aset_it
echo "\nChecking aset_it table structure...\n\n";
foreach ($columns_to_check as $col) {
    $result = $conn->query("SHOW COLUMNS FROM aset_it LIKE '$col'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Column '$col' exists\n";
    } else {
        echo "✗ Column '$col' MISSING - adding...\n";
        $type = ($col === 'stocktaking_status') ? "ENUM('Pending','Document Needed','Stocktaked') DEFAULT 'Pending'" : "VARCHAR(255) DEFAULT NULL";
        $sql = "ALTER TABLE aset_it ADD COLUMN $col $type";
        if ($conn->query($sql)) {
            echo "✓ Added column '$col'\n";
        } else {
            echo "✗ Failed to add column '$col': " . $conn->error . "\n";
        }
    }
}

$conn->close();
echo "\nDone!\n";
?>