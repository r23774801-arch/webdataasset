<?php
include 'koneksi.php';

$queries = [
    // Add columns to aset_ga
    "ALTER TABLE aset_ga ADD COLUMN IF NOT EXISTS asset_class VARCHAR(100) DEFAULT NULL AFTER serial_number",
    "ALTER TABLE aset_ga ADD COLUMN IF NOT EXISTS location_note VARCHAR(255) DEFAULT NULL AFTER area",
    "ALTER TABLE aset_ga ADD COLUMN IF NOT EXISTS utilisasi ENUM('Yes','No') DEFAULT 'No'",
    "ALTER TABLE aset_ga ADD COLUMN IF NOT EXISTS date_of_entry DATE DEFAULT NULL",
    "ALTER TABLE aset_ga ADD COLUMN IF NOT EXISTS stocktaking_status ENUM('Pending','Document Needed','Stocktaked') DEFAULT 'Pending'",
    "ALTER TABLE aset_ga ADD COLUMN IF NOT EXISTS stocktaking_photo VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE aset_ga ADD COLUMN IF NOT EXISTS stocktaking_condition VARCHAR(50) DEFAULT NULL",
    
    // Add columns to aset_it
    "ALTER TABLE aset_it ADD COLUMN IF NOT EXISTS asset_class VARCHAR(100) DEFAULT NULL AFTER serial_number",
    "ALTER TABLE aset_it ADD COLUMN IF NOT EXISTS location_note VARCHAR(255) DEFAULT NULL AFTER area",
    "ALTER TABLE aset_it ADD COLUMN IF NOT EXISTS utilisasi ENUM('Yes','No') DEFAULT 'No'",
    "ALTER TABLE aset_it ADD COLUMN IF NOT EXISTS date_of_entry DATE DEFAULT NULL",
    "ALTER TABLE aset_it ADD COLUMN IF NOT EXISTS stocktaking_status ENUM('Pending','Document Needed','Stocktaked') DEFAULT 'Pending'",
    "ALTER TABLE aset_it ADD COLUMN IF NOT EXISTS stocktaking_photo VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE aset_it ADD COLUMN IF NOT EXISTS stocktaking_condition VARCHAR(50) DEFAULT NULL",
];

$allSuccess = true;
foreach ($queries as $q) {
    // MySQL doesn't support ADD COLUMN IF NOT EXISTS, so we use a try/catch approach
    try {
        // Check if column exists first
        $table = (strpos($q, 'aset_ga') !== false) ? 'aset_ga' : 'aset_it';
        
        // Extract column name from query
        preg_match("/ADD COLUMN IF NOT EXISTS (\w+)/", $q, $matches);
        if (isset($matches[1])) {
            $col = $matches[1];
            $check = $conn->query("SHOW COLUMNS FROM $table LIKE '$col'");
            if ($check && $check->num_rows > 0) {
                echo "Column $col already exists in $table. Skipping.\n";
                continue;
            }
        }
        
        // Remove 'IF NOT EXISTS' for actual execution
        $sql = str_replace("ADD COLUMN IF NOT EXISTS", "ADD COLUMN", $q);
        if ($conn->query($sql)) {
            echo "OK: " . $sql . "\n";
        } else {
            echo "ERROR: " . $conn->error . "\n";
            $allSuccess = false;
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $allSuccess = false;
    }
}

if ($allSuccess) {
    echo "\n✅ All migrations completed successfully!";
} else {
    echo "\n⚠️ Some migrations had errors (likely columns already exist).";
}

$conn->close();
?>
