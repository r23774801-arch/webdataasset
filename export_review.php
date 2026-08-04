<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

// Review export is an admin-only action (approval page is admin-only).
require_admin();

$id     = (int)($_GET['id'] ?? 0);
$format = strtolower($_GET['format'] ?? 'excel');

if ($id <= 0) {
    http_response_code(400);
    exit('ID pengajuan tidak valid.');
}
if (!in_array($format, ['excel', 'pdf'], true)) {
    http_response_code(400);
    exit('Format tidak valid.');
}

$sub = ApprovalService::getById($conn, $id);
if (!$sub) {
    http_response_code(404);
    exit('Pengajuan tidak ditemukan.');
}

$assets    = $sub['assets'] ?? [];
$isRejected = $sub['status'] === 'Rejected';

$esc = function ($v) {
    return htmlspecialchars((string)($v ?? '-'), ENT_QUOTES, 'UTF-8');
};

$meta = [
    ['User Name', $sub['submitted_by_name'] ?? '-'],
    ['NRP', $sub['submitted_by'] ?? '-'],
    ['Department', $sub['department'] ?? '-'],
    ['Area', $sub['area'] ?? '-'],
    ['Submission Date', $sub['submission_date'] ?? '-'],
    ['Status', $sub['status'] ?? '-'],
    ['Approved By', $sub['approved_by_name'] ?? '-'],
    ['Approval Date', $sub['approval_date'] ?? '-'],
    ['Rejected By', $sub['rejected_by_name'] ?? '-'],
    ['Rejection Date', $sub['rejection_date'] ?? '-'],
];

if ($isRejected && !empty($sub['rejection_reason'])) {
    $meta[] = ['Rejection Reason', $sub['rejection_reason']];
}

