<?php

namespace App\Services;

use ZipArchive;
use SimpleXMLElement;
use Exception;
use DateTime;

class ExcelService
{
    /**
     * Column index (0-based) to Excel letters (0 => A, 25 => Z, 26 => AA, etc.)
     */
    public static function colLetter(int $colIndex): string
    {
        $letter = '';
        $colIndex++;
        while ($colIndex > 0) {
            $mod = ($colIndex - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $colIndex = (int)(($colIndex - $mod) / 26);
        }
        return $letter;
    }

    /**
     * Universal Date Normalizer:
     * Robustly converts any date format (Excel serial numbers, DD/MM/YYYY, MM/DD/YYYY, YYYY-MM-DD, text)
     * into canonical ISO 'YYYY-MM-DD' so database sorting with ORDER BY dispatch_date DESC is always 100% accurate.
     */
    public static function normalizeDate(mixed $dateVal): string
    {
        if (empty($dateVal)) {
            return date('Y-m-d');
        }

        $str = trim((string)$dateVal);

        // 1. Check if it's an Excel serial date number (e.g. 45000 to 60000 corresponds to years 2023 to 2064)
        if (is_numeric($str) && (float)$str > 25000 && (float)$str < 80000) {
            $days = (int)$str;
            // Excel leap year bug offset: 25569 days between 1900-01-01 and 1970-01-01
            $timestamp = ($days - 25569) * 86400;
            return gmdate('Y-m-d', $timestamp);
        }

        // 2. Format: DD/MM/YYYY or DD-MM-YYYY or DD.MM.YYYY
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $str, $m)) {
            $p1 = (int)$m[1];
            $p2 = (int)$m[2];
            $year = (int)$m[3];

            // If first number > 12, it MUST be day (DD/MM/YYYY)
            // If second number > 12, it MUST be month first (MM/DD/YYYY)
            if ($p1 > 12) {
                $day = str_pad($p1, 2, '0', STR_PAD_LEFT);
                $month = str_pad($p2, 2, '0', STR_PAD_LEFT);
            } elseif ($p2 > 12) {
                $day = str_pad($p2, 2, '0', STR_PAD_LEFT);
                $month = str_pad($p1, 2, '0', STR_PAD_LEFT);
            } else {
                // Default East Africa / UK logistics format: Day/Month/Year
                $day = str_pad($p1, 2, '0', STR_PAD_LEFT);
                $month = str_pad($p2, 2, '0', STR_PAD_LEFT);
            }

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        // 3. Format: YYYY/MM/DD or YYYY-MM-DD or YYYY.MM.DD
        if (preg_match('#^(\d{4})[/.-](\d{1,2})[/.-](\d{1,2})$#', $str, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        }

        // 4. Fallback to PHP strtotime
        $ts = strtotime($str);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d', $ts);
        }

        return date('Y-m-d');
    }

    /**
     * Export dataset to native .xlsx format (OpenXML Excel Workbook) and stream download
     */
    public static function exportXlsx(string $filename, array $headers, array $rows, string $sheetTitle = 'Dispatches'): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Unable to create temporary Excel archive.");
        }

        // Shared strings dictionary
        $strings = [];
        $stringLookup = [];
        $getStringIndex = function(string $str) use (&$strings, &$stringLookup) {
            if (isset($stringLookup[$str])) {
                return $stringLookup[$str];
            }
            $idx = count($strings);
            $strings[] = $str;
            $stringLookup[$str] = $idx;
            return $idx;
        };

        // 1. [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>');

        // 2. _rels/.rels
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        // 3. xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>');

        // 4. xl/workbook.xml
        $cleanSheetTitle = htmlspecialchars(substr($sheetTitle, 0, 31), ENT_QUOTES, 'UTF-8');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="' . $cleanSheetTitle . '" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>');

        // 5. xl/styles.xml (Self-contained, avoids theme color dependencies so Excel never prompts warnings)
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/><color rgb="FF0F172A"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/></border>
    <border>
      <left style="thin"><color rgb="FFE2E8F0"/></left>
      <right style="thin"><color rgb="FFE2E8F0"/></right>
      <top style="thin"><color rgb="FFE2E8F0"/></top>
      <bottom style="thin"><color rgb="FFE2E8F0"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="3">
    <!-- 0: Standard Body Cell -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>
    <!-- 1: Header Row Cell -->
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
    <!-- 2: Numeric Cell -->
    <xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>
  </cellXfs>
