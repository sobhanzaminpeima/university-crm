<?php

namespace App\Support\Export;

use DateTimeInterface;
use DateTimeZone;
use RuntimeException;
use ZipArchive;

/**
 * Dependency-free, streaming XLSX (Office Open XML) writer.
 *
 * Designed for shared hosting: only needs ext-zip, rows are written to a
 * temporary file as they arrive (bounded memory), and the output opens
 * cleanly in Excel, LibreOffice and Google Sheets.
 *
 * Layout of every sheet:
 *   row 1  report title (merged across all columns)
 *   row 2  subtitle (generated at, row count, active filters)
 *   row 3  blank spacer
 *   row 4  styled column headers (frozen, auto-filter, repeated when printing)
 *   row 5+ zebra-striped, bordered data rows with typed cells
 *
 * All text is written as inline strings, so cell content is never evaluated
 * as a formula (no spreadsheet formula injection) and numbers are only
 * numeric when the column type says so.
 */
class XlsxWriter
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const HEADER_ROW = 4;

    // cellXfs indexes (see styles()).
    private const S_TITLE = 1;

    private const S_SUBTITLE = 2;

    private const S_HEADER = 3;

    private const S_BODY = [
        'string' => [4, 5],
        'integer' => [6, 7],
        'decimal' => [8, 9],
        'date' => [10, 11],
        'datetime' => [12, 13],
        'money' => [14, 15],
        'percent' => [16, 17],
    ];

    /** @var array<int, array{label:string,type:string,width:?float}> */
    private array $columns = [];

    private string $title;

    private string $subtitle = '';

    private string $sheetName;

    /** @var resource */
    private $rowsHandle;

    private string $rowsPath;

    private int $rowCount = 0;

    /** @var array<int, int> max content length per column, for auto width */
    private array $maxLen = [];

    private DateTimeZone $timezone;

    /**
     * @param  array<int, array{label:string,type?:string,width?:float|int}>  $columns
     */
    public function __construct(string $title, array $columns, ?string $sheetName = null, ?string $timezone = null)
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to build XLSX files.');
        }
        if ($columns === []) {
            throw new RuntimeException('At least one column is required.');
        }

        $this->title = $title;
        $this->sheetName = self::sheetName($sheetName ?: $title);
        $this->timezone = new DateTimeZone($timezone ?: (string) config('app.timezone', 'UTC'));

        foreach (array_values($columns) as $i => $col) {
            $type = $col['type'] ?? 'string';
            if (! isset(self::S_BODY[$type])) {
                $type = 'string';
            }
            $this->columns[$i] = [
                'label' => (string) $col['label'],
                'type' => $type,
                'width' => isset($col['width']) ? (float) $col['width'] : null,
            ];
            $this->maxLen[$i] = mb_strlen((string) $col['label']);
        }

        $this->rowsPath = self::tempPath('rows');
        $handle = fopen($this->rowsPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open temporary export file.');
        }
        $this->rowsHandle = $handle;
    }

    public function setSubtitle(string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $values  in column order
     */
    public function addRow(array $values): self
    {
        $values = array_values($values);
        $r = self::HEADER_ROW + 1 + $this->rowCount;
        $zebra = $this->rowCount % 2;
        $xml = '<row r="'.$r.'">';

        foreach ($this->columns as $i => $col) {
            $ref = self::colLetter($i).$r;
            $style = self::S_BODY[$col['type']][$zebra];
            $xml .= $this->cell($ref, $values[$i] ?? null, $col['type'], $style, $i);
        }

        fwrite($this->rowsHandle, $xml.'</row>');
        $this->rowCount++;

        return $this;
    }

    public function rowCount(): int
    {
        return $this->rowCount;
    }

    /**
     * Build the .xlsx file and return its path. Caller owns (and should delete) the file.
     */
    public function save(): string
    {
        fclose($this->rowsHandle);

        $sheetPath = self::tempPath('sheet');
        $out = fopen($sheetPath, 'wb');
        if ($out === false) {
            throw new RuntimeException('Unable to open temporary sheet file.');
        }

        $lastCol = self::colLetter(count($this->columns) - 1);
        $lastRow = max(self::HEADER_ROW + $this->rowCount, self::HEADER_ROW);

        fwrite($out, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<dimension ref="A1:'.$lastCol.$lastRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0" showGridLines="0">'
            .'<pane ySplit="'.self::HEADER_ROW.'" topLeftCell="A'.(self::HEADER_ROW + 1).'" activePane="bottomLeft" state="frozen"/>'
            .'<selection pane="bottomLeft" activeCell="A'.(self::HEADER_ROW + 1).'" sqref="A'.(self::HEADER_ROW + 1).'"/>'
            .'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18" customHeight="1"/>'
            .'<cols>');

        foreach ($this->columns as $i => $col) {
            $width = $col['width'] ?? $this->autoWidth($i, $col['type']);
            fwrite($out, '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.number_format($width, 2, '.', '').'" customWidth="1"/>');
        }
        fwrite($out, '</cols><sheetData>');

        // Title + subtitle + spacer.
        fwrite($out, '<row r="1" ht="28" customHeight="1">'.$this->inline('A1', $this->title, self::S_TITLE).'</row>');
        fwrite($out, '<row r="2" ht="18" customHeight="1">'.$this->inline('A2', $this->subtitle, self::S_SUBTITLE).'</row>');
        fwrite($out, '<row r="3" ht="8" customHeight="1"/>');

        // Header row.
        fwrite($out, '<row r="'.self::HEADER_ROW.'" ht="24" customHeight="1">');
        foreach ($this->columns as $i => $col) {
            fwrite($out, $this->inline(self::colLetter($i).self::HEADER_ROW, $col['label'], self::S_HEADER));
        }
        fwrite($out, '</row>');

        // Data rows (streamed from the temp file in chunks).
        $in = fopen($this->rowsPath, 'rb');
        if ($in !== false) {
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        @unlink($this->rowsPath);

        fwrite($out, '</sheetData>');
        if ($this->rowCount > 0) {
            fwrite($out, '<autoFilter ref="A'.self::HEADER_ROW.':'.$lastCol.$lastRow.'"/>');
        }
        fwrite($out, '<mergeCells count="2"><mergeCell ref="A1:'.$lastCol.'1"/><mergeCell ref="A2:'.$lastCol.'2"/></mergeCells>'
            .'<printOptions horizontalCentered="1"/>'
            .'<pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            .'<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'<headerFooter><oddFooter>&amp;L'.self::xml($this->title).'&amp;RPage &amp;P of &amp;N</oddFooter></headerFooter>'
            .'</worksheet>');
        fclose($out);

        $xlsxPath = self::tempPath('xlsx');
        $zip = new ZipArchive;
        if ($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($sheetPath);
            throw new RuntimeException('Unable to create XLSX archive.');
        }

        $sheetRef = "'".str_replace("'", "''", $this->sheetName)."'";
        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('docProps/app.xml', self::appProps());
        $zip->addFromString('docProps/core.xml', $this->coreProps());
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<bookViews><workbookView/></bookViews>'
            .'<sheets><sheet name="'.self::xml($this->sheetName).'" sheetId="1" r:id="rId1"/></sheets>'
            .'<definedNames>'
            .($this->rowCount > 0 ? '<definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">'.self::xml($sheetRef).'!$A$'.self::HEADER_ROW.':$'.$lastCol.'$'.$lastRow.'</definedName>' : '')
            .'<definedName name="_xlnm.Print_Titles" localSheetId="0">'.self::xml($sheetRef).'!$'.self::HEADER_ROW.':$'.self::HEADER_ROW.'</definedName>'
            .'</definedNames>'
            .'</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($sheetPath);

        return $xlsxPath;
    }

    // ---------------------------------------------------------------- cells

    private function cell(string $ref, mixed $value, string $type, int $style, int $colIndex): string
    {
        if ($value === null || $value === '') {
            return '<c r="'.$ref.'" s="'.$style.'"/>';
        }

        switch ($type) {
            case 'integer':
            case 'decimal':
            case 'money':
            case 'percent':
                if (is_numeric($value)) {
                    $num = $type === 'integer' ? (string) (int) $value : (string) (float) $value;
                    $this->track($colIndex, $type === 'integer' ? $num : number_format((float) $value, 2));

                    return '<c r="'.$ref.'" s="'.$style.'"><v>'.$num.'</v></c>';
                }
                break; // non-numeric content falls back to text, never to a formula
            case 'date':
            case 'datetime':
                $serial = $this->excelDate($value);
                if ($serial !== null) {
                    $this->track($colIndex, $type === 'date' ? '2026-01-01' : '2026-01-01 00:00');

                    return '<c r="'.$ref.'" s="'.$style.'"><v>'.$serial.'</v></c>';
                }
                break;
        }

        $text = is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value;
        $this->track($colIndex, $text);

        // Text cells keep the text alignment style for this row's stripe.
        $textStyle = self::S_BODY['string'][($style - 4) % 2];

        return $this->inline($ref, $text, $textStyle);
    }

    private function inline(string $ref, string $text, int $style): string
    {
        if ($text === '') {
            return '<c r="'.$ref.'" s="'.$style.'"/>';
        }

        return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.self::xml($text).'</t></is></c>';
    }

    private function excelDate(mixed $value): ?string
    {
        try {
            if ($value instanceof DateTimeInterface) {
                $dt = \DateTimeImmutable::createFromInterface($value)->setTimezone($this->timezone);
            } elseif (is_string($value) && trim($value) !== '') {
                $dt = new \DateTimeImmutable($value, $this->timezone);
                $dt = $dt->setTimezone($this->timezone);
            } else {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        // Excel serial date: days since 1899-12-30 using wall-clock time.
        $wall = gmmktime((int) $dt->format('H'), (int) $dt->format('i'), (int) $dt->format('s'),
            (int) $dt->format('n'), (int) $dt->format('j'), (int) $dt->format('Y'));

        return rtrim(rtrim(number_format($wall / 86400 + 25569, 10, '.', ''), '0'), '.');
    }

    private function track(int $colIndex, string $text): void
    {
        $longestLine = 0;
        foreach (preg_split('/\R/u', $text) ?: [$text] as $line) {
            $longestLine = max($longestLine, mb_strlen($line));
        }
        if ($longestLine > ($this->maxLen[$colIndex] ?? 0)) {
            $this->maxLen[$colIndex] = $longestLine;
        }
    }

    private function autoWidth(int $colIndex, string $type): float
    {
        $min = match ($type) {
            'date' => 12.0,
            'datetime' => 17.0,
            'money', 'decimal' => 12.0,
            default => 9.0,
        };

        return min(60.0, max($min, ($this->maxLen[$colIndex] ?? 8) * 1.1 + 3));
    }

    // -------------------------------------------------------------- helpers

    public static function colLetter(int $index): string
    {
        $letters = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    public static function xml(string $text): string
    {
        // Strip characters that are illegal in XML 1.0 (they corrupt the workbook).
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function sheetName(string $name): string
    {
        $name = trim(preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', $name) ?? '');
        $name = mb_substr($name !== '' ? $name : 'Export', 0, 31);

        return trim($name, "' ") ?: 'Export';
    }

    private static function tempPath(string $prefix): string
    {
        $dir = function_exists('storage_path') ? storage_path('app/exports-tmp') : sys_get_temp_dir();
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (! is_dir($dir) || ! is_writable($dir)) {
            $dir = sys_get_temp_dir();
        }

        return $dir.DIRECTORY_SEPARATOR.$prefix.'-'.bin2hex(random_bytes(8)).'.tmp';
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private static function appProps(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Vertue CRM</Application></Properties>';
    }

    private function coreProps(): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            .'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            .'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.self::xml($this->title).'</dc:title><dc:creator>Vertue CRM</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private static function styles(): string
    {
        $navy = 'FF0A4F7A';
        $stripe = 'FFF3F7FB';
        $line = 'FFD9E2EC';

        // xf(numFmt, font, fill, border, alignment-xml)
        $xf = function (int $numFmt, int $font, int $fill, int $border, string $align) {
            return '<xf numFmtId="'.$numFmt.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0"'
                .($numFmt ? ' applyNumberFormat="1"' : '').' applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
                .$align.'</xf>';
        };
        $left = '<alignment vertical="center" wrapText="1"/>';
        $right = '<alignment horizontal="right" vertical="center"/>';
        $center = '<alignment horizontal="center" vertical="center"/>';

        $cellXfs = [
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>',      // 0 default
            $xf(0, 1, 0, 0, '<alignment vertical="center"/>'),                    // 1 title
            $xf(0, 2, 0, 0, '<alignment vertical="center"/>'),                    // 2 subtitle
            $xf(0, 3, 2, 1, '<alignment horizontal="center" vertical="center" wrapText="1"/>'), // 3 header
            $xf(0, 0, 0, 1, $left), $xf(0, 0, 3, 1, $left),                        // 4/5 text
            $xf(3, 0, 0, 1, $right), $xf(3, 0, 3, 1, $right),                      // 6/7 integer #,##0
            $xf(4, 0, 0, 1, $right), $xf(4, 0, 3, 1, $right),                      // 8/9 decimal #,##0.00
            $xf(164, 0, 0, 1, $center), $xf(164, 0, 3, 1, $center),                // 10/11 date
            $xf(165, 0, 0, 1, $center), $xf(165, 0, 3, 1, $center),                // 12/13 datetime
            $xf(166, 0, 0, 1, $right), $xf(166, 0, 3, 1, $right),                  // 14/15 money
            $xf(10, 0, 0, 1, $right), $xf(10, 0, 3, 1, $right),                    // 16/17 percent
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="3">'
            .'<numFmt numFmtId="164" formatCode="yyyy-mm-dd"/>'
            .'<numFmt numFmtId="165" formatCode="yyyy-mm-dd hh:mm"/>'
            .'<numFmt numFmtId="166" formatCode="#,##0.00"/>'
            .'</numFmts>'
            .'<fonts count="4">'
            .'<font><sz val="11"/><color rgb="FF1F2933"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="16"/><color rgb="'.$navy.'"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><i/><sz val="10"/><color rgb="FF627D98"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            .'</fonts>'
            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="'.$navy.'"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="'.$stripe.'"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="'.$line.'"/></left><right style="thin"><color rgb="'.$line.'"/></right>'
            .'<top style="thin"><color rgb="'.$line.'"/></top><bottom style="thin"><color rgb="'.$line.'"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($cellXfs).'">'.implode('', $cellXfs).'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