// ==========================================
// EXCEL (.xls) — immediate download, same
// mechanism as the existing Excel exports
// (HTML table rendered by Excel).
// ==========================================
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="review_stocktaking_' . $id . '_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $reviewTitle = $sub['submission_code'] ?? ('STK-' . $sub['id']);
    $generatedBy = $_SESSION['username'] ?? '';

    // ---- Enterprise palette ----
    $blue    = '#1E5AA8';
    $yellow  = '#FFCC00';
    $white   = '#FFFFFF';
    $labelBg = '#F2F4F8';
    $border  = '#B9C6D6';
    $altRow  = '#EEF4FB';
    $muted   = '#7F8C8D';

    // Condition badge (background / text): Normal=Green, Broken=Orange, Lost=Red, Transfer=Blue, Pending=Gray
    $condBadges = [
        'normal'   => ['#C6EFCE', '#1F5B2E'],
        'broken'   => ['#FDE9D9', '#9C4A00'],
        'lost'     => ['#FBDDDD', '#A10000'],
        'transfer' => ['#DCE9FA', '#1E5AA8'],
        'pending'  => ['#E8E8E8', '#5D6D7E'],
    ];
    // Status badge: Stocktaked=Green, Pending=Orange, Rejected=Red
    $statusBadges = [
        'stocktaked' => ['#C6EFCE', '#1F5B2E'],
        'pending'    => ['#FDE9D9', '#9C4A00'],
        'rejected'   => ['#FBDDDD', '#A10000'],
    ];
    $badgeStyle = function ($badges, $key) {
        $k = strtolower(trim((string)$key));
        // Empty, '-' and unknown values fall back to the gray Pending badge so every
        // cell renders colored (consistent with the report mapping of ''/'-' to Pending).
        if (!isset($badges[$k])) {
            return 'background:#E8E8E8; color:#5D6D7E; font-weight:bold; text-align:center;';
        }
        return 'background:' . $badges[$k][0] . '; color:' . $badges[$k][1] . '; font-weight:bold; text-align:center;';
    };

    // Row numbers (1-based) used for AutoFilter / freeze panes / print titles.
    // Layout above the asset header: 4 title rows + 1 info banner + m meta rows
    // + 1 summary banner + 2 summary rows + 1 asset banner = 9 + m rows.
    $metaCount = count($meta);
    $assetHeaderRow = 10 + $metaCount;
    $assetLastRow   = $assetHeaderRow + max(1, count($assets));

    // UTF-8 BOM so Excel decodes non-ASCII text (Indonesian names, reasons) correctly.
    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="utf-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    echo '<x:Name>Review Stocktaking</x:Name>';
    echo '<x:WorksheetOptions>';
    echo '<x:Print><x:ValidPrinterInfo/><x:PaperSizeIndex>9</x:PaperSizeIndex>'
        . '<x:HorizontalResolution>600</x:HorizontalResolution><x:VerticalResolution>600</x:VerticalResolution></x:Print>';
    echo '<x:PageSetup><x:Orientation>Landscape</x:Orientation><x:FitToPage/><x:Scale>100</x:Scale></x:PageSetup>';
    echo '<x:FitWidth>1</x:FitWidth><x:FitHeight>0</x:FitHeight>';
    echo '<x:PrintTitleRows>' . $assetHeaderRow . ':' . $assetHeaderRow . '</x:PrintTitleRows>';
    echo '<x:CenterHorizontal/>';
    echo '<x:AutoFilter x:Range="R' . $assetHeaderRow . 'C1:R' . $assetLastRow . 'C8"/>';
    echo '<x:FrozenNoSplit/>';
    echo '<x:SplitHorizontal>' . $assetHeaderRow . '</x:SplitHorizontal>';
    echo '<x:SplitVertical>0</x:SplitVertical>';
    echo '<x:TopRowBottomPane>' . ($assetHeaderRow + 1) . '</x:TopRowBottomPane>';
    echo '<x:LeftColumnRightPane>0</x:LeftColumnRightPane>';
    echo '<x:ActivePane>2</x:ActivePane>';
    echo '<x:Selected/>';
    echo '</x:WorksheetOptions>';
    echo '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>table { border-collapse: collapse; } td, th { vertical-align: middle; }</style>';
    echo '</head><body>';

    // ---- Enterprise header block (merged cells, dark blue background, white text) ----
    echo '<table style="width:100%;">'
        . '<tr><td colspan="8" style="background:' . $blue . '; color:' . $white . '; text-align:center; font-size:12pt; font-weight:bold; letter-spacing:2px; padding:8px 4px 2px 4px; border:none;">PT UNITED TRACTORS TBK</td></tr>'
        . '<tr><td colspan="8" style="background:' . $blue . '; color:' . $white . '; text-align:center; font-size:18pt; font-weight:bold; padding:2px 4px; border:none;">REVIEW STOCKTAKING</td></tr>'
        . '<tr><td colspan="8" style="background:' . $blue . '; color:' . $yellow . '; text-align:center; font-size:12pt; font-weight:bold; padding:2px 4px; border:none;">' . $esc($reviewTitle) . '</td></tr>'
        . '<tr><td colspan="8" style="background:' . $blue . '; color:#DCE9FA; text-align:center; font-size:9pt; padding:2px 4px 8px 4px; border:none;">Generated: ' . date('d F Y') . ($generatedBy !== '' ? ' &nbsp;|&nbsp; Generated By: ' . $esc($generatedBy) : '') . '</td></tr>'
        . '</table>';

    $section = function ($title) use ($blue, $yellow) {
        return '<table style="width:100%;"><tr><td colspan="8" style="background:' . $blue . '; color:' . $yellow . '; font-weight:bold; font-size:11pt; padding:5px 8px; border:1px solid ' . $blue . ';">' . $title . '</td></tr></table>';
    };

    // ---- Informasi Pengajuan (clean two-column table, bold labels on light gray) ----
    echo $section('INFORMASI PENGAJUAN');
    echo '<table style="width:100%;"><colgroup><col style="width:220px"><col style="width:440px"></colgroup>';
    foreach ($meta as $pair) {
        echo '<tr>'
            . '<th style="background:' . $labelBg . '; color:#2C3E50; font-weight:bold; text-align:left; border:1px solid ' . $border . '; padding:6px 8px;">' . $esc($pair[0]) . '</th>'
            . '<td style="border:1px solid ' . $border . '; padding:6px 8px;">' . $esc($pair[1]) . '</td>'
            . '</tr>';
    }
    echo '</table>';

    // ---- Ringkasan Kondisi (colored summary: Blue=Total, Green=Normal, Orange=Broken, Red=Lost, Gray=Pending) ----
    echo $section('RINGKASAN KONDISI');
    $sumHead = [
        ['Total Asset', $blue],
        ['Normal', '#2E7D32'],
        ['Broken', '#E67E22'],
        ['Lost', '#C0392B'],
        ['Pending', '#7F8C8D'],
    ];
    $sumVals = [
        (int)$sub['total_assets'],
        (int)$sub['normal_count'],
        (int)$sub['broken_count'],
        (int)$sub['lost_count'],
        (int)$sub['pending_count'],
    ];
    echo '<table style="width:100%;"><colgroup><col style="width:130px"><col style="width:100px"><col style="width:100px"><col style="width:100px"><col style="width:100px"></colgroup>';
    echo '<tr>';
    foreach ($sumHead as $h) {
        echo '<th style="background:' . $h[1] . '; color:' . $white . '; font-weight:bold; text-align:center; border:1px solid ' . $h[1] . '; padding:6px 4px; font-size:10pt;">' . $h[0] . '</th>';
    }
    echo '</tr><tr>';
    foreach ($sumVals as $i => $v) {
        echo '<td style="background:' . $sumHead[$i][1] . '; color:' . $white . '; text-align:center; border:1px solid ' . $sumHead[$i][1] . '; padding:6px 4px; font-size:14pt; font-weight:bold;">' . $v . '</td>';
    }
    echo '</tr></table>';

    // ---- Daftar Aset (dark blue header, white bold text, alternating rows, centered number columns) ----
    echo $section('DAFTAR ASET');
    echo '<table style="width:100%;"><colgroup>'
        . '<col style="width:36px"><col style="width:110px"><col style="width:110px"><col style="width:130px">'
        . '<col style="width:120px"><col style="width:130px"><col style="width:90px"><col style="width:100px">'
        . '</colgroup><thead><tr>'
        . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:center; border:1px solid ' . $blue . '; padding:6px 6px;">No</th>'
        . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:left; border:1px solid ' . $blue . '; padding:6px 6px;">Asset Number</th>'
        . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:left; border:1px solid ' . $blue . '; padding:6px 6px;">Serial Number</th>'
        . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:left; border:1px solid ' . $blue . '; padding:6px 6px;">Brand</th>'
        . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:left; border:1px solid ' . $blue . '; padding:6px 6px;">Area</th>'
        . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:left; border:1px solid ' . $blue . '; padding:6px 6px;">Department</th>'
        . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:center; border:1px solid ' . $blue . '; padding:6px 6px;">Condition</th>'
        . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:center; border:1px solid ' . $blue . '; padding:6px 6px;">Status</th>'
        . '</tr></thead><tbody>';
    if (count($assets) === 0) {
        echo '<tr><td colspan="8" style="text-align:center; border:1px solid ' . $border . '; padding:8px;">Tidak ada data aset.</td></tr>';
    } else {
        $no = 1;
        foreach ($assets as $i => $a) {
            $rowBg = ($i % 2 === 1) ? ' style="background:' . $altRow . ';"' : '';
            echo '<tr' . $rowBg . '>'
                . '<td style="text-align:center; border:1px solid ' . $border . '; padding:5px 6px;">' . $no++ . '</td>'
                . '<td style="border:1px solid ' . $border . '; padding:5px 6px;">' . $esc($a['asset_number']) . '</td>'
                . '<td style="border:1px solid ' . $border . '; padding:5px 6px;">' . $esc($a['serial_number']) . '</td>'
                . '<td style="border:1px solid ' . $border . '; padding:5px 6px;">' . $esc($a['nama_barang']) . '</td>'
                . '<td style="border:1px solid ' . $border . '; padding:5px 6px;">' . $esc($a['area']) . '</td>'
                . '<td style="border:1px solid ' . $border . '; padding:5px 6px;">' . $esc($a['location_note']) . '</td>'
                . '<td style="border:1px solid ' . $border . '; padding:5px 6px;' . $badgeStyle($condBadges, $a['condition']) . '">' . $esc($a['condition']) . '</td>'
                . '<td style="border:1px solid ' . $border . '; padding:5px 6px;' . $badgeStyle($statusBadges, $a['stocktaking_status']) . '">' . $esc($a['stocktaking_status']) . '</td>'
                . '</tr>';
        }
    }
    echo '</tbody></table>';

    // ---- Footer ----
    echo '<table style="width:100%;">'
        . '<tr><td colspan="8" style="border:none; text-align:center; color:' . $muted . '; font-size:9pt; padding:14px 4px 2px 4px;">Generated automatically by</td></tr>'
        . '<tr><td colspan="8" style="border:none; text-align:center; color:' . $muted . '; font-size:9pt; padding:1px 4px;">Asset Management Information System</td></tr>'
        . '<tr><td colspan="8" style="border:none; text-align:center; color:' . $muted . '; font-size:9pt; padding:1px 4px;">PT United Tractors Tbk</td></tr>'
        . '</table>';
    echo '</body></html>';
    $conn->close();
    exit;
}

