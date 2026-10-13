<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class NikahSheetReader
{
    /**
     * Read the first worksheet of an xlsx file.
     *
     * @return array{headers: list<string>, records: list<array{line: int, cells: array<string, string>}>, errors: list<string>}
     */
    public function read(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return ['headers' => [], 'records' => [], 'errors' => ['The file is not a readable Excel workbook. Upload an .xlsx file.']];
        }

        try {
            $sheetName = $this->firstSheetName($zip);
            if ($sheetName === null) {
                return ['headers' => [], 'records' => [], 'errors' => ['The workbook has no worksheet.']];
            }

            $sheetXml = $zip->getFromName($sheetName);
            if ($sheetXml === false) {
                return ['headers' => [], 'records' => [], 'errors' => ['The worksheet could not be read.']];
            }

            $strings = $this->sharedStrings($zip);
            $grid = $this->grid($sheetXml, $strings);
        } catch (RuntimeException $e) {
            return ['headers' => [], 'records' => [], 'errors' => [$e->getMessage()]];
        } finally {
            $zip->close();
        }

        $headerIndex = null;
        foreach ($grid as $index => $row) {
            if ($this->rowHasText($row['cells'])) {
                $headerIndex = $index;
                break;
            }
        }

        if ($headerIndex === null) {
            return ['headers' => [], 'records' => [], 'errors' => ['The file has no header row.']];
        }

        $headers = [];
        foreach ($grid[$headerIndex]['cells'] as $column => $value) {
            $header = trim($value);
            if ($header !== '') {
                $headers[$column] = $header;
            }
        }

        $records = [];
        foreach (array_slice($grid, $headerIndex + 1) as $row) {
            if (!$this->rowHasText($row['cells'])) {
                continue;
            }
            $cells = [];
            foreach ($headers as $column => $header) {
                $cells[$header] = trim((string) ($row['cells'][$column] ?? ''));
            }
            $records[] = ['line' => $row['line'], 'cells' => $cells];
        }

        return ['headers' => array_values($headers), 'records' => $records, 'errors' => []];
    }

    private function firstSheetName(ZipArchive $zip): ?string
    {
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($rels === false) {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $xpath = $this->xpath($rels);
        $nodes = $xpath->query('//*[local-name()="Relationship"]');
        if ($nodes === false) {
            return null;
        }

        foreach ($nodes as $node) {
            $type = $node->attributes?->getNamedItem('Type')?->nodeValue ?? '';
            if (!str_ends_with($type, '/worksheet')) {
                continue;
            }
            $target = $node->attributes?->getNamedItem('Target')?->nodeValue ?? '';
            $target = ltrim(str_replace('\\', '/', $target), '/');
            if (!str_starts_with($target, 'xl/')) {
                $target = 'xl/'.$target;
            }

            return $target;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false || $xml === '') {
            return [];
        }

        $xpath = $this->xpath($xml);
        $nodes = $xpath->query('//*[local-name()="si"]');
        $strings = [];
        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            $text = '';
            foreach ($node->getElementsByTagName('*') as $child) {
                if ($child->localName === 't') {
                    $text .= $child->textContent;
                }
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param  list<string>  $strings
     * @return list<array{line: int, cells: array<int, string>}>
     */
    private function grid(string $sheetXml, array $strings): array
    {
        $xpath = $this->xpath($sheetXml);
        $rows = $xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]');
        if ($rows === false) {
            throw new RuntimeException('The worksheet could not be read.');
        }

        $grid = [];
        foreach ($rows as $row) {
            $line = (int) ($row->attributes?->getNamedItem('r')?->nodeValue ?? (count($grid) + 1));
            $cells = [];
            foreach ($row->childNodes as $cell) {
                if ($cell->localName !== 'c') {
                    continue;
                }
                $ref = $cell->attributes?->getNamedItem('r')?->nodeValue ?? '';
                $column = $this->columnIndex($ref);
                if ($column === null) {
                    continue;
                }
                $type = $cell->attributes?->getNamedItem('t')?->nodeValue;
                $cells[$column] = $this->cellValue($cell, $type, $strings);
            }
            $grid[] = ['line' => $line, 'cells' => $cells];
        }

        return $grid;
    }

    /**
     * @param  list<string>  $strings
     */
    private function cellValue(\DOMNode $cell, ?string $type, array $strings): string
    {
        if ($type === 'inlineStr') {
            $text = '';
            foreach ($cell->childNodes as $child) {
                if ($child->localName === 'is') {
                    $text .= $child->textContent;
                }
            }

            return $text;
        }

        $raw = '';
        foreach ($cell->childNodes as $child) {
            if ($child->localName === 'v') {
                $raw = $child->textContent;
                break;
            }
        }

        if ($type === 's') {
            return $strings[(int) $raw] ?? '';
        }

        if ($raw !== '' && is_numeric($raw)) {
            $number = (float) $raw;
            if (floor($number) == $number && abs($number) < 1e15) {
                return sprintf('%.0f', $number);
            }
        }

        return $raw;
    }

    private function columnIndex(string $ref): ?int
    {
        if (!preg_match('/^([A-Z]+)/', $ref, $match)) {
            return null;
        }

        $index = 0;
        foreach (str_split($match[1]) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function rowHasText(array $cells): bool
    {
        foreach ($cells as $value) {
            if (trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($loaded === false) {
            throw new RuntimeException('The workbook XML could not be read.');
        }

        return new DOMXPath($dom);
    }
}
