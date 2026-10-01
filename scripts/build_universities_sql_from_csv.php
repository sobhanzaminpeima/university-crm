<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/build_universities_sql_from_csv.php <input.csv> <output.sql> [tenant_id=1] [created_by_user_id=1]\n");
    exit(1);
}

$input = $argv[1];
$output = $argv[2];
$tenantId = isset($argv[3]) ? (int) $argv[3] : 1;
$createdBy = isset($argv[4]) ? (int) $argv[4] : 1;

if (!is_file($input)) {
    fwrite(STDERR, "Input file not found: {$input}\n");
    exit(1);
}

$csv = fopen($input, 'r');
if ($csv === false) {
    fwrite(STDERR, "Cannot open input CSV.\n");
    exit(1);
}

$header = fgetcsv($csv);
if (!$header) {
    fclose($csv);
    fwrite(STDERR, "Input CSV is empty.\n");
    exit(1);
}

$index = [];
foreach ($header as $i => $h) {
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h) ?? (string) $h;
    $key = strtolower(trim($raw, "\" \t\n\r\0\x0B"));
    $key = preg_replace('/[^a-z0-9_]/', '', $key) ?? $key;
    $index[$key] = $i;
}

$columns = [
    'name','country','city','institution_type','website','currency','tuition_range','language','deadline',
    'visa_notes','description','programs_summary','program_degree_level','program_name','program_language',
    'program_duration','program_currency','program_fee','program_notes'
];

$uRows = [];
$pRows = [];
$uSeen = [];
$pSeen = [];

while (($row = fgetcsv($csv)) !== false) {
    if (count($row) < count($header)) {
        $row = array_pad($row, count($header), '');
    }
    $d = [];
    foreach ($columns as $c) {
        $k = preg_replace('/[^a-z0-9_]/', '', strtolower($c)) ?? $c;
        $idx = $index[$k] ?? null;
        $d[$c] = trim((string) ($idx !== null ? ($row[$idx] ?? '') : ''));
    }
    if ($d['name'] === '' || $d['country'] === '') {
        continue;
    }

    $d['institution_type'] = normalizeType($d['institution_type']);
    $d['currency'] = normalizeCurrency($d['currency']) ?: 'USD';
    $d['language'] = normalizeLanguage($d['language']);
    $d['deadline'] = normalizeDate($d['deadline']);
    $d['program_degree_level'] = normalizeDegree($d['program_degree_level']);
    $d['program_language'] = normalizeLanguage($d['program_language']);
    $d['program_currency'] = normalizeCurrency($d['program_currency']) ?: $d['currency'];
    $d['program_fee'] = normalizeFee($d['program_fee']);

    $d['name'] = mb_substr($d['name'], 0, 190);
    $d['country'] = mb_substr($d['country'], 0, 80);
    $d['city'] = mb_substr($d['city'], 0, 120);
    $d['website'] = mb_substr($d['website'], 0, 255);
    $d['currency'] = mb_substr($d['currency'], 0, 8);
    $d['tuition_range'] = mb_substr($d['tuition_range'], 0, 255);
    $d['language'] = mb_substr($d['language'], 0, 120);
    $d['program_name'] = mb_substr($d['program_name'], 0, 255);
    $d['program_degree_level'] = mb_substr($d['program_degree_level'], 0, 50);
    $d['program_language'] = mb_substr($d['program_language'], 0, 80);
    $d['program_duration'] = mb_substr($d['program_duration'], 0, 40);
    $d['program_currency'] = mb_substr($d['program_currency'], 0, 8);
    $d['program_notes'] = mb_substr($d['program_notes'], 0, 65000);
    $d['visa_notes'] = mb_substr($d['visa_notes'], 0, 65000);
    $d['description'] = mb_substr($d['description'], 0, 65000);
    $d['programs_summary'] = mb_substr($d['programs_summary'], 0, 65000);

    $uKey = strtolower($d['name'].'|'.$d['country'].'|'.$d['city']);
    if (!isset($uSeen[$uKey])) {
        $uSeen[$uKey] = true;
        $uRows[] = $d;
    }

    if ($d['program_name'] !== '') {
        $pKey = strtolower(implode('|', [
            $uKey,
            $d['program_degree_level'],
            $d['program_name'],
            $d['program_language'],
            $d['program_duration'],
            $d['program_currency'],
            $d['program_fee'],
        ]));
        if (!isset($pSeen[$pKey])) {
            $pSeen[$pKey] = true;
            $pRows[] = $d;
        }
    }
}
fclose($csv);

$sql = [];
$sql[] = "-- Generated on ".date('Y-m-d H:i:s');
$sql[] = "-- Tenant ID: {$tenantId}, Created By User ID: {$createdBy}";
$sql[] = "-- Universities: ".count($uRows).", Programs: ".count($pRows);
$sql[] = "SET NAMES utf8mb4;";
$sql[] = "START TRANSACTION;";

