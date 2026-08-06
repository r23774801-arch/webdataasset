<?php
/**
 * Phase 4.22 — Master Employee Directory import.
 *
 * Admin-only endpoint. Accepts:
 *   - multipart file upload  (field "file")   → .csv / .tsv / .txt / .xlsx
 *   - raw pasted text        (JSON { "data": "..." }) → tab/comma/semicolon separated
 *
 * Behaviour:
 *   - New rows are inserted.
 *   - Rows whose NRP already exists are updated (never duplicated).
 *   - Column mapping is header-aware (NRP / NAME / EMAIL, case-insensitive)
 *     and falls back to positional order (NRP, NAME, EMAIL) when no header is found.
 *   - The imported file is never read again after import — registration always
 *     queries the master_employee table.
 */
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

include 'koneksi.php';
require_once __DIR__ . '/app/helpers.php';

require_admin();

// ---------------------------------------------------------------
// 1. Acquire raw rows (array of arrays of cells)
// ---------------------------------------------------------------
$rows = [];

if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
    $fileName = (string)($_FILES['file']['name'] ?? '');
    $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ((int)$_FILES['file']['size'] > 8 * 1024 * 1024) {
        json_response(['status' => 'error', 'message' => 'File terlalu besar (maks. 8 MB).']);
    }

    $tmp = $_FILES['file']['tmp_name'];

    if ($ext === 'xlsx') {
        $parsed = parse_xlsx_rows($tmp);
        if (isset($parsed['error'])) {
            json_response(['status' => 'error', 'message' => $parsed['error']]);
        }
        $rows = $parsed['rows'];
    } elseif (in_array($ext, ['csv', 'tsv', 'txt'], true)) {
        $content = file_get_contents($tmp);
        $rows    = parse_text_rows($content);
    } else {
        json_response(['status' => 'error', 'message' => 'Format file tidak didukung. Gunakan .csv, .tsv, .txt, atau .xlsx.']);
    }
} else {
    $body = json_decode(file_get_contents('php://input'), true);
    $text = trim((string)($body['data'] ?? ''));
    if ($text === '') {
        json_response(['status' => 'error', 'message' => 'Tidak ada data yang diterima. Upload file atau tempel data.']);
    }
    $rows = parse_text_rows($text);
}

if (count($rows) > 100000) {
    json_response(['status' => 'error', 'message' => 'Terlalu banyak baris (maks. 100.000).']);
}

// ---------------------------------------------------------------
// 2. Map columns → normalized employee records
// ---------------------------------------------------------------
$employees = normalize_employee_rows($rows);

if (count($employees) === 0) {
    json_response(['status' => 'error', 'message' => 'Tidak ada baris data valid (NRP + Nama) yang ditemukan.']);
}

// ---------------------------------------------------------------
// 3. Upsert into master_employee
// ---------------------------------------------------------------
$stmt = $conn->prepare(
    "INSERT INTO master_employee (nrp, employee_name, email)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE employee_name = VALUES(employee_name), email = VALUES(email)"
);

if (!$stmt) {
    json_response(['status' => 'error', 'message' => 'Gagal menyiapkan query: ' . $conn->error]);
}

$inserted = 0;
$updated  = 0;
$skipped  = 0;

$conn->begin_transaction();
try {
    foreach ($employees as $emp) {
        $stmt->bind_param('sss', $emp['nrp'], $emp['name'], $emp['email']);
        if (!$stmt->execute()) {
            $skipped++;
            continue;
        }
        if ($conn->affected_rows === 1) {
            $inserted++;
        } elseif ($conn->affected_rows === 2) {
            $updated++;
        } else {
            $skipped++; // no change
        }
    }
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    json_response(['status' => 'error', 'message' => 'Gagal menyimpan data: ' . $e->getMessage()]);
}

json_response([
    'status'  => 'success',
    'message' => 'Import berhasil.',
    'data'    => [
        'inserted' => $inserted,
        'updated'  => $updated,
        'skipped'  => $skipped,
        'total'    => count($employees),
    ],
]);

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

/**
 * Parse plain-text input (tab / comma / semicolon separated).
 * Strips BOM, normalizes CRLF, drops fully-empty lines.
 *
 * @return array<int, array<int, string>>
 */
function parse_text_rows(string $content): array
{
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // UTF-8 BOM
    $content = str_replace(["\r\n", "\r"], "\n", $content);

    $lines = array_values(array_filter(
        explode("\n", $content),
        static fn ($l) => trim($l) !== ''
    ));

    if (!$lines) {
        return [];
    }

    // Detect the delimiter from the first non-empty line.
    $first = $lines[0];
    $tabs  = substr_count($first, "\t");
    $commas = substr_count($first, ',');
    $semicolons = substr_count($first, ';');

    if ($tabs >= $commas && $tabs >= $semicolons) {
        $delimiter = "\t";
    } elseif ($semicolons > $commas) {
        $delimiter = ';';
    } else {
        $delimiter = ',';
    }

    $rows = [];
    foreach ($lines as $line) {
        $cells = str_getcsv($line, $delimiter);
        $cells = array_map('trim', $cells);
        $rows[] = $cells;
    }

    return $rows;
}

/**
 * Minimal .xlsx reader (shared strings + first worksheet) using
 * ZipArchive + SimpleXML — no third-party library required.
 *
 * @return array{rows?: array<int, array<int, string>>, error?: string}
 */
