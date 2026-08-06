<?php
header('Content-Type: application/json');
include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

// ==========================================
// DASHBOARD SUMMARY MODE (?dashboard=true)
// ==========================================
$dashboardMode = isset($_GET['dashboard']) && $_GET['dashboard'] === 'true';

if ($dashboardMode) {
    $it = ReportService::stats($conn, 'aset_it');
    $ga = ReportService::stats($conn, 'aset_ga');
    $overall = ReportService::merge($it, $ga);

    echo json_encode([
        'status' => 'success',
        'overall' => ReportService::finalize($overall),
        'it' => ReportService::finalize($it),
        'ga' => ReportService::finalize($ga)
    ]);
    exit;
}

// Check if summary mode is requested
$summaryMode = isset($_GET['summary']) && $_GET['summary'] === 'true';

if ($summaryMode) {
    echo json_encode([
        'status' => 'success',
        'data' => ReportService::areaSummary($conn)
    ]);
    exit;
}

// ==========================================
// BARANG REPORT MODE (?barang=true)
// Returns counts + recent rows for the four typed barang tables.
// ==========================================
if (isset($_GET['barang']) && $_GET['barang'] === 'true') {
    $tables = [
        'masuk_it'  => 'barang_masuk_it',
        'masuk_ga'  => 'barang_masuk_ga',
        'keluar_it' => 'barang_keluar_it',
        'keluar_ga' => 'barang_keluar_ga',
    ];

    $out = [];
    foreach ($tables as $key => $table) {
        $countResult = $conn->query("SELECT COUNT(*) AS c FROM $table");
        $count = $countResult ? (int)$countResult->fetch_assoc()['c'] : 0;

        $rows = [];
        $dataResult = $conn->query("SELECT * FROM $table ORDER BY created_at DESC, id DESC LIMIT 20");
        if ($dataResult) {
            $rows = $dataResult->fetch_all(MYSQLI_ASSOC);
        }
        $out[$key] = ['count' => $count, 'rows' => $rows];
    }

    echo json_encode(['status' => 'success', 'data' => $out]);
    exit;
}

// ==========================================
// TRANSFER HISTORY MODE (?transfer=true)
// ==========================================
if (isset($_GET['transfer']) && $_GET['transfer'] === 'true') {
    // Defensive: the transfer_history table may not exist pre-migration.
    $tblCheck = $conn->query("SHOW TABLES LIKE 'transfer_history'");
    if (!$tblCheck || $tblCheck->num_rows === 0) {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }
    $result = $conn->query(
        "SELECT id, asset_number, asset_name, asset_type, old_area, new_area,
                old_department, new_department, pic, transfer_date,
                transferred_by, remarks, created_at
         FROM transfer_history
         ORDER BY id DESC"
    );
    $rows = ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];

    echo json_encode(['status' => 'success', 'data' => $rows]);
    exit;
}

// ==========================================
// APPROVAL HISTORY MODE (?approval=true)
// Returns all submissions with their decision fields.
// ==========================================
if (isset($_GET['approval']) && $_GET['approval'] === 'true') {
    $result = $conn->query(
        "SELECT id, submission_code, asset_type, submitted_by, submitted_by_name,
                department, area, total_assets, status, approved_by, approved_by_name,
                rejected_by, rejected_by_name, rejection_date, rejection_reason,
                submission_date, approval_date
         FROM stocktaking_submissions
         ORDER BY id DESC"
    );
    $rows = ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];

    echo json_encode(['status' => 'success', 'data' => $rows]);
    exit;
}

// ==========================================
// CHART DATA
// ==========================================

// Aggregate data from aset_it by area and kondisi
$query = "
    SELECT area, kondisi, COUNT(*) as total
    FROM (
        SELECT area, kondisi FROM aset_it
        UNION ALL
        SELECT area, kondisi FROM aset_ga
    ) as all_assets
    GROUP BY area, kondisi
    ORDER BY area, kondisi
";

$result = $conn->query($query);

$areaData = [];
// Phase 4.24 — master_area is the source of truth: every active Area is shown
// in the chart, including Areas with zero assets. Pre-migration fallback
// derives the list from the data itself — never hardcoded.
$areaLabels = AreaService::names($conn);
if (empty($areaLabels)) {
    $areasResult = $conn->query("SELECT DISTINCT area FROM aset_it UNION SELECT DISTINCT area FROM aset_ga");
    if ($areasResult) {
        while ($areaRow = $areasResult->fetch_assoc()) {
            $areaVal = trim((string)($areaRow['area'] ?? ''));
            if ($areaVal !== '' && !in_array($areaVal, $areaLabels, true)) {
                $areaLabels[] = $areaVal;
            }
        }
    }
}

// Map database kondisi values to chart labels
// '' (empty/unset) and '-' are both treated as Pending
$kondisiMap = [
    '-' => 'Pending',
    '' => 'Pending',
    'Normal' => 'Normal',
    'Broken' => 'Broken',
    'Lost' => 'Lost',
    'Transfer' => 'Transfer'
];

// Initialize structure
foreach ($areaLabels as $area) {
    $areaData[$area] = ['Normal' => 0, 'Pending' => 0, 'Broken' => 0, 'Lost' => 0, 'Transfer' => 0];
}

// Fill data
while ($row = $result->fetch_assoc()) {
    $area = $row['area'];
    $dbKondisi = $row['kondisi'];
    $chartLabel = isset($kondisiMap[$dbKondisi]) ? $kondisiMap[$dbKondisi] : $dbKondisi;
    if (isset($areaData[$area]) && isset($areaData[$area][$chartLabel])) {
        $areaData[$area][$chartLabel] += (int)$row['total'];
    }
}

// Build chart datasets
$baikData = [];
$pendingData = [];
$rusakData = [];
$lostData = [];
$transferData = [];

foreach ($areaLabels as $area) {
    $baikData[] = $areaData[$area]['Normal'];
    $pendingData[] = $areaData[$area]['Pending'];
    $rusakData[] = $areaData[$area]['Broken'];
    $lostData[] = $areaData[$area]['Lost'];
    $transferData[] = $areaData[$area]['Transfer'];
}

// Calculate totals for donut chart
$totalBaik = array_sum($baikData);
$totalPending = array_sum($pendingData);
$totalRusak = array_sum($rusakData);
$totalLost = array_sum($lostData);
$totalTransfer = array_sum($transferData);

echo json_encode([
    'status' => 'success',
    'labels' => $areaLabels,
    'datasets' => [
        ['label' => 'Normal', 'data' => $baikData, 'backgroundColor' => '#1E5AA8'],
        ['label' => 'Pending', 'data' => $pendingData, 'backgroundColor' => '#FFCC00'],
        ['label' => 'Broken', 'data' => $rusakData, 'backgroundColor' => '#0D9488'],
        ['label' => 'Lost', 'data' => $lostData, 'backgroundColor' => '#dc3545'],
        ['label' => 'Transfer', 'data' => $transferData, 'backgroundColor' => '#8B5CF6']
    ],
    'donut' => [
        'labels' => ['Normal', 'Pending', 'Broken', 'Lost', 'Transfer'],
        'data' => [$totalBaik, $totalPending, $totalRusak, $totalLost, $totalTransfer],
        'backgroundColor' => ['#1E5AA8', '#FFCC00', '#0D9488', '#dc3545', '#8B5CF6']
    ]
]);
?>
