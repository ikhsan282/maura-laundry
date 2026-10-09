<?php
/**
 * PDF generator self-check. Run: php tests/pdf_test.php
 * Verifies header, EOF, xref offsets (point to "N 0 obj"), string escaping, multipage.
 */
require __DIR__ . '/../includes/pdf.php';

$fail = 0;
function check(bool $ok, string $msg): void {
    global $fail;
    echo ($ok ? "PASS" : "FAIL") . " — {$msg}\n";
    if (!$ok) $fail++;
}

// --- Header / EOF ---
$pdf = (new PDFGenerator())->output();
check(str_starts_with($pdf, "%PDF-1.4\n"), 'header starts with %PDF-1.4');
check(str_ends_with($pdf, "%%EOF\n"), 'ends with %%EOF');

// --- Escaping ---
$g = new PDFGenerator();
$g->text('a(b)c\\d');
$out = $g->output();
check(str_contains($out, '(a\\(b\\)c\\\\d) Tj'), 'parentheses and backslash escaped');

// --- Multipage ---
$g = new PDFGenerator();
$g->text('title');
$rows = array_fill(0, 120, ['Row', '1000', '2000']); // forces page breaks
$g->table(['A', 'B', 'C'], $rows, [200, 150, 150]);
$g->table(['D', 'E'], [['x', 'y']], [250, 250]);
$out = $g->output();

preg_match('/\/Count (\d+)/', $out, $m);
$count = (int)($m[1] ?? 0);
check($count >= 2, "multipage: /Count = {$count} (>=2)");

// --- xref offsets point at real object headers ---
preg_match('/startxref\s+(\d+)/', $out, $sx);
$xref_pos = (int)$sx[1];
check(substr($out, $xref_pos, 4) === 'xref', 'startxref points at xref keyword');

preg_match('/xref\s+0 (\d+)\s+(.*?)trailer/s', $out, $tm);
$size = (int)$tm[1];
$lines = array_values(array_filter(explode("\n", $tm[2]), fn($l) => trim($l) !== ''));
$ok_offsets = (substr($lines[0], 11, 5) === '65535');
for ($i = 1; $i < $size && $i < count($lines); $i++) {
    $off = (int)substr($lines[$i], 0, 10);
    $expect = "{$i} 0 obj";
    if (substr($out, $off, strlen($expect)) !== $expect) { $ok_offsets = false; break; }
}
check($ok_offsets, "all xref offsets resolve to their 'N 0 obj' header (size={$size})");

// --- trailer Size matches xref count ---
preg_match('/trailer\s*<<\s*\/Size (\d+)/', $out, $sm);
check((int)$sm[1] === $size, 'trailer /Size matches xref entry count');

// --- empty table renders without error ---
$g = new PDFGenerator();
$g->table(['H1', 'H2'], [], [100, 100]);
check(str_contains($g->output(), '(H1) Tj'), 'empty table still renders its header');

echo $fail === 0 ? "\nALL PASS\n" : "\n{$fail} FAILURE(S)\n";
exit($fail === 0 ? 0 : 1);