foreach ($uRows as $u) {
    $name = q($u['name']);
    $country = q($u['country']);
    $city = qn($u['city']);
    $type = q($u['institution_type']);
    $website = qn($u['website']);
    $currency = q($u['currency']);
    $range = qn($u['tuition_range']);
    $lang = qn($u['language']);
    $deadline = qn($u['deadline']);
    $visa = qn($u['visa_notes']);
    $desc = qn($u['description']);
    $summary = qn($u['programs_summary']);

    $whereCity = $u['city'] === ''
        ? "COALESCE(city,'') = ''"
        : "city = ".q($u['city']);

    $sql[] = "UPDATE universities SET ".
        "institution_type={$type}, website={$website}, currency={$currency}, tuition_range={$range}, language={$lang}, deadline={$deadline}, visa_notes={$visa}, description={$desc}, programs_summary={$summary}, is_active=1, updated_at=NOW() ".
        "WHERE tenant_id={$tenantId} AND name={$name} AND country={$country} AND {$whereCity};";

    $sql[] = "INSERT INTO universities (tenant_id, created_by_user_id, name, country, city, institution_type, website, currency, tuition_range, language, programs_summary, deadline, visa_notes, description, is_active, created_at, updated_at) ".
        "SELECT {$tenantId}, {$createdBy}, {$name}, {$country}, {$city}, {$type}, {$website}, {$currency}, {$range}, {$lang}, {$summary}, {$deadline}, {$visa}, {$desc}, 1, NOW(), NOW() ".
        "FROM DUAL WHERE NOT EXISTS (".
        "SELECT 1 FROM universities WHERE tenant_id={$tenantId} AND name={$name} AND country={$country} AND {$whereCity}".
        ");";
}

foreach ($pRows as $p) {
    $name = q($p['name']);
    $country = q($p['country']);
    $city = $p['city'];
    $whereCity = $city === ''
        ? "COALESCE(u.city,'') = ''"
        : "u.city = ".q($city);
    $degree = q($p['program_degree_level'] ?: 'Bachelor');
    $programName = q($p['program_name']);
    $programLang = qn($p['program_language']);
    $programDuration = qn($p['program_duration']);
    $programCurrency = q($p['program_currency'] ?: 'USD');
    $programFee = $p['program_fee'] === '' ? "NULL" : (string) ((float) $p['program_fee']);
    $programNotes = qn($p['program_notes']);

    $sql[] = "INSERT INTO university_programs (tenant_id, university_id, degree_level, program_name, language, duration, currency, fee, notes, created_at, updated_at) ".
        "SELECT {$tenantId}, u.id, {$degree}, {$programName}, {$programLang}, {$programDuration}, {$programCurrency}, {$programFee}, {$programNotes}, NOW(), NOW() ".
        "FROM universities u ".
        "WHERE u.tenant_id={$tenantId} AND u.name={$name} AND u.country={$country} AND {$whereCity} ".
        "AND NOT EXISTS (".
            "SELECT 1 FROM university_programs p ".
            "WHERE p.tenant_id={$tenantId} AND p.university_id=u.id AND p.degree_level={$degree} AND p.program_name={$programName} ".
            "AND COALESCE(p.language,'') = COALESCE({$programLang},'') ".
            "AND COALESCE(p.duration,'') = COALESCE({$programDuration},'') ".
            "AND COALESCE(p.currency,'') = COALESCE({$programCurrency},'') ".
            "AND COALESCE(p.fee,-1) = COALESCE({$programFee},-1)".
        ") LIMIT 1;";
}

$sql[] = "COMMIT;";

file_put_contents($output, implode("\n", $sql)."\n");

echo "SQL generated: {$output}\n";
echo "Universities: ".count($uRows)."\n";
echo "Programs: ".count($pRows)."\n";

function q(string $v): string {
    return "'".str_replace(["\\", "'"], ["\\\\", "\\'"], $v)."'";
}

function qn(string $v): string {
    return $v === '' ? "NULL" : q($v);
}

function normalizeType(string $v): string {
    $v = strtolower(trim($v));
    return $v === 'school' ? 'school' : 'university';
}

function normalizeLanguage(string $v): string {
    $v = strtolower(trim($v));
    if ($v === '') return '';
    if (in_array($v, ['both','english/turkish','turkish/english','en/tr','tr/en'], true)) return 'Both';
    if (in_array($v, ['english','en'], true)) return 'English';
    if (in_array($v, ['turkish','tr'], true)) return 'Turkish';
    return '';
}

function normalizeDegree(string $v): string {
    $v = strtolower(trim($v));
    if ($v === '') return 'Bachelor';
    if (str_contains($v, 'associate') || str_contains($v, 'önlisans') || str_contains($v, 'onlisans')) return 'Associate';
    if (str_contains($v, 'diploma')) return 'Diploma';
    if (str_contains($v, 'master') || str_contains($v, 'yüksek lisans') || str_contains($v, 'yuksek lisans')) return 'Master';
    if (str_contains($v, 'phd') || str_contains($v, 'doctor')) return 'PhD';
    return 'Bachelor';
}

function normalizeCurrency(string $v): string {
    $v = strtoupper(trim($v));
    $v = preg_replace('/[^A-Z]/', '', $v) ?? $v;
    return $v;
}

function normalizeFee(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    $v = str_replace(['$', '€', '£', '₺', ' '], '', $v);
    if (str_contains($v, ',') && !str_contains($v, '.')) {
        $v = str_replace(',', '.', $v);
    } else {
        $v = str_replace(',', '', $v);
    }
    return is_numeric($v) ? (string) ((float) $v) : '';
}

function normalizeDate(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
    $ts = strtotime($v);
    return $ts === false ? '' : date('Y-m-d', $ts);
}

