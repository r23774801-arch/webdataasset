<?php
/**
 * migrate_db.php — idempotent database migration (schema + legacy data).
 *
 * ADMIN-ONLY: this script can ALTER/CREATE tables, so it must never be
 * reachable without an authenticated ADMIN session. Run it by logging in as
 * admin and hitting this URL directly (browser or curl with session cookie).
 */
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: text/plain; charset=utf-8');

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';
require_admin();

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

    // Add user profile columns (assigned area / department)
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS area VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS department VARCHAR(100) DEFAULT NULL",

    // Add user e-mail address (used for approval result notifications)
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(100) DEFAULT NULL",

    // Add user account status (managed by the Admin "Data Akun" feature)
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif'",

    // Add user profile photo (path relative to the project root, e.g. uploads/...)
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS photo VARCHAR(255) DEFAULT NULL",

    // Full employee name, auto-filled from the master_employee directory when
    // the user picks their NRP during registration. Stored separately from the
    // (free-typed) username so the two fields stay independent.
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS nama_lengkap VARCHAR(100) DEFAULT NULL",
    // Add the generic non-admin 'user' role (register/approval workflow). The
    // original column is ENUM('admin','it','ga'); widening it to also accept
    // 'user' keeps existing roles intact.
    "ALTER TABLE users MODIFY COLUMN role ENUM('admin','it','ga','user') NOT NULL DEFAULT 'user'",

    // Approval workflow table
    "CREATE TABLE IF NOT EXISTS stocktaking_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submission_code VARCHAR(30) DEFAULT NULL,
        asset_type ENUM('IT','GA') NOT NULL,
        submitted_by VARCHAR(20) NOT NULL,
        submitted_by_name VARCHAR(100) NOT NULL,
        department VARCHAR(100) DEFAULT NULL,
        area VARCHAR(255) DEFAULT NULL,
        total_assets INT NOT NULL DEFAULT 0,
        normal_count INT NOT NULL DEFAULT 0,
        broken_count INT NOT NULL DEFAULT 0,
        lost_count INT NOT NULL DEFAULT 0,
        pending_count INT NOT NULL DEFAULT 0,
        assets_json MEDIUMTEXT DEFAULT NULL,
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        approved_by VARCHAR(20) DEFAULT NULL,
        approved_by_name VARCHAR(100) DEFAULT NULL,
        submission_date DATETIME NOT NULL,
        approval_date DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ==========================================
    // PHASE 4 — rejection fields on submissions
    // ==========================================
    "ALTER TABLE stocktaking_submissions ADD COLUMN IF NOT EXISTS rejected_by VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE stocktaking_submissions ADD COLUMN IF NOT EXISTS rejected_by_name VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE stocktaking_submissions ADD COLUMN IF NOT EXISTS rejection_date DATETIME DEFAULT NULL",
    "ALTER TABLE stocktaking_submissions ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL",

    // ==========================================
    // PHASE 4 — separated Barang tables (IT / GA)
    // ==========================================
    "CREATE TABLE IF NOT EXISTS barang_masuk_it (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_number VARCHAR(100) DEFAULT NULL,
        asset_name VARCHAR(255) NOT NULL,
        jumlah INT NOT NULL DEFAULT 0,
        supplier VARCHAR(255) DEFAULT NULL,
        tanggal DATE DEFAULT NULL,
        pic VARCHAR(100) DEFAULT NULL,
        area VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS barang_masuk_ga (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_number VARCHAR(100) DEFAULT NULL,
        asset_name VARCHAR(255) NOT NULL,
        jumlah INT NOT NULL DEFAULT 0,
        supplier VARCHAR(255) DEFAULT NULL,
        tanggal DATE DEFAULT NULL,
        pic VARCHAR(100) DEFAULT NULL,
        area VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS barang_keluar_it (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_number VARCHAR(100) DEFAULT NULL,
        asset_name VARCHAR(255) NOT NULL,
        jumlah INT NOT NULL DEFAULT 0,
        tanggal DATE DEFAULT NULL,
        pic VARCHAR(100) DEFAULT NULL,
        area VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS barang_keluar_ga (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_number VARCHAR(100) DEFAULT NULL,
        asset_name VARCHAR(255) NOT NULL,
        jumlah INT NOT NULL DEFAULT 0,
        tanggal DATE DEFAULT NULL,
        pic VARCHAR(100) DEFAULT NULL,
        area VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ==========================================
    // PHASE 4 — audit log table
    // ==========================================
    "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_nrp VARCHAR(20) DEFAULT NULL,
        user_name VARCHAR(100) DEFAULT NULL,
        action VARCHAR(100) NOT NULL,
        table_name VARCHAR(100) DEFAULT NULL,
        record_id INT DEFAULT NULL,
        details TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ==========================================
    // PHASE 4.5 — transfer history table
    // ==========================================
    "CREATE TABLE IF NOT EXISTS transfer_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_id INT DEFAULT NULL,
        asset_number VARCHAR(100) DEFAULT NULL,
        asset_name VARCHAR(255) DEFAULT NULL,
        asset_type ENUM('IT','GA') DEFAULT NULL,
        old_area VARCHAR(100) DEFAULT NULL,
        new_area VARCHAR(100) DEFAULT NULL,
        old_department VARCHAR(255) DEFAULT NULL,
        new_department VARCHAR(255) DEFAULT NULL,
        pic VARCHAR(100) DEFAULT NULL,
        transfer_date DATE DEFAULT NULL,
        transferred_by VARCHAR(100) DEFAULT NULL,
        remarks TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ==========================================
    // PHASE 4.5 — allow the Transfer condition
    // (kondisi is ENUM; 'Transfer' must be added
    // or the transfer UPDATE silently stores '')
    // ==========================================
    "ALTER TABLE aset_it MODIFY COLUMN kondisi ENUM('Normal','Broken','Lost','Transfer','-') NULL DEFAULT '-'",
    "ALTER TABLE aset_ga MODIFY COLUMN kondisi ENUM('Normal','Broken','Lost','Transfer','-') NULL DEFAULT '-'",

    // ==========================================
    // PHASE 4.9 — photo (attachment) column on
    // the separated Barang tables (entry forms)
    // ==========================================
    "ALTER TABLE barang_masuk_it ADD COLUMN IF NOT EXISTS attachment VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE barang_masuk_ga ADD COLUMN IF NOT EXISTS attachment VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE barang_keluar_it ADD COLUMN IF NOT EXISTS attachment VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE barang_keluar_ga ADD COLUMN IF NOT EXISTS attachment VARCHAR(255) DEFAULT NULL",

    // ==========================================
    // PHASE 4.10 — Nomor Tiket/Resi column on
    // the separated Barang tables
    // ==========================================
    "ALTER TABLE barang_masuk_it ADD COLUMN IF NOT EXISTS nomor_tiket VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE barang_masuk_ga ADD COLUMN IF NOT EXISTS nomor_tiket VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE barang_keluar_it ADD COLUMN IF NOT EXISTS nomor_tiket VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE barang_keluar_ga ADD COLUMN IF NOT EXISTS nomor_tiket VARCHAR(100) DEFAULT NULL",

    // ==========================================
    // PHASE 5.1 — submission_code on the Barang
    // tables (link each transaction to the
    // approved stocktaking submission that drove
    // the Inbound/Outbound movement).
    // ==========================================
    "ALTER TABLE barang_masuk_it ADD COLUMN IF NOT EXISTS submission_code VARCHAR(30) DEFAULT NULL",
    "ALTER TABLE barang_masuk_ga ADD COLUMN IF NOT EXISTS submission_code VARCHAR(30) DEFAULT NULL",
    "ALTER TABLE barang_keluar_it ADD COLUMN IF NOT EXISTS submission_code VARCHAR(30) DEFAULT NULL",
    "ALTER TABLE barang_keluar_ga ADD COLUMN IF NOT EXISTS submission_code VARCHAR(30) DEFAULT NULL",

    // ==========================================
    // PHASE 4.16 — Unit (satuan) column on
    // the separated Barang tables
    // ==========================================
    "ALTER TABLE barang_masuk_it ADD COLUMN IF NOT EXISTS unit VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE barang_masuk_ga ADD COLUMN IF NOT EXISTS unit VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE barang_keluar_it ADD COLUMN IF NOT EXISTS unit VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE barang_keluar_ga ADD COLUMN IF NOT EXISTS unit VARCHAR(50) DEFAULT NULL",

    // ==========================================
    // PHASE 4.22 — master employee directory
    // (source of truth for registration lookup)
    // ==========================================
    "CREATE TABLE IF NOT EXISTS master_employee (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nrp VARCHAR(50) NOT NULL,
        employee_name VARCHAR(255) NOT NULL,
        email VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_master_employee_nrp (nrp),
        KEY idx_master_employee_nrp (nrp),
        KEY idx_master_employee_name (employee_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ==========================================
    // PHASE 4.24 — Master Area (single source of truth for every Area
    // dropdown, summary card, report and chart).
    // ==========================================
    "CREATE TABLE IF NOT EXISTS master_area (
        id INT AUTO_INCREMENT PRIMARY KEY,
        area_name VARCHAR(100) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_master_area_name (area_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Seed the default Areas (INSERT IGNORE never duplicates existing rows).
    "INSERT IGNORE INTO master_area (area_name) VALUES ('Main Office'), ('Part BKJ'), ('Kel.'), ('BIU Service'), ('Part BIU'), ('Part BIU 3'), ('PTK'), ('Gudang')",

    // ==========================================
    // PHASE 4.25 — user registration approval:
    // new accounts are queued here (status Pending)
    // until an ADMIN approves them. Only approved
    // rows are copied into the users table.
    // ==========================================
    "CREATE TABLE IF NOT EXISTS user_approvals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nrp VARCHAR(50) NOT NULL,
        username VARCHAR(100) NOT NULL,
        nama_lengkap VARCHAR(100) DEFAULT NULL,
        email VARCHAR(100) DEFAULT NULL,
        password VARCHAR(255) NOT NULL,
        department VARCHAR(100) DEFAULT NULL,
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        requested_at DATETIME NOT NULL,
        reviewed_by VARCHAR(20) DEFAULT NULL,
        reviewed_by_name VARCHAR(100) DEFAULT NULL,
        review_date DATETIME DEFAULT NULL,
        rejection_reason TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_approvals_nrp (nrp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Allow existing user_approvals tables (created before nama_lengkap was
    // introduced) to gain the column on a re-run.
    "ALTER TABLE user_approvals ADD COLUMN IF NOT EXISTS nama_lengkap VARCHAR(100) DEFAULT NULL",
];

$allSuccess = true;
foreach ($queries as $q) {
    try {
        // CREATE TABLE statements are idempotent — run directly.
        if (preg_match('/^\s*CREATE TABLE/i', $q)) {
            if ($conn->query($q)) {
                echo "OK: " . substr($q, 0, 90) . "...\n";
            } else {
                echo "ERROR: " . $conn->error . "\n";
                $allSuccess = false;
            }
            continue;
        }

        // ALTER TABLE statements: skip if the column already exists.
        if (!preg_match('/ALTER TABLE (\w+) ADD COLUMN IF NOT EXISTS (\w+)/', $q, $m)) {
            if ($conn->query($q)) {
                echo "OK: " . $q . "\n";
            } else {
                echo "ERROR: " . $conn->error . "\n";
                $allSuccess = false;
            }
            continue;
        }

        $table = $m[1];
        $col   = $m[2];

        $check = $conn->query("SHOW COLUMNS FROM $table LIKE '$col'");
        if ($check && $check->num_rows > 0) {
            echo "Column $col already exists in $table. Skipping.\n";
            continue;
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

// ==========================================
// PHASE 4 — idempotent data migration:
// copy legacy barang_masuk / barang_keluar
// rows into the new typed tables once.
// (NULL-safe equality keeps re-runs clean.)
// ==========================================
$migrations = [
    "INSERT INTO barang_masuk_it (asset_number, asset_name, jumlah, supplier, tanggal, pic, area, created_at)
     SELECT l.asset_number, l.asset_name, l.jumlah, l.supplier, l.tanggal, l.pic, l.area, l.created_at
     FROM barang_masuk l
     WHERE NOT EXISTS (
         SELECT 1 FROM barang_masuk_it t
         WHERE t.asset_number <=> l.asset_number
           AND t.asset_name   <=> l.asset_name
           AND t.jumlah        = l.jumlah
           AND t.supplier     <=> l.supplier
           AND t.tanggal      <=> l.tanggal
           AND t.pic          <=> l.pic
           AND t.area         <=> l.area
     )",

    "INSERT INTO barang_keluar_it (asset_number, asset_name, jumlah, tanggal, pic, area, created_at)
     SELECT l.asset_number, l.asset_name, l.jumlah, l.tanggal, l.pic, l.area, l.created_at
     FROM barang_keluar l
     WHERE NOT EXISTS (
         SELECT 1 FROM barang_keluar_it t
         WHERE t.asset_number <=> l.asset_number
           AND t.asset_name   <=> l.asset_name
           AND t.jumlah        = l.jumlah
           AND t.tanggal      <=> l.tanggal
           AND t.pic          <=> l.pic
           AND t.area         <=> l.area
     )",

    // ==========================================
    // PHASE 5.1 — rename legacy STK- submission
    // codes to the BKJ- prefix (new site code).
    // NULL-safe: only touches codes that still
    // carry the old site prefix; idempotent.
    // ==========================================
    "UPDATE stocktaking_submissions
     SET submission_code = CONCAT('BKJ-', SUBSTRING(submission_code, 5))
     WHERE submission_code LIKE 'STK-%'",
];

foreach ($migrations as $sql) {
    if ($conn->query($sql)) {
        echo "OK: data migration -> " . substr($sql, 0, 80) . "... (" . $conn->affected_rows . " rows)\n";
    } else {
        echo "ERROR: " . $conn->error . "\n";
        $allSuccess = false;
    }
}

if ($allSuccess) {
    echo "\n✅ All migrations completed successfully!";
} else {
    echo "\n⚠️ Some migrations had errors (likely columns already exist).";
}

$conn->close();
