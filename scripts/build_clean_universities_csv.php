<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/build_clean_universities_csv.php <input.csv> <output.csv>\n");
    exit(1);
}

$input = $argv[1];
$output = $argv[2];

if (!is_file($input)) {
    fwrite(STDERR, "Input not found: {$input}\n");
    exit(1);
}

$importColumns = [
    'name',
    'country',
    'city',
    'institution_type',
    'website',
    'currency',
    'tuition_range',
    'language',
    'deadline',
    'visa_notes',
    'description',
    'programs_summary',
    'program_degree_level',
    'program_name',
    'program_language',
    'program_duration',
    'program_currency',
    'program_fee',
    'program_notes',
];

$in = fopen($input, 'r');
if ($in === false) {
    fwrite(STDERR, "Cannot open input file.\n");
    exit(1);
}

$header = fgetcsv($in);
if (!$header) {
    fclose($in);
    fwrite(STDERR, "Input CSV is empty.\n");
    exit(1);
}

$normalizedHeader = [];
foreach ($header as $idx => $h) {
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h) ?? (string) $h;
    $raw = trim($raw, "\" \t\n\r\0\x0B");
    $key = mb_strtolower($raw);
    $key = preg_replace('/[^a-z0-9_]/', '', $key) ?? $key;
    $normalizedHeader[$key] = $idx;
}

$rows = [];
$seenUniversity = [];
$seenProgram = [];
$read = 0;
$written = 0;

while (($row = fgetcsv($in)) !== false) {
    $read++;
    if (count($row) < count($header)) {
        $row = array_pad($row, count($header), '');
    }

    $item = [];
    foreach ($importColumns as $col) {
        $lookup = preg_replace('/[^a-z0-9_]/', '', mb_strtolower($col)) ?? $col;
        $idx = $normalizedHeader[$lookup] ?? null;
        $item[$col] = $idx !== null ? cleanText((string) ($row[$idx] ?? ''), columnLimit($col)) : '';
    }

    $item['name'] = trim($item['name']);
    $item['country'] = trim($item['country']);
    $item['city'] = trim($item['city']);
    if ($item['name'] === '' || $item['country'] === '') {
        continue;
    }

    $item['institution_type'] = normalizeType($item['institution_type']);
    $item['currency'] = normalizeCurrency($item['currency']) ?: 'USD';
    $item['program_currency'] = normalizeCurrency($item['program_currency']) ?: $item['currency'];
    $item['language'] = normalizeLanguage($item['language']);
    $item['program_language'] = normalizeLanguage($item['program_language']);
    $item['program_degree_level'] = normalizeDegree($item['program_degree_level']);
    $item['program_fee'] = normalizeFee($item['program_fee']);
    $item['deadline'] = normalizeDate($item['deadline']);

    $uniKey = mb_strtolower($item['name'].'|'.$item['country'].'|'.$item['city']);
    $programName = trim($item['program_name']);

    if ($programName === '') {
        if (isset($seenUniversity[$uniKey])) {
            continue;
        }
        $seenUniversity[$uniKey] = true;
    } else {
        $pKey = mb_strtolower(implode('|', [
            $item['name'],
            $item['country'],
            $item['city'],
            $item['program_degree_level'],
            $programName,
            $item['program_language'],
            $item['program_duration'],
            $item['program_currency'],
            $item['program_fee'],
        ]));
        if (isset($seenProgram[$pKey])) {
            continue;
        }
        $seenProgram[$pKey] = true;
    }

    $rows[] = $item;
    $written++;
}
fclose($in);

usort($rows, function (array $a, array $b): int {
    return [$a['name'], $a['country'], $a['city'], $a['program_name']] <=> [$b['name'], $b['country'], $b['city'], $b['program_name']];
});

$out = fopen($output, 'w');
if ($out === false) {
    fwrite(STDERR, "Cannot open output file.\n");
    exit(1);
}
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $importColumns);
foreach ($rows as $r) {
    $csvRow = [];
    foreach ($importColumns as $col) {
        $csvRow[] = $r[$col] ?? '';
    }
    fputcsv($out, $csvRow);
}
fclose($out);

echo "Rows read: {$read}\n";
echo "Rows written (clean): {$written}\n";
echo "Unique universities: ".count($seenUniversity)."\n";
echo "Unique programs: ".count($seenProgram)."\n";
echo "Output: {$output}\n";

function columnLimit(string $column): int
{
    return match ($column) {
        'name' => 160,
        'country' => 80,
        'city' => 120,
        'institution_type' => 20,
        'website' => 255,
        'currency', 'program_currency' => 8,
        'language', 'program_language' => 20,
        'deadline' => 20,
        'visa_notes', 'program_notes' => 2000,
        'description', 'programs_summary' => 5000,
        'program_degree_level' => 60,
        'program_name' => 255,
        'program_duration' => 40,
        'program_fee' => 30,
        default => 255,
    };
}

function cleanText(string $value, int $limit): string
{
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    $value = str_replace("\0", '', $value);
    if ($value === '') {
        return '';
    }
    return mb_substr($value, 0, $limit);
}

function normalizeType(string $value): string
{
    $v = mb_strtolower(trim($value));
    return $v === 'school' ? 'school' : 'university';
}

function normalizeLanguage(string $value): string
{
    $v = mb_strtolower(trim($value));
    if ($v === '') {
        return '';
    }
    if (in_array($v, ['both', 'english/turkish', 'turkish/english', 'en/tr', 'tr/en'], true)) {
        return 'Both';
    }
    if (in_array($v, ['english', 'en'], true)) {
        return 'English';
    }
    if (in_array($v, ['turkish', 'tr'], true)) {
        return 'Turkish';
    }
    return '';
}

function normalizeCurrency(string $value): string
{
    $v = strtoupper(trim($value));
    if ($v === '') {
        return '';
    }
    $v = preg_replace('/[^A-Z]/', '', $v) ?? $v;
    return mb_substr($v, 0, 8);
}

function normalizeDegree(string $value): string
{
    $v = mb_strtolower(trim($value));
    if ($v === '') {
        return 'Bachelor';
    }
    if (str_contains($v, 'associate') || str_contains($v, 'önlisans') || str_contains($v, 'onlisans')) {
        return 'Associate';
    }
    if (str_contains($v, 'diploma')) {
        return 'Diploma';
    }
    if (str_contains($v, 'master') || str_contains($v, 'yüksek lisans') || str_contains($v, 'yuksek lisans')) {
        return 'Master';
    }
    if (str_contains($v, 'phd') || str_contains($v, 'doctor')) {
        return 'PhD';
    }
    return 'Bachelor';
}

function normalizeFee(string $value): string
{
    $v = trim($value);
    if ($v === '') {
        return '';
    }
    $v = str_replace(['$', '€', '£', '₺', ' '], '', $v);
    if (str_contains($v, ',') && !str_contains($v, '.')) {
        $v = str_replace(',', '.', $v);
    } else {
        $v = str_replace(',', '', $v);
    }
    return is_numeric($v) ? (string) ((float) $v) : '';
}

function normalizeDate(string $value): string
{
    $v = trim($value);
    if ($v === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return $v;
    }
    $ts = strtotime($v);
    if ($ts === false) {
        return '';
    }
    return date('Y-m-d', $ts);
}

