<?php
/**
 * Minimal PDF 1.4 generator: tables, automatic page breaks, Helvetica, escaping.
 * Usage: $pdf = new PDFGenerator(); $pdf->text(...); $pdf->table(...); $pdf->download('x.pdf');
 * ponytail: text width is estimated (strlen-based), not from Helvetica AFM metrics.
 *           Upgrade path: add a width table if right/center alignment must be exact.
 * ponytail: non-ASCII bytes are written raw; Helvetica only covers WinAnsi, so
 *           pass UTF-8 through utf8_decode()/iconv before calling if needed.
 */

class PDFGenerator {
    private array $pages = [];
    private int $idx = 0;
    private float $y = 0;
    private int $page_w = 612;
    private int $page_h = 792;
    private int $margin = 40;

    public function __construct() {
        $this->new_page();
    }

    private function new_page(): void {
        $this->pages[] = '';
        $this->idx = count($this->pages) - 1;
        $this->y = $this->page_h - $this->margin;
    }

    private function emit(string $op): void {
        $this->pages[$this->idx] .= $op . "\n";
    }

    private function esc(string $s): string {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '\\r', '\\n'], $s);
    }

    private function text_at(float $x, float $y, string $s, int $size = 9): void {
        $this->emit("BT /F1 {$size} Tf {$x} {$y} Td (" . $this->esc($s) . ") Tj ET");
    }

    private function line(float $x1, float $y1, float $x2, float $y2): void {
        $this->emit("{$x1} {$y1} m {$x2} {$y2} l S");
    }

    private function cell_x(float $x, float $w, string $align, string $s): float {
        $tw = strlen($s) * 5; // ponytail: estimate, see header
        if ($align === 'right')  return $x + $w - 3 - $tw;
        if ($align === 'center') return $x + ($w - $tw) / 2;
        return $x + 3;
    }

    public function text(string $s, int $size = 10): void {
        if ($this->y - ($size + 4) < $this->margin) $this->new_page();
        $this->text_at($this->margin, $this->y, $s, $size);
        $this->y -= $size + 4;
    }

    public function table(array $headers, array $rows, array $widths, array $aligns = []): void {
        $row_h = 18; $head_h = 22;
        $total = array_sum($widths);
        $x0 = $this->margin;

        $draw_head = function () use ($headers, $widths, $aligns, $x0, $total, $head_h) {
            $top = $this->y;
            $x = $x0;
            foreach ($headers as $i => $h) {
                $this->text_at($this->cell_x($x, $widths[$i], $aligns[$i] ?? 'left', $h), $top - 15, $h, 10);
                $x += $widths[$i];
            }
            $this->line($x0, $top, $x0 + $total, $top);
            $this->line($x0, $top - $head_h, $x0 + $total, $top - $head_h);
            $this->y = $top - $head_h;
        };

        if ($this->y - $head_h - $row_h < $this->margin) $this->new_page();
        $top = $this->y;
        $draw_head();

        foreach ($rows as $row) {
            if ($this->y - $row_h < $this->margin) {
                $this->new_page();
                $top = $this->y;
                $draw_head();
            }
            $x = $x0;
            foreach ($row as $i => $cell) {
                $s = (string)$cell;
                $this->text_at($this->cell_x($x, $widths[$i], $aligns[$i] ?? 'left', $s), $this->y - 13, $s, 9);
                $x += $widths[$i];
            }
            $this->y -= $row_h;
            $this->line($x0, $this->y, $x0 + $total, $this->y);
        }

        // Vertical rules span from table top to bottom on the last page the table reached.
        $x = $x0;
        foreach ($widths as $w) { $this->line($x, $top, $x, $this->y); $x += $w; }
        $this->line($x, $top, $x, $this->y);
        $this->y -= 12;
    }

    public function output(): string {
        $n = count($this->pages);
        $objs = [];
        $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $kids = [];
        for ($i = 0; $i < $n; $i++) $kids[] = (3 + $i * 2) . ' 0 R';
        $objs[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count {$n} >>";
        foreach ($this->pages as $i => $content) {
            $p = 3 + $i * 2;
            $c = $p + 1;
            $objs[$p] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->page_w} {$this->page_h}] "
                . "/Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> >> "
                . "/Contents {$c} 0 R >>";
            $objs[$c] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }
        $size = count($objs) + 1;
        $xref = strlen($pdf);
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
        return $pdf;
    }

    public function download(string $filename): void {
        $ascii = preg_replace('/[^a-zA-Z0-9_.\-]/', '_', $filename);
        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($filename));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $this->output();
    }
}
