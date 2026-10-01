<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/repair_universities_text_csv.php <input.csv> <output.csv>\n");
    exit(1);
}

$input = $argv[1];
$output = $argv[2];

if (!is_file($input)) {
    fwrite(STDERR, "Input not found: {$input}\n");
    exit(1);
}

$in = fopen($input, 'r');
if ($in === false) {
    fwrite(STDERR, "Cannot open input file.\n");
    exit(1);
}

$header = fgetcsv($in);
if (!$header) {
    fclose($in);
    fwrite(STDERR, "CSV is empty.\n");
    exit(1);
}

$out = fopen($output, 'w');
if ($out === false) {
    fclose($in);
    fwrite(STDERR, "Cannot open output file.\n");
    exit(1);
}

fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $header);

$rows = 0;
while (($row = fgetcsv($in)) !== false) {
    $rows++;
    foreach ($row as $i => $cell) {
        $row[$i] = normalizeText((string) $cell);
    }
    fputcsv($out, $row);
}

fclose($in);
fclose($out);

echo "Rows repaired: {$rows}\n";
echo "Output: {$output}\n";

function normalizeText(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $best = $text;
    $bestScore = mojibakeScore($text);

    $candidates = [$text];
    $latin = @mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
    if (is_string($latin)) {
        $candidates[] = $latin;
    }
    $cp1252 = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    if (is_string($cp1252)) {
        $candidates[] = $cp1252;
    }
    // Two-pass recovery for heavy mojibake
    $latin2 = is_string($latin) ? @mb_convert_encoding($latin, 'UTF-8', 'ISO-8859-1') : null;
    if (is_string($latin2)) {
        $candidates[] = $latin2;
    }

    foreach ($candidates as $candidate) {
        $score = mojibakeScore($candidate);
        if ($score < $bestScore) {
            $best = $candidate;
            $bestScore = $score;
        }
    }

    $best = applyTurkishMojibakeMap($best);

    $best = preg_replace('/\s+\|\s+/u', ' | ', $best) ?? $best;
    $best = preg_replace('/\s{2,}/u', ' ', $best) ?? $best;

    return $best;
}

function mojibakeScore(string $text): int
{
    $needles = ['Ã', 'Ä', 'Å', 'Â', 'â€', 'â€™', 'â€œ', 'â€', 'â€“', 'â€”', 'â€¢'];
    $score = 0;
    foreach ($needles as $n) {
        $score += substr_count($text, $n);
    }
    return $score;
}

function applyTurkishMojibakeMap(string $text): string
{
    $map = [
        'Ä°' => 'İ',
        'Ä±' => 'ı',
        'Ãœ' => 'Ü',
        'Ã¼' => 'ü',
        'Ã–' => 'Ö',
        'Ã¶' => 'ö',
        'Ã‡' => 'Ç',
        'Ã§' => 'ç',
        'Äž' => 'Ğ',
        'ÄŸ' => 'ğ',
        'Åž' => 'Ş',
        'ÅŸ' => 'ş',
        'Â°' => '°',
        'â€“' => '-',
        'â€”' => '-',
        'â€™' => "'",
        'â€œ' => '"',
        'â€' => '"',
        'â€¢' => '-',
        'Â' => '',
    ];
    return strtr($text, $map);
}
