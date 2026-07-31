<?php
header('Content-Type: application/json');
include 'koneksi.php';

// Check if summary mode is requested
$summaryMode = isset($_GET['summary']) && $_GET['summary'] === 'true';

if ($summaryMode) {
    // ==========================================
    // SUMMARY TABLE DATA
    // ==========================================
    
    // Get all areas from both tables
    $areasQuery = "
        SELECT DISTINCT area FROM aset_it
        UNION
        SELECT DISTINCT area FROM aset_ga
    ";
    $areasResult = $conn->query($areasQuery);
    
    $areas = [];
    while ($row = $areasResult->fetch_assoc()) {
        $areas[] = $row['area'];
    }
    
    $summaryData = [];
    
    foreach ($areas as $area) {
        $area_esc = $conn->real_escape_string($area);
        
        // Count total assets in this area
        $totalQuery_it = "SELECT COUNT(*) as total FROM aset_it WHERE area = '$area_esc'";
        $totalQuery_ga = "SELECT COUNT(*) as total FROM aset_ga WHERE area = '$area_esc'";
        
        $total_it = $conn->query($totalQuery_it)->fetch_assoc()['total'];
        $total_ga = $conn->query($totalQuery_ga)->fetch_assoc()['total'];
        $totalAssets = $total_it + $total_ga;
        
        // Count Normal assets (kondisi = 'Normal' in database)
        $normalQuery_it = "SELECT COUNT(*) as total FROM aset_it WHERE area = '$area_esc' AND kondisi = 'Normal'";
        $normalQuery_ga = "SELECT COUNT(*) as total FROM aset_ga WHERE area = '$area_esc' AND kondisi = 'Normal'";
        
        $normal_it = $conn->query($normalQuery_it)->fetch_assoc()['total'];
        $normal_ga = $conn->query($normalQuery_ga)->fetch_assoc()['total'];
        $totalNormal = $normal_it + $normal_ga;
        
        // Count Broken assets (kondisi = 'Broken' in database)
        $brokenQuery_it = "SELECT COUNT(*) as total FROM aset_it WHERE area = '$area_esc' AND kondisi = 'Broken'";
        $brokenQuery_ga = "SELECT COUNT(*) as total FROM aset_ga WHERE area = '$area_esc' AND kondisi = 'Broken'";
        
        $broken_it = $conn->query($brokenQuery_it)->fetch_assoc()['total'];
        $broken_ga = $conn->query($brokenQuery_ga)->fetch_assoc()['total'];
        $totalBroken = $broken_it + $broken_ga;
        
        // Count pending assets (kondisi = '-' OR empty string '' in database)
        $pendingQuery_it = "SELECT COUNT(*) as total FROM aset_it WHERE area = '$area_esc' AND (kondisi = '-' OR kondisi = '')";
        $pendingQuery_ga = "SELECT COUNT(*) as total FROM aset_ga WHERE area = '$area_esc' AND (kondisi = '-' OR kondisi = '')";
        
        $pending_it = $conn->query($pendingQuery_it)->fetch_assoc()['total'];
        $pending_ga = $conn->query($pendingQuery_ga)->fetch_assoc()['total'];
        $totalPending = $pending_it + $pending_ga;
        
        // Count Lost assets (kondisi = 'Lost' in database)
        $lostQuery_it = "SELECT COUNT(*) as total FROM aset_it WHERE area = '$area_esc' AND kondisi = 'Lost'";
        $lostQuery_ga = "SELECT COUNT(*) as total FROM aset_ga WHERE area = '$area_esc' AND kondisi = 'Lost'";
        
        $lost_it = $conn->query($lostQuery_it)->fetch_assoc()['total'];
        $lost_ga = $conn->query($lostQuery_ga)->fetch_assoc()['total'];
        $totalLost = $lost_it + $lost_ga;
        
        // Count stocktaking progress (all assets are considered as having completed stocktaking)
        $stocktakingDone = $totalAssets;
        
        $summaryData[] = [
            'area' => $area,
            'total' => $totalAssets,
            'normal' => $totalNormal,
            'broken' => $totalBroken,
            'pending' => $totalPending,
            'lost' => $totalLost,
            'stocktaking_done' => $stocktakingDone
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $summaryData
    ]);
    
} else {
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
    $areaLabels = ['Main Office', 'Part BKJ', 'Part BIU', 'BIU Service', 'Kel.'];
    // Map database kondisi values to chart labels
    // '' (empty/unset) and '-' are both treated as Pending
    $kondisiMap = [
        '-' => 'Pending',
        '' => 'Pending',
        'Normal' => 'Normal',
        'Broken' => 'Broken',
        'Lost' => 'Lost'
    ];

    // Initialize structure
    foreach ($areaLabels as $area) {
        $areaData[$area] = ['Normal' => 0, 'Pending' => 0, 'Broken' => 0, 'Lost' => 0];
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

    foreach ($areaLabels as $area) {
        $baikData[] = $areaData[$area]['Normal'];
        $pendingData[] = $areaData[$area]['Pending'];
        $rusakData[] = $areaData[$area]['Broken'];
        $lostData[] = $areaData[$area]['Lost'];
    }

    // Calculate totals for donut chart
    $totalBaik = array_sum($baikData);
    $totalPending = array_sum($pendingData);
    $totalRusak = array_sum($rusakData);
    $totalLost = array_sum($lostData);

    echo json_encode([
        'status' => 'success',
        'labels' => $areaLabels,
        'datasets' => [
            ['label' => 'Normal', 'data' => $baikData, 'backgroundColor' => '#1E5AA8'],
            ['label' => 'Pending', 'data' => $pendingData, 'backgroundColor' => '#FFCC00'],
            ['label' => 'Broken', 'data' => $rusakData, 'backgroundColor' => '#0D9488'],
            ['label' => 'Lost', 'data' => $lostData, 'backgroundColor' => '#dc3545']
        ],
        'donut' => [
            'labels' => ['Normal', 'Pending', 'Broken', 'Lost'],
            'data' => [$totalBaik, $totalPending, $totalRusak, $totalLost],
            'backgroundColor' => ['#1E5AA8', '#FFCC00', '#0D9488', '#dc3545']
        ]
    ]);
}
?>