</styleSheet>');

        // 6. Build Sheet1.xml
        $totalCols = max(1, count($headers));
        $totalRows = 1 + count($rows);
        $lastColLetter = self::colLetter($totalCols - 1);

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
        $sheetXml .= '<dimension ref="A1:' . $lastColLetter . $totalRows . '"/>' . "\n";
        $sheetXml .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews>' . "\n";
        $sheetXml .= '<sheetFormatPr defaultRowHeight="18"/>' . "\n";
        $sheetXml .= '<sheetData>' . "\n";

        $rowNum = 1;
        // Header row (style 1)
        if (!empty($headers)) {
            $sheetXml .= '<row r="' . $rowNum . '" customHeight="1" ht="26">' . "\n";
            foreach ($headers as $colIdx => $h) {
                $ref = self::colLetter($colIdx) . $rowNum;
                $sIdx = $getStringIndex((string)$h);
                $sheetXml .= '<c r="' . $ref . '" s="1" t="s"><v>' . $sIdx . '</v></c>';
            }
            $sheetXml .= '</row>' . "\n";
            $rowNum++;
        }

        // Data rows
        foreach ($rows as $row) {
            $sheetXml .= '<row r="' . $rowNum . '" customHeight="1" ht="20">' . "\n";
            $colIdx = 0;
            foreach ($row as $val) {
                $ref = self::colLetter($colIdx) . $rowNum;
                if ($val === null || $val === '') {
                    $sheetXml .= '<c r="' . $ref . '" s="0"/>';
                } elseif (is_numeric($val) && !preg_match('/^0\d+/', (string)$val)) {
                    $sheetXml .= '<c r="' . $ref . '" s="0" t="n"><v>' . $val . '</v></c>';
                } else {
                    $sIdx = $getStringIndex((string)$val);
                    $sheetXml .= '<c r="' . $ref . '" s="0" t="s"><v>' . $sIdx . '</v></c>';
                }
                $colIdx++;
            }
            $sheetXml .= '</row>' . "\n";
            $rowNum++;
        }

        $sheetXml .= '</sheetData>' . "\n";
        $sheetXml .= '</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        // 7. xl/sharedStrings.xml
        $sstXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $sstXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">' . "\n";
        foreach ($strings as $s) {
            $sstXml .= '<si><t>' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>' . "\n";
        }
        $sstXml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $sstXml);

        $zip->close();

        // Stream file cleanly without any leftover buffered output
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($tempFile));

        readfile($tempFile);
        @unlink($tempFile);
        exit;
    }

    /**
     * Export dataset to single-file Excel spreadsheet (.xls)
     * Opens immediately on ALL devices, tablets, phones, and desktops without any ZIP/archive confusion.
     */
    public static function exportXls(string $filename, array $headers, array $rows, string $sheetTitle = 'Dispatches'): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta charset="utf-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
        echo '<x:Name>' . htmlspecialchars($sheetTitle) . '</x:Name>';
        echo '<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
        echo '<body style="font-family:Calibri,Arial,sans-serif;">';
        echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;border:1px solid #CBD5E1;">';

        // Header
        echo '<tr style="background-color:#0F172A;color:#FFFFFF;font-weight:bold;height:32px;">';
        foreach ($headers as $h) {
            echo '<th style="background-color:#0F172A;color:#FFFFFF;padding:8px 12px;border:1px solid #334155;text-align:left;">' . htmlspecialchars((string)$h) . '</th>';
        }
        echo '</tr>';

        // Rows
        foreach ($rows as $r) {
            echo '<tr style="height:24px;">';
            foreach ($r as $val) {
                $isNum = is_numeric($val) && !preg_match('/^0\d+/', (string)$val);
                $align = $isNum ? 'right' : 'left';
                echo '<td style="padding:6px 10px;border:1px solid #E2E8F0;text-align:' . $align . ';">' . htmlspecialchars((string)$val) . '</td>';
            }
            echo '</tr>';
        }

        echo '</table></body></html>';
        exit;
    }

    /**
     * Export dataset to standard CSV format with UTF-8 BOM so Excel opens it with proper characters and column splits
     */
    public static function exportCsv(string $filename, array $headers, array $rows): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $out = fopen('php://output', 'w');
        // Write UTF-8 BOM for Microsoft Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, $headers);
        foreach ($rows as $r) {
            fputcsv($out, $r);
        }
        fclose($out);
        exit;
    }

    /**
     * Read uploaded spreadsheet (.xlsx, .xls, or .csv) and return array of rows (each row is array of column values)
     */
    public static function importFile(string $filePath, ?string $originalName = null): array
    {
        $ext = strtolower(pathinfo($originalName ?: $filePath, PATHINFO_EXTENSION));

        if ($ext === 'xlsx') {
            return self::readXlsx($filePath);
        } elseif ($ext === 'xls') {
            return self::readXls($filePath);
        } else {
            // Default to CSV / Delimited parser
            return self::readCsv($filePath);
        }
    }

    /**
     * Parse .xlsx file into 2D array
     */
    public static function readXlsx(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("Cannot open .xlsx file. The file may be corrupted.");
        }

        // 1. Read shared strings
        $sharedStrings = [];
        $sstContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($sstContent !== false) {
            $xml = simplexml_load_string($sstContent);
            if ($xml && isset($xml->si)) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string)$si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string)$r->t;
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Read sheet1.xml
        $sheetContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetContent === false) {
            for ($i = 1; $i <= 5; $i++) {
                $sheetContent = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
                if ($sheetContent !== false) break;
            }
        }
        $zip->close();

        if ($sheetContent === false) {
            throw new Exception("Unable to locate worksheet data inside .xlsx archive.");
        }

        $xml = simplexml_load_string($sheetContent);
        if (!$xml || !isset($xml->sheetData->row)) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $r) {
            $rowData = [];
            $expectedCol = 0;
            foreach ($r->c as $c) {
                // Calculate column index from cell reference e.g. "B3"
                $cellRef = (string)$c['r'];
                preg_match('/^([A-Z]+)(\d+)$/', $cellRef, $matches);
                if (!empty($matches[1])) {
                    $colLetters = $matches[1];
                    $actualCol = 0;
                    for ($l = 0; $l < strlen($colLetters); $l++) {
                        $actualCol = $actualCol * 26 + (ord($colLetters[$l]) - 64);
                    }
                    $actualCol--; // 0-based

                    // Fill skipped empty columns
                    while ($expectedCol < $actualCol) {
                        $rowData[] = '';
                        $expectedCol++;
                    }
                }

                $type = (string)$c['t'];
                $val = (string)$c->v;

                if ($type === 's') {
                    $idx = (int)$val;
                    $cellVal = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                    $cellVal = (string)$c->is->t;
                } else {
                    $cellVal = $val;
                }

                $rowData[] = $cellVal;
                $expectedCol++;
            }
            $rows[] = $rowData;
        }

        return $rows;
    }

    /**
     * Parse .xls (supports Excel 2003 XML Spreadsheet, HTML tables, or fallback to CSV)
     */
    public static function readXls(string $filePath): array
    {
        $content = file_get_contents($filePath);

        // Check if it's XML Spreadsheet 2003
        if (str_contains($content, 'urn:schemas-microsoft-com:office:spreadsheet')) {
            $xml = @simplexml_load_string($content);
            if ($xml) {
                $rows = [];
                $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
                $xmlRows = $xml->xpath('//ss:Row');
                foreach ($xmlRows as $r) {
                    $rowData = [];
                    foreach ($r->xpath('ss:Cell') as $cell) {
                        $data = $cell->xpath('ss:Data');
                        $rowData[] = !empty($data) ? (string)$data[0] : '';
                    }
                    $rows[] = $rowData;
                }
                return $rows;
            }
        }

        // Fallback: Check if it's HTML table
        if (str_contains($content, '<table') && str_contains($content, '<tr')) {
            $dom = new \DOMDocument();
            @$dom->loadHTML($content);
            $rows = [];
            foreach ($dom->getElementsByTagName('tr') as $tr) {
                $rowData = [];
                foreach ($tr->childNodes as $td) {
                    if (in_array(strtolower($td->nodeName), ['td', 'th'])) {
                        $rowData[] = trim($td->textContent);
                    }
                }
                if (!empty($rowData)) {
                    $rows[] = $rowData;
                }
            }
            return $rows;
        }

        // Otherwise, standard CSV parser
        return self::readCsv($filePath);
    }

    /**
     * Parse CSV or TSV file into 2D array with auto-delimiter detection
     */
    public static function readCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Unable to open CSV file for reading.");
        }

        // Auto-detect delimiter from first line (, ; or \t)
        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = ',';
        if ($firstLine !== false) {
            $commaCount = substr_count($firstLine, ',');
            $semicolonCount = substr_count($firstLine, ';');
            $tabCount = substr_count($firstLine, "\t");

            if ($semicolonCount > $commaCount && $semicolonCount > $tabCount) {
                $delimiter = ';';
            } elseif ($tabCount > $commaCount && $tabCount > $semicolonCount) {
                $delimiter = "\t";
            }
        }

        $rows = [];
        while (($data = fgetcsv($handle, 4096, $delimiter)) !== false) {
            $rows[] = $data;
        }
        fclose($handle);

        return $rows;
    }
}
