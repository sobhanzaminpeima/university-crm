<?php

declare(strict_types=1);

use App\Models\University;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function guessUniversityName(string $filename): string
{
    $name = preg_replace('/\.(xlsx|docx)$/i', '', $filename) ?? $filename;
    $name = str_replace(['_', '-'], ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name) ?? $name;

    $map = [
        'Istanbul Atlas' => 'Istanbul Atlas University',
        'Biruni' => 'Biruni University',
        'OKAN' => 'Okan University',
        'IGU' => 'Istanbul Gelisim University',
        'Medipol' => 'Istanbul Medipol University',
    ];
    foreach ($map as $needle => $university) {
        if (stripos($name, $needle) !== false) {
            return $university;
        }
    }

    return trim($name);
}

function extractDocxText(string $path): string
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return '';
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if (!$xml) {
        return '';
    }

    $xml = str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], $xml);
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;

    return trim($text);
}

function extractXlsxRows(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return [];
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml) {
        $sx = @simplexml_load_string($sharedXml);
        if ($sx && isset($sx->si)) {
            foreach ($sx->si as $si) {
                $sharedStrings[] = trim((string) $si->t);
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$sheetXml) {
        return [];
    }

    $sheet = @simplexml_load_string($sheetXml);
    if (!$sheet || !isset($sheet->sheetData->row)) {
        return [];
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $type = (string) ($c['t'] ?? '');
            $value = (string) ($c->v ?? '');
            if ($type === 's' && $value !== '' && isset($sharedStrings[(int) $value])) {
                $value = $sharedStrings[(int) $value];
            }
            $value = trim((string) $value);
            $cells[] = $value;
        }
        if (!empty(array_filter($cells, fn ($v) => $v !== ''))) {
            $rows[] = $cells;
        }
    }

    return $rows;
}

function firstFeeValue(array $cells): ?float
{
    foreach ($cells as $value) {
        if (!is_string($value)) {
            continue;
        }
        $normalized = str_replace([',', '$', '€', '₺', 'TL', 'USD', 'EUR', 'TRY', ' '], ['', '', '', '', '', '', '', '', ''], $value);
        if ($normalized !== '' && is_numeric($normalized)) {
            return (float) $normalized;
        }
    }
    return null;
}

$tenantId = 1;
$createdByUserId = 1;
$baseDir = 'C:/Users/PC/Downloads/Telegram Desktop';
$files = [
    'Istanbul Atlas University Early Registration (2026-2027).docx',
    '2025-2026 Akademik Yılı Ücret Listesi.docx',
    '2026-2027 Tuition Fees.xlsx',
    'Biruni University Tution Fees 2026-2027.xlsx',
    'Fiyat Listesi 2026 - 2027 .docx',
    'OKAN UNIVERSITY 26-27 Price List.xlsx',
    '2025-2026 Fee List-Bachelor-FINAL (1).docx',
    '2026 Ücret Kataloğu (5).docx',
    '2026-2027 Academic Intake Tuition Fee List.docx',
    '2026-2027 Fall Undergraduate Programs.xlsx',
    '2026-2027 Price List Çalışması.xlsx',
    'IGU Tuition Fees List for the 2026-2027 Academic Year.xlsx',
    'İstanbul Medipol Üniversitesi Fiyat Listeleri - 25 15-1.xlsx',
];

$created = 0;
$updated = 0;
$programs = 0;

DB::beginTransaction();
try {
    foreach ($files as $filename) {
        $path = $baseDir.'/'.$filename;
        if (!is_file($path)) {
            echo "SKIP (missing): {$filename}\n";
            continue;
        }

        $universityName = guessUniversityName($filename);
        $isXlsx = str_ends_with(strtolower($filename), '.xlsx');

        $description = '';
        $tuitionRange = null;
        $programSummary = null;
        $programRows = [];

        if ($isXlsx) {
            $rows = extractXlsxRows($path);
            $flat = [];
            foreach (array_slice($rows, 0, 200) as $r) {
                $flat[] = implode(' | ', array_slice($r, 0, 8));
            }
            $description = implode("\n", array_slice($flat, 0, 120));
            $programSummary = implode(', ', array_slice($flat, 0, 20));

            foreach ($rows as $r) {
                $program = trim((string) ($r[0] ?? ''));
                if ($program === '' || strlen($program) < 3) {
                    continue;
                }
                $fee = firstFeeValue($r);
                $programRows[] = [
                    'program' => $program,
                    'fee' => $fee,
                ];
            }
        } else {
            $description = extractDocxText($path);
            $programSummary = mb_substr($description, 0, 1200);
        }

        $description = mb_substr($description, 0, 6500);
        $programSummary = $programSummary ? mb_substr($programSummary, 0, 4500) : null;
        preg_match_all('/\b\d{3,6}\b/u', $description, $matches);
        if (!empty($matches[0])) {
            $nums = array_map('intval', $matches[0]);
            $nums = array_filter($nums, fn ($n) => $n > 500);
            if (!empty($nums)) {
                $tuitionRange = min($nums).'-'.max($nums);
            }
        }

        $universityData = [
            'tenant_id' => $tenantId,
            'created_by_user_id' => $createdByUserId,
            'name' => $universityName,
            'country' => 'Turkey',
            'city' => 'Istanbul',
            'institution_type' => 'university',
            'currency' => 'USD',
            'tuition_range' => $tuitionRange,
            'language' => 'English/Turkish',
            'programs_summary' => $programSummary,
            'description' => $description,
            'is_active' => 1,
        ];

        $university = University::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($universityName)])
            ->first();

        if ($university) {
            $university->update($universityData);
            $updated++;
        } else {
            $university = University::query()->create($universityData);
            $created++;
        }

        $programRows = array_slice($programRows, 0, 200);
        foreach ($programRows as $row) {
            DB::table('university_programs')->updateOrInsert(
                [
                    'tenant_id' => $tenantId,
                    'university_id' => $university->id,
                    'degree_level' => 'Bachelor',
                    'program_name' => mb_substr($row['program'], 0, 255),
                ],
                [
                    'language' => 'English/Turkish',
                    'currency' => 'USD',
                    'fee' => $row['fee'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $programs++;
        }

        echo "OK: {$filename} => {$universityName}\n";
    }

    DB::commit();
} catch (Throwable $e) {
    DB::rollBack();
    fwrite(STDERR, "Import failed: ".$e->getMessage()."\n");
    exit(1);
}

echo "DONE | created={$created} updated={$updated} programs={$programs}\n";
