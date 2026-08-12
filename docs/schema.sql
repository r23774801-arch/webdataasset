-- United Tractors Asset Management System
-- Complete production schema for a fresh MySQL/MariaDB database.
-- Import this once before running migrate_db.php for future incremental changes.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nrp VARCHAR(50) NOT NULL,
    username VARCHAR(100) NOT NULL,
    nama_lengkap VARCHAR(100) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','it','ga','user') NOT NULL DEFAULT 'user',
    email VARCHAR(100) DEFAULT NULL,
    area VARCHAR(100) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    status ENUM('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
    photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_nrp (nrp),
    KEY idx_users_role (role),
    KEY idx_users_status (status),
    KEY idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS aset_it (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_number VARCHAR(100) DEFAULT NULL,
    nama_barang VARCHAR(255) NOT NULL,
    serial_number VARCHAR(100) DEFAULT NULL,
    asset_class VARCHAR(100) DEFAULT NULL,
    pic VARCHAR(100) DEFAULT NULL,
    area VARCHAR(100) DEFAULT NULL,
    location_note VARCHAR(255) DEFAULT NULL,
    utilisasi ENUM('Yes','No') DEFAULT 'No',
    date_of_entry DATE DEFAULT NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    kondisi ENUM('Normal','Broken','Lost','Transfer','-') NULL DEFAULT '-',
    stocktaking_status ENUM('Pending','Document Needed','Stocktaked') DEFAULT 'Pending',
    stocktaking_photo VARCHAR(255) DEFAULT NULL,
    stocktaking_condition VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_aset_it_asset_number (asset_number),
    KEY idx_aset_it_serial_number (serial_number),
    KEY idx_aset_it_area (area),
    KEY idx_aset_it_kondisi (kondisi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS aset_ga (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_number VARCHAR(100) DEFAULT NULL,
    nama_barang VARCHAR(255) NOT NULL,
    serial_number VARCHAR(100) DEFAULT NULL,
    asset_class VARCHAR(100) DEFAULT NULL,
    pic VARCHAR(100) DEFAULT NULL,
    area VARCHAR(100) DEFAULT NULL,
    location_note VARCHAR(255) DEFAULT NULL,
    utilisasi ENUM('Yes','No') DEFAULT 'No',
    date_of_entry DATE DEFAULT NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    kondisi ENUM('Normal','Broken','Lost','Transfer','-') NULL DEFAULT '-',
    stocktaking_status ENUM('Pending','Document Needed','Stocktaked') DEFAULT 'Pending',
    stocktaking_photo VARCHAR(255) DEFAULT NULL,
    stocktaking_condition VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_aset_ga_asset_number (asset_number),
    KEY idx_aset_ga_serial_number (serial_number),
    KEY idx_aset_ga_area (area),
    KEY idx_aset_ga_kondisi (kondisi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stocktaking_submissions (
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
    rejected_by VARCHAR(20) DEFAULT NULL,
    rejected_by_name VARCHAR(100) DEFAULT NULL,
    rejection_date DATETIME DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_stocktaking_asset_status (asset_type, status),
    KEY idx_stocktaking_submitted_by (submitted_by),
    KEY idx_stocktaking_code (submission_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS barang_masuk_it (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_number VARCHAR(100) DEFAULT NULL,
    asset_name VARCHAR(255) NOT NULL,
    jumlah INT NOT NULL DEFAULT 0,
    unit VARCHAR(50) DEFAULT NULL,
    supplier VARCHAR(255) DEFAULT NULL,
    tanggal DATE DEFAULT NULL,
    pic VARCHAR(100) DEFAULT NULL,
    area VARCHAR(100) DEFAULT NULL,
    nomor_tiket VARCHAR(100) DEFAULT NULL,
    submission_code VARCHAR(30) DEFAULT NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_barang_masuk_it_nomor_tiket (nomor_tiket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS barang_masuk_ga LIKE barang_masuk_it;

CREATE TABLE IF NOT EXISTS barang_keluar_it (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_number VARCHAR(100) DEFAULT NULL,
    asset_name VARCHAR(255) NOT NULL,
    jumlah INT NOT NULL DEFAULT 0,
    unit VARCHAR(50) DEFAULT NULL,
    tanggal DATE DEFAULT NULL,
    pic VARCHAR(100) DEFAULT NULL,
    area VARCHAR(100) DEFAULT NULL,
    nomor_tiket VARCHAR(100) DEFAULT NULL,
    submission_code VARCHAR(30) DEFAULT NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_barang_keluar_it_nomor_tiket (nomor_tiket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS barang_keluar_ga LIKE barang_keluar_it;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_nrp VARCHAR(20) DEFAULT NULL,
    user_name VARCHAR(100) DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) DEFAULT NULL,
    record_id INT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_created_at (created_at),
    KEY idx_audit_user (user_nrp),
    KEY idx_audit_table_record (table_name, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transfer_history (
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
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_transfer_asset_number (asset_number),
    KEY idx_transfer_type_date (asset_type, transfer_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS master_employee (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nrp VARCHAR(50) NOT NULL,
    employee_name VARCHAR(255) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_master_employee_nrp (nrp),
    KEY idx_master_employee_nrp (nrp),
    KEY idx_master_employee_name (employee_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS master_area (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_master_area_name (area_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO master_area (area_name)
VALUES ('Main Office'), ('Part BKJ'), ('Kel.'), ('BIU Service'), ('Part BIU'), ('Part BIU 3'), ('PTK'), ('Gudang');

CREATE TABLE IF NOT EXISTS user_approvals (
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
    UNIQUE KEY uq_user_approvals_nrp (nrp),
    KEY idx_user_approvals_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
