<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

include 'koneksi.php';
require_once __DIR__ . '/app/bootstrap.php';

require_login();

// ==========================================
// Asset report PDF — direct download.
// Content mirrors the on-page "Ringkasan Aset
// per Lokasi (Area)" table (ReportService keeps
// the data source identical to the web report).
// ==========================================
$summary = ReportService::areaSummary($conn);

$pdf = new PdfService('LAPORAN REKAPITULASI ASET', 'PT United Tractors Tbk  -  Dicetak ' . date('d F Y'));
$pdf->addPage();

$y = $pdf->getMargin() + 44;
$pdf->setFont(11, true);
$pdf->text($pdf->getMargin(), $y, 'RINGKASAN ASET PER LOKASI (AREA)', [0.12, 0.35, 0.66]);
$y += 17;

$headers = [
    ['No', 24, 'center'],
    ['Lokasi (Area)', 110, 'left'],
    ['Total Aset', 44, 'center'],
    ['Normal', 38, 'center'],
    ['Pending', 42, 'center'],
    ['Broken', 42, 'center'],
    ['Lost', 38, 'center'],
    ['Transfer', 44, 'center'],
    ['Persentase Normal', 62, 'center'],
    ['Progress Stocktaking', 62, 'center'],
];

$rows = [];
$no = 1;
foreach ($summary as $s) {
    $total      = (int)$s['total'];
    $pctNormal  = $total > 0 ? number_format(($s['normal'] / $total) * 100, 1) : '0';
    $pctProgress = $total > 0 ? number_format(($s['stocktaking_done'] / $total) * 100, 1) : '0';
    $rows[] = [
        $no++,
        $s['area'],
        $total,
        $s['normal'],
        $s['pending'],
        $s['broken'],
        $s['lost'],
        $s['transfer'],
        $pctNormal . '%',
        $pctProgress . '%',
    ];
}
if (empty($rows)) {
    $rows[] = ['-', 'Belum ada data', '-', '-', '-', '-', '-', '-', '-', '-'];
}

$pdf->table($pdf->getMargin(), $y, $headers, $rows, ['font_size' => 8, 'row_height' => 14]);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="laporan_aset_' . date('Y-m-d') . '.pdf"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo $pdf->output();
$conn->close();
