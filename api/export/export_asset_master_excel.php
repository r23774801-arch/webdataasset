<?php
/**
 * export_asset_master_excel.php — Asset Master Data Excel export.
 *
 * Exports ALL matching Asset IT + Asset GA records (the same filtered set
 * shown in the Asset Master table) as an immediate .xls download. The
 * current search (?q=) and column filters (?filters[col]=value) are applied
 * before exporting. No redirect, no blank page — served with an attachment
 * header so the browser starts the download immediately.
 *
 * Uses the same HTML-table .xls mechanism as the other Excel exports
 * (PhpSpreadsheet is not installed) with an enterprise layout.
 */
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../app/bootstrap.php';

require_login();

$q     = trim((string)($_GET['q'] ?? ''));
$filters = [];
if (!empty($_GET['filters']) && is_array($_GET['filters'])) {
    foreach ($_GET['filters'] as $key => $val) {
        if (is_array($val)) {
            $filters[$key] = (string)($val['value'] ?? '');
        } else {
            $filters[$key] = (string)$val;
        }
    }
}

// Export the entire filtered set (no pagination limit).
$rows = ReportService::assetMasterRows($conn, $q, $filters);

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="asset_master_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

$esc = function ($v) {
    return htmlspecialchars((string)($v ?? '-'), ENT_QUOTES, 'UTF-8');
};

// Enterprise palette
$blue    = '#1E5AA8';
$yellow  = '#FFCC00';    $white   = '#FFFFFF';
    $border  = '#B9C6D6';
$altRow  = '#EEF4FB';
$muted   = '#7F8C8D';

// Condition badge (background / text)
$condBadges = [
    'normal'   => ['#C6EFCE', '#1F5B2E'],
    'broken'   => ['#FDE9D9', '#9C4A00'],
    'lost'     => ['#FBDDDD', '#A10000'],
    'transfer' => ['#DCE9FA', '#1E5AA8'],
    'pending'  => ['#E8E8E8', '#5D6D7E'],
];
// Status badge
$statusBadges = [
    'stocktaked' => ['#C6EFCE', '#1F5B2E'],
    'pending'    => ['#FDE9D9', '#9C4A00'],
    'rejected'   => ['#FBDDDD', '#A10000'],
];
$badgeStyle = function ($badges, $key) {
    $k = strtolower(trim((string)$key));
    if (!isset($badges[$k])) {
        return 'background:#E8E8E8; color:#5D6D7E; font-weight:bold; text-align:center;';
    }
    return 'background:' . $badges[$k][0] . '; color:' . $badges[$k][1] . '; font-weight:bold; text-align:center;';
};

// Row numbers for AutoFilter / freeze / print titles.
// 4 merged title rows, then the asset table header = header at row 5.
$assetHeaderRow = 5;
$assetLastRow   = $assetHeaderRow + max(1, count($rows));

// UTF-8 BOM so Excel decodes non-ASCII text (Indonesian names) correctly.
echo "\xEF\xBB\xBF";
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="utf-8">';
echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
echo '<x:Name>Asset Master Report</x:Name>';
echo '<x:WorksheetOptions>';
echo '<x:Print><x:ValidPrinterInfo/><x:PaperSizeIndex>9</x:PaperSizeIndex>'
    . '<x:HorizontalResolution>600</x:HorizontalResolution><x:VerticalResolution>600</x:VerticalResolution></x:Print>';
echo '<x:PageSetup><x:Orientation>Landscape</x:Orientation><x:FitToPage/><x:Scale>100</x:Scale></x:PageSetup>';
echo '<x:FitWidth>1</x:FitWidth><x:FitHeight>0</x:FitHeight>';
echo '<x:PrintTitleRows>' . $assetHeaderRow . ':' . $assetHeaderRow . '</x:PrintTitleRows>';
echo '<x:CenterHorizontal/>';
echo '<x:AutoFilter x:Range="R' . $assetHeaderRow . 'C1:R' . $assetLastRow . 'C14"/>';
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

// ---- Enterprise header block (merged, dark blue, white text) ----
echo '<table style="width:100%;">'
    . '<tr><td colspan="14" style="background:' . $blue . '; color:' . $white . '; text-align:center; font-size:12pt; font-weight:bold; letter-spacing:2px; padding:8px 4px 2px 4px; border:none;">PT UNITED TRACTORS TBK</td></tr>'
    . '<tr><td colspan="14" style="background:' . $blue . '; color:' . $white . '; text-align:center; font-size:18pt; font-weight:bold; padding:2px 4px; border:none;">ASSET MASTER REPORT</td></tr>'
    . '<tr><td colspan="14" style="background:' . $blue . '; color:' . $yellow . '; text-align:center; font-size:12pt; font-weight:bold; padding:2px 4px; border:none;">Asset IT &amp; Asset GA</td></tr>'
    . '<tr><td colspan="14" style="background:' . $blue . '; color:#DCE9FA; text-align:center; font-size:9pt; padding:2px 4px 8px 4px; border:none;">Generated: ' . date('d F Y H:i') . ' &nbsp;|&nbsp; Total Records: ' . count($rows) . '</td></tr>'
    . '</table>';

