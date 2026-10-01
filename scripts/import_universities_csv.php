<?php

declare(strict_types=1);

use App\Models\University;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$path = __DIR__.'/../storage/app/universities_bulk_import.csv';
if (!is_file($path)) {
    fwrite(STDERR, "CSV not found: {$path}\n");
    exit(1);
}

$h = fopen($path, 'r');
if ($h === false) {
    fwrite(STDERR, "Cannot open CSV: {$path}\n");
    exit(1);
}

$header = fgetcsv($h);
if (!$header) {
    fclose($h);
    fwrite(STDERR, "CSV header is empty.\n");
    exit(1);
}

$debug = getenv('IMPORT_DEBUG_HEADERS');
if ($debug === '1') {
    var_dump($header);
}

$normalizedHeader = [];
foreach ($header as $col) {
    $clean = preg_replace('/^\xEF\xBB\xBF/', '', (string) $col) ?? (string) $col;
    $clean = trim($clean, "\" \t\n\r\0\x0B");
    $clean = trim(mb_strtolower($clean));
    $normalizedHeader[] = $clean;
}
$idx = array_flip($normalizedHeader);

$tenantId = 1;
$createdByUserId = 1;
$created = 0;
$updated = 0;
$programs = 0;

DB::beginTransaction();
try {
    while (($r = fgetcsv($h)) !== false) {
        $name = trim((string) ($r[$idx['name']] ?? ''));
        $country = trim((string) ($r[$idx['country']] ?? ''));
        if ($name === '' || $country === '') {
            continue;
        }

        $city = trim((string) ($r[$idx['city']] ?? ''));
        $payload = [
            'tenant_id' => $tenantId,
            'created_by_user_id' => $createdByUserId,
            'name' => $name,
            'country' => $country,
            'city' => $city !== '' ? $city : null,
            'institution_type' => trim((string) ($r[$idx['institution_type']] ?? '')) ?: 'university',
            'website' => trim((string) ($r[$idx['website']] ?? '')) ?: null,
            'currency' => trim((string) ($r[$idx['currency']] ?? '')) ?: 'USD',
            'tuition_range' => trim((string) ($r[$idx['tuition_range']] ?? '')) ?: null,
            'language' => trim((string) ($r[$idx['language']] ?? '')) ?: null,
            'deadline' => trim((string) ($r[$idx['deadline']] ?? '')) ?: null,
            'visa_notes' => trim((string) ($r[$idx['visa_notes']] ?? '')) ?: null,
            'description' => trim((string) ($r[$idx['description']] ?? '')) ?: null,
            'programs_summary' => trim((string) ($r[$idx['programs_summary']] ?? '')) ?: null,
            'is_active' => 1,
        ];

        $u = University::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name)=?', [mb_strtolower($name)])
            ->first();

        if ($u) {
            $u->update($payload);
            $updated++;
        } else {
            $u = University::query()->create($payload);
            $created++;
        }

        $programName = trim((string) ($r[$idx['program_name']] ?? ''));
        if ($programName !== '') {
            $programFee = trim((string) ($r[$idx['program_fee']] ?? ''));
            DB::table('university_programs')->updateOrInsert(
                [
                    'tenant_id' => $tenantId,
                    'university_id' => $u->id,
                    'degree_level' => trim((string) ($r[$idx['program_degree_level']] ?? '')) ?: 'Bachelor',
                    'program_name' => mb_substr($programName, 0, 255),
                ],
                [
                    'language' => trim((string) ($r[$idx['program_language']] ?? '')) ?: null,
                    'duration' => trim((string) ($r[$idx['program_duration']] ?? '')) ?: null,
                    'currency' => trim((string) ($r[$idx['program_currency']] ?? '')) ?: 'USD',
                    'fee' => is_numeric($programFee) ? (float) $programFee : null,
                    'notes' => trim((string) ($r[$idx['program_notes']] ?? '')) ?: null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $programs++;
        }
    }
    DB::commit();
} catch (Throwable $e) {
    DB::rollBack();
    fclose($h);
    fwrite(STDERR, 'Import failed: '.$e->getMessage()."\n");
    exit(1);
}

fclose($h);
echo "DONE created={$created} updated={$updated} programs={$programs}\n";
