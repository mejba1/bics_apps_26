<?php

namespace App\Support;

/**
 * Minimal Excel (.xlsx) writer — no library needed (ZipArchive + SpreadsheetML).
 * Several sheets; bold header row with a fill, frozen header, auto-filter, column widths from the content.
 * Numbers stay numbers; everything else is text (so registration numbers / phones keep their leading zeros as typed).
 *
 *   $x = new XlsxWriter();
 *   $x->sheet('Check-ins', ['Name', 'Time'], $rows, ['Name' => 30]);
 *   return $x->download('checkins.xlsx');
 */
class XlsxWriter
{
    private array $sheets = [];

    /** @param array $rows list of arrays (values in header order); $numeric: header names to keep as numbers */
    public function sheet(string $name, array $headers, iterable $rows, array $widths = [], array $numeric = [], bool $table = true): static
    {
        $name = mb_substr(preg_replace('#[\\\\/?*\[\]:]#', ' ', $name), 0, 31);
        $data = [];
        foreach ($rows as $r) $data[] = array_values((array) $r);
        $this->sheets[] = compact('name', 'headers', 'data', 'widths', 'numeric', 'table');
        return $this;
    }

    public function save(string $path): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('Cannot create the Excel file.');
        $n = count($this->sheets);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . implode('', array_map(fn ($i) => '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>', range(1, max(1, $n))))
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>BICS</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created></cp:coreProperties>');
        $sheetsXml = $rels = '';
        foreach ($this->sheets as $i => $s) {
            $k = $i + 1;
            $sheetsXml .= '<sheet name="' . $this->esc($s['name']) . '" sheetId="' . $k . '" r:id="rId' . $k . '"/>';
            $rels .= '<Relationship Id="rId' . $k . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $k . '.xml"/>';
            $zip->addFromString('xl/worksheets/sheet' . $k . '.xml', $this->sheetXml($s));
        }
        $rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheetsXml . '</sheets>'
            . '<definedNames>' . implode('', array_map(fn ($s, $i) => $s['table'] && count($s['headers']) ? '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">\'' . $this->esc(str_replace("'", "''", $s['name'])) . '\'!$A$1:$' . $this->col(count($s['headers']) - 1) . '$' . (count($s['data']) + 1) . '</definedName>' : '', $this->sheets, array_keys($this->sheets))) . '</definedNames></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>');
        // styles: 0 normal · 1 header (bold white on navy) · 2 title (bold, larger) · 3 bold
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="14"/><color rgb="FF0A2350"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0E4CA1"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top/><bottom style="thin"><color rgb="FFDDE3EE"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="top"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>');
        $zip->close();
    }

    public function download(string $fileName)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $this->save($tmp);
        return response()->download($tmp, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    private function sheetXml(array $s): string
    {
        $cols = count($s['headers']);
        $numeric = array_flip(array_keys(array_intersect($s['headers'], $s['numeric'])));
        // widths: given, or from the longest value (max 60)
        $w = [];
        foreach ($s['headers'] as $i => $h) $w[$i] = $s['widths'][$h] ?? null;
        foreach ($s['headers'] as $i => $h) {
            if ($w[$i]) continue;
            $len = mb_strlen((string) $h) + 2;
            foreach (array_slice($s['data'], 0, 500) as $r) $len = max($len, mb_strlen((string) ($r[$i] ?? '')) + 2);
            $w[$i] = min(60, max(8, $len));
        }
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $x .= $s['table'] ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' : '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        $x .= '<sheetFormatPr defaultRowHeight="15"/><cols>';
        foreach ($w as $i => $width) $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $width . '" customWidth="1"/>';
        $x .= '</cols><sheetData>';
        $row = $s['table'] ? 1 : 0;
        if ($cols && $s['table']) {
            $x .= '<row r="1" ht="22" customHeight="1">';
            foreach ($s['headers'] as $i => $h) $x .= $this->cell($i, 1, $h, $s['table'] ? 1 : 3, false);
            $x .= '</row>';
        }
        foreach ($s['data'] as $r) {
            $row++;
            $x .= '<row r="' . $row . '">';
            foreach ($r as $i => $v) {
                $style = 0;
                if (!$s['table'] && is_array($v)) { [$v, $style] = $v; }
                $x .= $this->cell($i, $row, $v, $style, isset($numeric[$i]) || (!$s['table'] && (is_int($v) || is_float($v))));
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if ($s['table'] && $cols) $x .= '<autoFilter ref="A1:' . $this->col($cols - 1) . max(1, $row) . '"/>';
        return $x . '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
    }

    private function cell(int $c, int $r, $v, int $style, bool $number): string
    {
        $ref = $this->col($c) . $r;
        if ($v === null || $v === '') return '<c r="' . $ref . '" s="' . $style . '"/>';
        if ($number && is_numeric($v)) return '<c r="' . $ref . '" s="' . $style . '"><v>' . (0 + $v) . '</v></c>';
        // text cells (inlineStr) are never run as formulas, so "+880…" or "=…" stay plain text
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $v);
        return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . $this->esc($v) . '</t></is></c>';
    }

    private function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
        return $s;
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
