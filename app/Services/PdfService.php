<?php
/**
 * PdfService — minimal, dependency-free PDF generator (PDF 1.4).
 *
 * Built only on PHP primitives (no external libraries, no composer packages).
 * It is deliberately small and aimed at simple enterprise reports:
 * document header/footer, section titles, paragraphs, and bordered tables
 * with wrapped text and automatic page breaks.
 *
 * Coordinate system: top-left origin, units in points (72 pt = 1 inch).
 * Default page: A4 portrait (595.28 x 841.89 pt).
 */
class PdfService
{
    /** @var float Page width in points (A4). */
    private $pageW = 595.28;

    /** @var float Page height in points (A4). */
    private $pageH = 841.89;

    /** @var float Uniform page margin in points. */
    private $margin = 40.0;

    /** @var array<int,string> Raw content-stream operators per page. */
    private $pages = [];

    /** @var int Index of the page currently being written. */
    private $current = -1;

    /** @var float Active font size in points. */
    private $fontSize = 10;

    /** @var bool Active bold flag. */
    private $bold = false;

    /** @var string Document title drawn in the header of every page. */
    private $title = '';

    /** @var string Secondary line drawn under the title. */
    private $subtitle = '';

    /** Average glyph advance factor used for width estimation (Helvetica). */
    private const ADV = 0.50;
    private const ADV_BOLD = 0.55;

    public function __construct(string $title = '', string $subtitle = '', float $margin = 40.0)
    {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->margin = $margin;
    }

    public function getPageWidth(): float  { return $this->pageW; }
    public function getPageHeight(): float { return $this->pageH; }
    public function getMargin(): float     { return $this->margin; }

