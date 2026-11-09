<?php

// Both the website and the download use this workbook.
const UNIVERSITY_LIST_FILE = __DIR__ . '/documents/University_List.xlsx';

function loadUniversityPartnerships($file)
{
    if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
        throw new RuntimeException('The university list requires PHP ZIP and SimpleXML extensions.');
    }
    if (!is_readable($file)) {
        throw new RuntimeException('The university workbook is missing or unreadable.');
    }

    $archive = new ZipArchive();
    if ($archive->open($file) !== true) {
        throw new RuntimeException('Unable to open the university workbook.');
    }

    try {
        $readXml = function ($path) use ($archive) {
            $contents = $archive->getFromName($path);
            if ($contents === false) {
                throw new RuntimeException('Missing workbook part: ' . $path);
            }
            $xml = simplexml_load_string($contents, 'SimpleXMLElement', LIBXML_NONET);
            if ($xml === false) {
                throw new RuntimeException('Invalid workbook XML: ' . $path);
            }
            return $xml;
        };

        // Excel stores plain and rich text in a shared string table.
        $textValue = function ($node) {
            $text = '';
            foreach ($node->xpath('.//*[local-name()="t"]') as $part) {
                $text .= (string) $part;
            }
            return trim($text);
        };
        $strings = [];
        if ($archive->locateName('xl/sharedStrings.xml') !== false) {
            foreach ($readXml('xl/sharedStrings.xml')->xpath('/*/*[local-name()="si"]') as $string) {
                $strings[] = $textValue($string);
            }
        }

        $cellValue = function ($cell) use ($strings, $textValue) {
            $values = $cell->xpath('./*[local-name()="v"]');
            $value = isset($values[0]) ? (string) $values[0] : '';
            if ((string) $cell['t'] === 's') {
                return isset($strings[(int) $value]) ? $strings[(int) $value] : '';
            }
            return (string) $cell['t'] === 'inlineStr' ? $textValue($cell) : trim($value);
        };

        $relationships = [];
        foreach ($readXml('xl/_rels/workbook.xml.rels')->xpath('/*/*') as $relationship) {
            if ((string) $relationship['TargetMode'] !== 'External') {
                $target = (string) $relationship['Target'];
                $relationships[(string) $relationship['Id']] = $target[0] === '/'
                    ? ltrim($target, '/') : 'xl/' . $target;
            }
        }

        $partnerships = [];
        foreach ($readXml('xl/workbook.xml')->xpath('/*/*[local-name()="sheets"]/*') as $sheet) {
            $country = trim(str_replace('_x0009_', "\t", (string) $sheet['name']));
            if ($country === 'University List') {
                continue; // The summary contains counts, not institution names.
            }
            $aliases = ['Melta' => 'Malta', 'Hong Kong,China' => 'China / Hong Kong'];
            $country = isset($aliases[$country]) ? $aliases[$country] : $country;
            $id = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (!isset($relationships[$id])) {
                throw new RuntimeException('Missing worksheet relationship for ' . $country);
            }

            $schools = [];
            $providerColumn = null;
            foreach ($readXml($relationships[$id])->xpath('/*/*[local-name()="sheetData"]/*') as $row) {
                $cells = [];
                foreach ($row->xpath('./*[local-name()="c"]') as $cell) {
                    $column = preg_replace('/[0-9]+/', '', (string) $cell['r']);
                    $cells[$column] = $cellValue($cell);
                    if ($cells[$column] === 'Provider') {
                        $providerColumn = $column;
                    }
                }
                // Only numbered institution rows; ignore headings and empty styled rows.
                if ($providerColumn !== null && isset($cells['A'], $cells[$providerColumn])
                    && ctype_digit($cells['A']) && $cells[$providerColumn] !== '') {
                    $schools[] = $cells[$providerColumn];
                }
            }
            if ($schools) {
                $partnerships[$country] = $schools;
            }
        }
        if (!$partnerships) {
            throw new RuntimeException('No institutions found in the university workbook.');
        }
        return $partnerships;
    } finally {
        $archive->close();
    }
}
