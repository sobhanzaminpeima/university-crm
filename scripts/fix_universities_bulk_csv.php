<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/fix_universities_bulk_csv.php <input.csv> <output.csv>\n");
    exit(1);
}

$input = $argv[1];
$output = $argv[2];

if (!is_file($input)) {
    fwrite(STDERR, "Input file not found: {$input}\n");
    exit(1);
}

$in = fopen($input, 'r');
if ($in === false) {
    fwrite(STDERR, "Cannot open input file.\n");
    exit(1);
}

$out = fopen($output, 'w');
if ($out === false) {
    fclose($in);
    fwrite(STDERR, "Cannot open output file.\n");
    exit(1);
}

$header = fgetcsv($in);
if (!$header) {
    fclose($in);
    fclose($out);
    fwrite(STDERR, "Input CSV is empty.\n");
    exit(1);
}

fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $header);

$index = [];
foreach ($header as $i => $name) {
    $raw = (string) $name;
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $key = mb_strtolower(trim($raw, "\" \t\n\r\0\x0B"));
    $index[$key] = $i;
}

$rowCount = 0;
$written = 0;
while (($row = fgetcsv($in)) !== false) {
    $rowCount++;
    if (count($row) < count($header)) {
        $row = array_pad($row, count($header), '');
    }

    $nameIdx = $index['name'] ?? null;
    $countryIdx = $index['country'] ?? null;
    if ($nameIdx === null || $countryIdx === null) {
        continue;
    }

    $name = trim((string) ($row[$nameIdx] ?? ''));
    $country = trim((string) ($row[$countryIdx] ?? ''));
    if ($name === '' || $country === '') {
        continue;
    }

    $langIdx = $index['language'] ?? null;
    if ($langIdx !== null) {
        $lang = mb_strtolower(trim((string) ($row[$langIdx] ?? '')));
        if (in_array($lang, ['english/turkish', 'turkish/english', 'en/tr', 'tr/en', 'both'], true)) {
            $row[$langIdx] = 'Both';
        } elseif (in_array($lang, ['english', 'en'], true)) {
            $row[$langIdx] = 'English';
        } elseif (in_array($lang, ['turkish', 'tr'], true)) {
            $row[$langIdx] = 'Turkish';
        }
    }

    $progLangIdx = $index['program_language'] ?? null;
    if ($progLangIdx !== null) {
        $lang = mb_strtolower(trim((string) ($row[$progLangIdx] ?? '')));
        if (in_array($lang, ['english/turkish', 'turkish/english', 'en/tr', 'tr/en', 'both'], true)) {
            $row[$progLangIdx] = 'Both';
        } elseif (in_array($lang, ['english', 'en'], true)) {
            $row[$progLangIdx] = 'English';
        } elseif (in_array($lang, ['turkish', 'tr'], true)) {
            $row[$progLangIdx] = 'Turkish';
        }
    }

    $progNameIdx = $index['program_name'] ?? null;
    if ($progNameIdx !== null) {
        $row[$progNameIdx] = mb_substr(trim((string) ($row[$progNameIdx] ?? '')), 0, 255);
    }

    $progNotesIdx = $index['program_notes'] ?? null;
    if ($progNotesIdx !== null) {
        $row[$progNotesIdx] = mb_substr(trim((string) ($row[$progNotesIdx] ?? '')), 0, 2000);
    }

    fputcsv($out, $row);
    $written++;
}

fclose($in);
fclose($out);

echo "Rows read: {$rowCount}\n";
echo "Rows written: {$written}\n";
echo "Output: {$output}\n";