// ==========================================
// PDF — generated directly, downloaded with
// an attachment header (no print dialog).
// ==========================================
$pdf = new PdfService(
    'REVIEW STOCKTAKING',
    ($sub['submission_code'] ?? ('STK-' . $sub['id'])) . '  -  PT United Tractors Tbk  -  Dicetak ' . date('d F Y')
);
$pdf->addPage();

$margin = $pdf->getMargin();
$y = $margin + 42;

// ---- Meta grid (label/value pairs, 2 columns) ----
$colW   = ($pdf->getPageWidth() - 2 * $margin) / 2; // ~257.6
$halfW  = $colW / 2;                                // ~128.8
$cellH  = 15;
$labelBg = [0.93, 0.93, 0.93];
$lineC   = [0.75, 0.75, 0.75];

$pdf->setFont(8.5, false);
$rowCount = (int)ceil(count($meta) / 2);
for ($r = 0; $r < $rowCount; $r++) {
    $rowH = $cellH;
    foreach (['left' => $meta[$r * 2] ?? null, 'right' => $meta[$r * 2 + 1] ?? null] as $side => $pair) {
        if ($pair === null) {
            continue;
        }
        $col = ($side === 'left') ? $margin : $margin + $colW;
        $pdf->setFont(8.5, true);
        $lh = $pdf->cell($col, $y, $halfW, $cellH, $pair[0], ['bg' => $labelBg, 'stroke' => $lineC, 'align' => 'left', 'color' => [0.13, 0.13, 0.13]]);
        $pdf->setFont(8.5, false);
        $vh = $pdf->cell($col + $halfW, $y, $halfW, $cellH, $pair[1], ['stroke' => $lineC, 'align' => 'left', 'color' => [0.13, 0.13, 0.13]]);
        $rowH = max($rowH, $lh, $vh);
    }
    $y += $rowH;
}
$y += 8;