    // ---------------------------------------------------------------
    // Page / font management
    // ---------------------------------------------------------------

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->current = count($this->pages) - 1;
    }

    public function setFont(float $size, bool $bold = false): void
    {
        $this->fontSize = max(4, $size);
        $this->bold = $bold;
    }

    private function content(): string
    {
        return $this->pages[$this->current] ?? '';
    }

    // ---------------------------------------------------------------
    // Low-level helpers
    // ---------------------------------------------------------------

    /** Convert UTF-8 input to a WinAnsi/cp1252-safe string. */
    private function normalize(string $s): string
    {
        if (function_exists('iconv')) {
            $out = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
            if ($out !== false) {
                return $out;
            }
        }
        return preg_replace('/[^\x20-\x7E]/', '?', $s) ?? '';
    }

    private function escape(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    private function fillOp(?array $c): string
    {
        if (!$c) {
            return '';
        }
        return sprintf('%.3f %.3f %.3f rg ', $c[0] ?? 0, $c[1] ?? 0, $c[2] ?? 0);
    }

    private function strokeOp(?array $c): string
    {
        if (!$c) {
            return '';
        }
        return sprintf('%.3f %.3f %.3f RG ', $c[0] ?? 0, $c[1] ?? 0, $c[2] ?? 0);
    }

    /** Estimated width of a string at the active font (points). */
    public function textWidth(string $s): float
    {
        $factor = $this->bold ? self::ADV_BOLD : self::ADV;
        return strlen($this->normalize($s)) * $this->fontSize * $factor;
    }

    /** Wrap text into lines that fit within $maxWidth points. */
    public function wrapText(string $s, float $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($s)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($this->textWidth($candidate) <= $maxWidth || $line === '') {
                $line = $candidate;
            } else {
                $lines[] = $line;
                $line = $word;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        if (count($lines) === 0) {
            $lines[] = '';
        }
        return $lines;
    }

    // ---------------------------------------------------------------
    // Drawing primitives
    // ---------------------------------------------------------------

    /** Draw a single line of text. (x, y) is the top-left of the line. */
    public function text(float $x, float $y, string $s, ?array $color = null, string $align = 'left'): void
    {
        $s = $this->normalize((string)$s);
        if ($align === 'center') {
            $x -= $this->textWidth($s) / 2;
        } elseif ($align === 'right') {
            $x -= $this->textWidth($s);
        }
        $baseline = $this->pageH - $y - $this->fontSize;
        $op = sprintf('/%s %.2f Tf BT %.2f %.2f Td (%s) Tj ET',
            $this->bold ? 'F2' : 'F1',
            $this->fontSize,
            $x,
            $baseline,
            $this->escape($s)
        );
        $this->pages[$this->current] = $this->content() . $this->fillOp($color ?: [0.13, 0.13, 0.13]) . $op . "\n";
    }

    /** Draw a straight line between two points. */
    public function line(float $x1, float $y1, float $x2, float $y2, ?array $color = null, float $width = 0.6): void
    {
        $op = sprintf('%.2f w %.2f %.2f m %.2f %.2f l S', $width, $x1, $this->pageH - $y1, $x2, $this->pageH - $y2);
        $this->pages[$this->current] = $this->content() . $this->strokeOp($color ?: [0.5, 0.5, 0.5]) . $op . "\n";
    }

    /** Draw a rectangle, optionally filled and/or stroked. */
    public function rect(float $x, float $y, float $w, float $h, ?array $fill = null, ?array $stroke = null, float $lineWidth = 0.4): void
    {
        $op = sprintf('%.2f %.2f %.2f %.2f re', $x, $this->pageH - $y - $h, $w, $h);
        if ($fill !== null && $stroke !== null) {
            $op .= ' B';
            $prefix = $this->fillOp($fill) . $this->strokeOp($stroke) . sprintf('%.2f w ', $lineWidth);
        } elseif ($fill !== null) {
            $op .= ' f';
            $prefix = $this->fillOp($fill);
        } elseif ($stroke !== null) {
            $op .= ' S';
            $prefix = $this->strokeOp($stroke) . sprintf('%.2f w ', $lineWidth);
        } else {
            $op .= ' n';
            $prefix = '';
        }
        $this->pages[$this->current] = $this->content() . $prefix . $op . "\n";
    }

    /**
     * Draw a bordered table cell with optional background and word wrapping.
     * Returns the height actually used (may exceed $h when text wraps).
     */
    public function cell(float $x, float $y, float $w, float $h, string $text, array $opts = []): float
    {
        $padding = $opts['padding'] ?? 4;
        $bg      = $opts['bg'] ?? null;
        $stroke  = $opts['stroke'] ?? null;
        $color   = $opts['color'] ?? [0.13, 0.13, 0.13];
        $align   = $opts['align'] ?? 'left';

        $innerW = max(2, $w - 2 * $padding);
        $lines  = $this->wrapText($text, $innerW);
        $lineH  = $this->fontSize * 1.3;
        $rowH   = max($h, count($lines) * $lineH + 2 * $padding);

        if ($bg !== null) {
            $this->rect($x, $y, $w, $rowH, $bg);
        }
        if ($stroke !== null) {
            $this->rect($x, $y, $w, $rowH, null, $stroke, 0.3);
        }

        if ($align === 'center') {
            $tx = $x + $w / 2;
        } elseif ($align === 'right') {
            $tx = $x + $w - $padding;
        } else {
            $tx = $x + $padding;
        }

        $ty = $y + $padding;
        foreach ($lines as $line) {
            $this->text($tx, $ty, $line, $color, $align);
            $ty += $lineH;
        }
        return $rowH;
    }

    /** Height a cell would need for the given text at the active font. */
    public function cellHeight(float $w, string $text, array $opts = []): float
    {
        $padding = $opts['padding'] ?? 4;
        $innerW  = max(2, $w - 2 * $padding);
        $lines   = count($this->wrapText($text, $innerW));
        return $lines * $this->fontSize * 1.3 + 2 * $padding;
    }

    /**
     * Render a full table with a styled header row, zebra striping, wrapped
     * cells and automatic page breaks (the header row is repeated on each
     * new page). $headers: [ [label, width, align], ... ]; $rows: array of
     * arrays aligned with $headers. Returns the Y position after the table.
     */
    public function table(float $x, float $y, array $headers, array $rows, array $opts = []): float
    {
        $fontSize  = $opts['font_size']  ?? 8.5;
        $headerBg  = $opts['header_bg']  ?? [0.12, 0.35, 0.66];
        $headerFg  = $opts['header_fg']  ?? [1.0, 0.8, 0.06];
        $zebraBg   = $opts['zebra']      ?? [0.96, 0.96, 0.96];
        $lineColor = $opts['line_color'] ?? [0.55, 0.55, 0.55];
        $pad       = $opts['padding']    ?? 4;
        $minRowH   = $opts['row_height'] ?? 15;
        $bottomLimit = $this->pageH - $this->margin - 34;

        $colXs = [];
        $cx = $x;
        foreach ($headers as $col) {
            $colXs[] = $cx;
            $cx += $col[1];
        }

        $yPos = $y;

        $drawHeaderRow = function () use (&$yPos, $headers, $colXs, $fontSize, $headerBg, $headerFg, $lineColor, $pad, $minRowH): void {
            $this->setFont($fontSize, true);
            $h = $minRowH;
            foreach ($headers as $i => $col) {
                $h = max($h, $this->cellHeight($col[1], (string)$col[0], ['padding' => $pad]));
            }
            foreach ($headers as $i => $col) {
                $this->cell($colXs[$i], $yPos, $col[1], $h, (string)$col[0], [
                    'padding' => $pad,
                    'bg'      => $headerBg,
                    'stroke'  => $lineColor,
                    'color'   => $headerFg,
                    'align'   => $col[2] ?? 'left',
                ]);
            }
            $yPos += $h;
            $this->setFont($fontSize, false);
        };

        $drawHeaderRow();

        $idx = 0;
        foreach ($rows as $row) {
            $this->setFont($fontSize, false);
            $rh = $minRowH;
            foreach ($headers as $i => $col) {
                $rh = max($rh, $this->cellHeight($col[1], (string)($row[$i] ?? ''), ['padding' => $pad]));
            }
            if ($yPos + $rh > $bottomLimit) {
                $this->addPage();
                $yPos = $this->margin;
                $drawHeaderRow();
            }
            $bg = ($idx % 2 === 1) ? $zebraBg : null;
            foreach ($headers as $i => $col) {
                $this->cell($colXs[$i], $yPos, $col[1], $rh, (string)($row[$i] ?? ''), [
                    'padding' => $pad,
                    'bg'      => $bg,
                    'stroke'  => $lineColor,
                    'align'   => $col[2] ?? 'left',
                ]);
            }
            $yPos += $rh;
            $idx++;
        }
        return $yPos;
    }

    // ---------------------------------------------------------------
    // Page decoration (header + footer) applied at output time
    // ---------------------------------------------------------------

    private function decorate(string $content, int $pageNum, int $totalPages): string
    {
        $out = '';
        // Header title + subtitle
        $titleBaseline = $this->pageH - $this->margin - 12;
        $out .= '/F2 12 Tf BT ' . sprintf('%.2f %.2f Td', $this->margin, $titleBaseline)
              . ' (' . $this->escape($this->normalize($this->title)) . ') Tj ET';
        if ($this->subtitle !== '') {
            $out .= '/F1 8.5 Tf BT ' . sprintf('%.2f %.2f Td', $this->margin, $titleBaseline - 12)
                  . ' (' . $this->escape($this->normalize($this->subtitle)) . ') Tj ET';
        }
        // Blue rule under the header
        $ruleY = $this->pageH - $this->margin - 30;
        $out .= sprintf('0.118 0.353 0.4 RG 1 w %.2f %.2f m %.2f %.2f l S', $this->margin, $ruleY, $this->pageW - $this->margin, $ruleY);
        // Footer: page number
        $footerBaseline = 22;
        $out .= '/F1 8.5 Tf BT ' . sprintf('%.2f %.2f Td', $this->pageW / 2 - 16, $footerBaseline)
              . ' (Hal. ' . $pageNum . ' / ' . $totalPages . ') Tj ET';
        return $out . "\n" . $content;
    }

    // ---------------------------------------------------------------
    // Output assembly
    // ---------------------------------------------------------------

    /** Return the complete PDF document as a string. */
    public function output(): string
    {
        if ($this->current < 0) {
            $this->addPage();
        }
        $total = count($this->pages);

        $decorated = [];
        foreach ($this->pages as $i => $content) {
            $decorated[] = $this->decorate($content, $i + 1, $total);
        }

        $fontObjF1 = $total + 3;
        $fontObjF2 = $total + 4;

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $total; $i++) {
            $kids[] = (3 + $i) . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $total . ' >>';

        for ($i = 0; $i < $total; $i++) {
            $pageObj    = 3 + $i;
            $contentObj = $fontObjF2 + 1 + $i;
            $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $this->pageW . ' ' . $this->pageH . '] '
                . '/Resources << /Font << /F1 ' . $fontObjF1 . ' 0 R /F2 ' . $fontObjF2 . ' 0 R >> >> '
                . '/Contents ' . $contentObj . ' 0 R >>';
            $objects[$contentObj] = $decorated[$i];
        }

        $objects[$fontObjF1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontObjF2] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $infoObj = $fontObjF2 + 1 + $total;
        $objects[$infoObj] = '<< /Title (' . $this->escape($this->normalize($this->title)) . ') '
            . '/Producer (PdfService) /CreationDate (D:' . date('YmdHis') . ') >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $isStream = ($num >= $fontObjF2 + 1 && $num <= $fontObjF2 + $total);
            $pdf .= $num . " 0 obj\n";
            if ($isStream) {
                $pdf .= '<< /Length ' . strlen($body) . " >>\nstream\n" . $body . "\nendstream\n";
            } else {
                $pdf .= $body . "\n";
            }
            $pdf .= "endobj\n";
        }

        $xrefPos = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($num = 1; $num <= count($objects); $num++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$num]);
        }
        $pdf .= "trailer\n<< /Size $count /Root 1 0 R /Info $infoObj 0 R >>\nstartxref\n$xrefPos\n%%EOF";

        return $pdf;
    }
}
