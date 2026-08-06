<?php
/**
 * Application bootstrap — loads the shared helper functions and services.
 * Endpoints should call:  include 'koneksi.php'; require_once __DIR__ . '/app/bootstrap.php';
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Services/ApprovalService.php';
require_once __DIR__ . '/Services/MailService.php';
require_once __DIR__ . '/Services/BarangService.php';
require_once __DIR__ . '/Services/SpreadsheetService.php';
require_once __DIR__ . '/Services/AnalyticsService.php';
require_once __DIR__ . '/Services/AuditService.php';
require_once __DIR__ . '/Services/ReportService.php';
require_once __DIR__ . '/Services/AreaService.php';
require_once __DIR__ . '/Services/PdfService.php';
