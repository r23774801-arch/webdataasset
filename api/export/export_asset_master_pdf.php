<?php
/**
 * export_asset_master_pdf.php — Asset Master Data PDF export.
 *
 * Exports ALL matching Asset IT + Asset GA records (the same filtered set
 * shown in the Asset Master table) as a directly-downloaded PDF. The current
 * search (?q=) and column filters (?filters[col]=value) are applied before
 * exporting. No print dialog, no window.print(), no redirect — the file is
 * served with an attachment header so the browser downloads it immediately.
 *
 * Uses the shared dependency-free PdfService (same engine as the other report
 * PDFs) with automatic page numbers and repeated header rows.
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
$rows  = ReportService::assetMasterRows($conn, $q, $filters);
$total = count($rows);

$pdf = new PdfService(
    'ASSET MASTER REPORT',
    'PT United Tractors Tbk  -  Generated: ' . date('d F Y H:i') . '  -  Total Records: ' . $total
);
$pdf->addPage();

$margin = $pdf->getMargin();
$y = $margin + 42;

$pdf->setFont(9, true);
$pdf->text($margin, $y, 'Daftar Seluruh Aset (IT & GA)', [0.12, 0.35, 0.66]);
$y += 16;

$pdf->setFont(7.5, false);

$headers = [
    ['No', 22, 'center'],
    ['Type', 30, 'center'],
    ['Asset Number', 64, 'left'],
    ['Asset Name', 70, 'left'],
    ['Asset Class', 46, 'left'],
    ['Serial Number', 58, 'left'],
    ['Brand', 44, 'left'],
    ['PIC', 40, 'left'],
    ['Area', 48, 'left'],
    ['Department', 50, 'left'],
    ['Utilization', 42, 'center'],
    ['Condition', 42, 'center'],
    ['Stocktaking Status', 55, 'center'],
    ['Date of Entry', 46, 'center'],
];

// Normalize widths to exactly fit the printable width.
$sum = 0;
foreach ($headers as $h) $sum += $h[1];
$scale = ($pdf->getPageWidth() - 2 * $margin) / $sum;
foreach ($headers as &$h) $h[1] = (float)round($h[1] * $scale, 1);
unset($h);

$rowsOut = [];
$no = 1;
foreach ($rows as $r) {
    $cond = trim((string)($r['kondisi'] ?? ''));
    if ($cond === '' || $cond === '-') $cond = 'Pending';
    $date = !empty($r['date_of_entry']) && $r['date_of_entry'] !== '0000-00-00'
        ? date('d/m/Y', strtotime($r['date_of_entry']))
        : '-';
    $rowsOut[] = [
        $no++,
        strtoupper(trim((string)($r['asset_type'] ?? '-'))),
        $r['asset_number'] ?? '-',
        $r['nama_barang'] ?? '-',
        $r['asset_class'] ?? '-',
        $r['serial_number'] ?? '-',
        $r['asset_class'] ?? '-',
        $r['pic'] ?? '-',
        $r['area'] ?? '-',
        $r['location_note'] ?? '-',
        $r['utilisasi'] ?? '-',
        $cond,
        $r['stocktaking_status'] ?? '-',
        $date,
    ];
}
if (empty($rowsOut)) {
    $rowsOut[] = array_fill(0, 14, '-');
}

$pdf->table($margin, $y, $headers, $rowsOut, [
    'font_size'  => 7.5,
    'row_height' => 13,
    'header_bg'  => [0.12, 0.35, 0.66],
    'header_fg'  => [1.0, 0.8, 0.06],
]);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="asset_master_' . date('Y-m-d') . '.pdf"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo $pdf->output();
$conn->close();