// ---- Condition summary ----
$pdf->setFont(11, true);
$pdf->text($margin, $y, 'RINGKASAN KONDISI', [0.12, 0.35, 0.66]);
$y += 15;
$summaryRows = [[
    (int)$sub['total_assets'],
    (int)$sub['normal_count'],
    (int)$sub['broken_count'],
    (int)$sub['lost_count'],
    (int)$sub['pending_count'],
]];
$y = $pdf->table($margin, $y, [
    ['Total Assets', 100, 'center'],
    ['Normal', 100, 'center'],
    ['Broken', 100, 'center'],
    ['Lost', 100, 'center'],
    ['Pending', 100, 'center'],
], $summaryRows, ['font_size' => 9, 'row_height' => 16]);

// ---- Asset list ----
$y += 16;
$pdf->setFont(11, true);
$pdf->text($margin, $y, 'DAFTAR ASET', [0.12, 0.35, 0.66]);
$y += 15;

$assetRows = [];
foreach ($assets as $i => $a) {
    $assetRows[] = [
        $i + 1,
        $a['asset_number'] ?? '-',
        $a['serial_number'] ?? '-',
        $a['nama_barang'] ?? '-',
        $a['area'] ?? '-',
        $a['location_note'] ?? '-',
        $a['condition'] ?? '-',
        $a['stocktaking_status'] ?? '-',
    ];
}
if (empty($assetRows)) {
    $assetRows[] = ['-', '-', '-', '-', '-', '-', '-', '-'];
}

$pdf->table($margin, $y, [
    ['No', 22, 'center'],
    ['Asset Number', 68, 'left'],
    ['Serial Number', 68, 'left'],
    ['Brand', 88, 'left'],
    ['Area', 68, 'left'],
    ['Department', 78, 'left'],
    ['Condition', 50, 'center'],
    ['Status', 56, 'center'],
], $assetRows, ['font_size' => 8, 'row_height' => 13]);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="review_stocktaking_' . $id . '_' . date('Y-m-d') . '.pdf"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo $pdf->output();
$conn->close();