// ---- Asset Master table (13 columns) ----
echo '<table style="width:100%;"><colgroup>'
    . '<col style="width:36px"><col style="width:60px"><col style="width:110px"><col style="width:140px">'
    . '<col style="width:90px"><col style="width:100px"><col style="width:90px"><col style="width:80px">'
    . '<col style="width:110px"><col style="width:110px"><col style="width:70px">'
    . '<col style="width:80px"><col style="width:100px"><col style="width:90px">'
    . '</colgroup><thead><tr>'
    . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:center; border:1px solid ' . $blue . '; padding:6px 6px;">No</th>'
    . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:center; border:1px solid ' . $blue . '; padding:6px 6px;">Asset Type</th>'
    . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:left; border:1px solid ' . $blue . '; padding:6px 6px;">Asset Number</th>'
    . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:left; border:1px solid ' . $blue . '; padding:6px 6px;">Asset Name</th>'
    . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:left; border:1px solid ' . $blue . '; padding:6px 6px;">Asset Class</th>'
    . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:left; border:1px solid ' . $blue . '; padding:6px 6px;">Serial Number</th>'
    . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:left; border:1px solid ' . $blue . '; padding:6px 6px;">Brand</th>'
    . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:left; border:1px solid ' . $blue . '; padding:6px 6px;">PIC</th>'
    . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:left; border:1px solid ' . $blue . '; padding:6px 6px;">Area</th>'
    . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:left; border:1px solid ' . $blue . '; padding:6px 6px;">Department</th>'
    . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:center; border:1px solid ' . $blue . '; padding:6px 6px;">Utilization</th>'
    . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:center; border:1px solid ' . $blue . '; padding:6px 6px;">Condition</th>'
    . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:center; border:1px solid ' . $blue . '; padding:6px 6px;">Stocktaking Status</th>'
    . '<th style="background:' . $blue . '; color:' . $white . '; font-weight:bold; text-align:left; border:1px solid ' . $blue . '; padding:6px 6px;">Date of Entry</th>'
    . '</tr></thead><tbody>';

$typeBadge = function ($type) use ($blue, $white, $esc) {
    $bg = strtoupper(trim((string)$type)) === 'IT' ? $blue : '#0D9488';
    return '<span style="background:' . $bg . '; color:' . $white . '; font-weight:bold; padding:3px 8px; border-radius:999px; font-size:9pt;">' . $esc(strtoupper(trim((string)$type))) . '</span>';
};

if (count($rows) === 0) {
    echo '<tr><td colspan="14" style="text-align:center; border:1px solid ' . $border . '; padding:10px;">Tidak ada data aset.</td></tr>';
} else {
    $no = 1;
    foreach ($rows as $i => $r) {
        $rowBg = ($i % 2 === 1) ? ' style="background:' . $altRow . ';"' : '';
        $date  = !empty($r['date_of_entry']) && $r['date_of_entry'] !== '0000-00-00'
            ? date('d/m/Y', strtotime($r['date_of_entry']))
            : '-';
        $cond = trim((string)($r['kondisi'] ?? ''));
        if ($cond === '' || $cond === '-') $cond = 'Pending';
        echo '<tr' . $rowBg . '>'
            . '<td style="text-align:center; border:1px solid ' . $border . '; padding:5px 6px;">' . $no++ . '</td>'
            . '<td style="text-align:center; border:1px solid ' . $border . '; padding:5px 6px;">' . $typeBadge($r['asset_type'] ?? '') . '</td>'
            . '<td style="border:1px solid ' . $border . '; padding:5px 6px;">' . $esc($r['asset_number'] ?? '-') . '</td>'
            . '<td style="border:1px solid ' . $border . '; padding:5px 6px;">' . $esc($r['nama_barang'] ?? '-') . '</td>'
            . '<td style="border:1px solid ' . $border . '; padding:5px 6px;">' . $esc($r['asset_class'] ?? '-') . '</td>'
            . '<td style="border:1px solid ' . $border . '; padding:5px 6px;">' . $esc($r['serial_number'] ?? '-') . '</td>'
            . '<td style="border:1px solid ' . $border . '; padding:5px 6px;">' . $esc($r['asset_class'] ?? '-') . '</td>'
            . '<td style="border:1px solid ' . $border . '; padding:5px 6px;">' . $esc($r['pic'] ?? '-') . '</td>'
            . '<td style="border:1px solid ' . $border . '; padding:5px 6px;">' . $esc($r['area'] ?? '-') . '</td>'
            . '<td style="border:1px solid ' . $border . '; padding:5px 6px;">' . $esc($r['location_note'] ?? '-') . '</td>'
            . '<td style="text-align:center; border:1px solid ' . $border . '; padding:5px 6px;">' . $esc($r['utilisasi'] ?? '-') . '</td>'
            . '<td style="border:1px solid ' . $border . '; padding:5px 6px;' . $badgeStyle($condBadges, $cond) . '">' . $esc($cond) . '</td>'
            . '<td style="border:1px solid ' . $border . '; padding:5px 6px;' . $badgeStyle($statusBadges, $r['stocktaking_status'] ?? '') . '">' . $esc($r['stocktaking_status'] ?? '-') . '</td>'
            . '<td style="border:1px solid ' . $border . '; padding:5px 6px;">' . $date . '</td>'
            . '</tr>';
    }
}
echo '</tbody></table>';

// ---- Footer ----
echo '<table style="width:100%;">'
    . '<tr><td colspan="14" style="border:none; text-align:center; color:' . $muted . '; font-size:9pt; padding:14px 4px 2px 4px;">Generated automatically by</td></tr>'
    . '<tr><td colspan="14" style="border:none; text-align:center; color:' . $muted . '; font-size:9pt; padding:1px 4px;">Asset Management Information System</td></tr>'
    . '<tr><td colspan="14" style="border:none; text-align:center; color:' . $muted . '; font-size:9pt; padding:1px 4px;">PT United Tractors Tbk</td></tr>'
    . '</table>';
echo '</body></html>';
$conn->close();