function parse_xlsx_rows(string $path): array
{
    if (!class_exists('ZipArchive')) {
        return ['error' => 'Ekstensi ZipArchive tidak tersedia di server. Simpan file sebagai .csv lalu coba lagi.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return ['error' => 'File .xlsx tidak dapat dibuka.'];
    }

    // Shared strings table
    $shared = [];
    $ssXml  = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = simplexml_load_string($ssXml);
        if ($ss !== false) {
            foreach ($ss->si as $si) {
                $text = '';
                foreach ($si->t as $t) {
                    $text .= (string)$t;
                }
                if ($text === '') {
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                }
                $shared[] = $text;
            }
        }
    }

    // First worksheet
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        $zip->close();
        return ['error' => 'Worksheet tidak ditemukan di file .xlsx.'];
    }

    $sheet = simplexml_load_string($sheetXml);
    $zip->close();

    if ($sheet === false) {
        return ['error' => 'Worksheet .xlsx tidak valid.'];
    }

    $rows = [];
    if (isset($sheet->sheetData->row)) {
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref  = (string)($c['r'] ?? '');
                $type = (string)($c['t'] ?? '');
                $val  = '';

                if ($type === 's' && isset($c->v)) {
                    $idx  = (int)(string)$c->v;
                    $val  = $shared[$idx] ?? '';
                } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                    $val = (string)$c->is->t;
                } elseif (isset($c->v)) {
                    $val = (string)$c->v;
                }

                // Column letter from the cell reference (e.g. "B" from "B2", "AA" from "AA5")
                $colLetter = preg_replace('/[0-9]+$/', '', $ref);
                $colIndex  = $colLetter !== '' ? xlsx_col_index($colLetter) : count($cells);
                $cells[$colIndex] = trim($val);
            }

            if ($cells) {
                ksort($cells);
                $rows[] = array_values($cells);
            }
        }
    }

    return ['rows' => $rows];
}

/**
 * Normalize a grid of cells into employee records.
 * Header-aware mapping (NRP / NAME / EMAIL), positional fallback otherwise.
 *
 * @param array<int, array<int, string>> $rows
 * @return array<int, array{nrp: string, name: string, email: string}>
 */
function normalize_employee_rows(array $rows): array
{
    if (!$rows) {
        return [];
    }

    $header = array_map(
        static fn ($c) => strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', (string)$c))),
        $rows[0]
    );

    $nrpIdx   = array_search('nrp', $header, true);
    $nameIdx  = null;
    $emailIdx = array_search('email', $header, true);
    if ($emailIdx === false) {
        $emailIdx = null;
    }

    if ($nrpIdx === false) {
        $nrpIdx = array_search('nip', $header, true);
    }

    $nameAliases = ['name', 'nama', 'employeename', 'namakaryawan', 'namapegawai', 'pegawai'];
    foreach ($header as $i => $col) {
        if (in_array($col, $nameAliases, true)) {
            $nameIdx = $i;
            break;
        }
    }

    $hasHeader = $nrpIdx !== false && $nameIdx !== null;
    $dataRows  = $hasHeader ? array_slice($rows, 1) : $rows;

    // Header-based mapping can be misaligned when the header has stray empty
    // cells (e.g. "NRP\t\tNAME" = 3 cells) while data rows have fewer cells
    // ("NRP\tNAME" = 2 cells). If the header map yields no valid rows but the
    // data exists, fall back to positional mapping (NRP, NAME, EMAIL).
    $employees = map_employee_rows($dataRows, $hasHeader, $nrpIdx, $nameIdx, $emailIdx);
    if ($hasHeader && count($employees) === 0 && count($dataRows) > 0) {
        $employees = map_employee_rows($dataRows, false, null, null, null);
    }

    return $employees;
}

/**
 * Map a grid of data rows into employee records using either header-based
 * column indexes or positional order.
 *
 * @param array<int, array<int, string>> $dataRows
 * @return array<int, array{nrp: string, name: string, email: string}>
 */
function map_employee_rows(array $dataRows, bool $hasHeader, ?int $nrpIdx, ?int $nameIdx, ?int $emailIdx): array
{
    $employees = [];
    foreach ($dataRows as $cells) {
        if ($hasHeader) {
            $nrp   = (string)($cells[$nrpIdx] ?? '');
            $name  = (string)($cells[$nameIdx] ?? '');
            $email = $emailIdx !== null ? (string)($cells[$emailIdx] ?? '') : '';
        } else {
            $nrp   = (string)($cells[0] ?? '');
            $name  = (string)($cells[1] ?? '');
            $email = (string)($cells[2] ?? '');
        }

        $nrp  = trim($nrp);
        $name = trim($name);

        if ($nrp === '' || $name === '') {
            continue;
        }

        $email = strtolower(trim($email));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }

        $employees[] = [
            'nrp'   => $nrp,
            'name'  => $name,
            'email' => $email,
        ];
    }

    return $employees;
}

/**
 * Convert an Excel column letter ("A", "B", ..., "AA", ...) to a 0-based index.
 */
function xlsx_col_index(string $letters): int
{
    $letters = strtoupper($letters);
    $index   = 0;
    $length  = strlen($letters);
    for ($i = 0; $i < $length; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }
    return $index - 1;
}
