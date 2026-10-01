<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Document;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HealthController extends Controller
{
    private const BACKUP_TABLES = [
        'students',
        'applications',
        'documents',
        'payments',
        'tasks',
        'student_messages',
        'student_requests',
        'universities',
        'university_programs',
        'scholarships',
        'automation_rules',
        'message_templates',
        'notifications',
        'api_tokens',
        'study_fields',
        'intake_terms',
    ];

    public function index(Request $request): View
    {
        $dbOk = true;
        try {
            DB::select('SELECT 1');
        } catch (\Throwable) {
            $dbOk = false;
        }

        $storageOk = Storage::disk('public')->exists('.') || is_dir(storage_path('app/public'));

        return view('health.index', [
            'dbOk' => $dbOk,
            'storageOk' => $storageOk,
            'appEnv' => config('app.env'),
            'appDebug' => (bool) config('app.debug'),
            'appUrl' => (string) config('app.url'),
            'backupTables' => $this->availableBackupTables(),
        ]);
    }

    public function backup(Request $request)
    {
        $auth = $this->authUser($request);
        $tables = $this->availableBackupTables();
        $data = [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'tenant_id' => $auth->tenant_id,
                'app_url' => (string) config('app.url'),
                'format' => 'tenant-backup-v2',
                'tables' => $tables,
            ],
            'data' => [],
            'counts' => [],
        ];

        foreach ($tables as $table) {
            $rows = DB::table($table)
                ->where('tenant_id', $auth->tenant_id)
                ->orderBy(Schema::hasColumn($table, 'id') ? 'id' : 'tenant_id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
            $data['data'][$table] = $rows;
            $data['counts'][$table] = count($rows);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $filename = 'tenant-backup-'.$auth->tenant_id.'-'.now()->format('Ymd-His').'.json';

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Backup-Tables' => (string) count($tables),
        ]);
    }

    public function restore(Request $request)
    {
        $auth = $this->authUser($request);
        $payload = $request->validate([
            'backup_json' => 'nullable|string',
            'backup_file' => 'nullable|file|mimes:json,txt|max:20480',
        ]);
        $raw = trim((string) ($payload['backup_json'] ?? ''));
        if ($request->hasFile('backup_file')) {
            $raw = (string) file_get_contents($request->file('backup_file')->getRealPath());
        }
        if ($raw === '') {
            return back()->withErrors(['backup_json' => 'Upload a backup file or paste backup JSON.']);
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return back()->withErrors(['backup_json' => 'Invalid JSON']);
        }
        $tables = $json['data'] ?? $json;
        if (!is_array($tables)) {
            return back()->withErrors(['backup_json' => 'Backup data section is missing.']);
        }

        $restored = [];
        DB::transaction(function () use ($auth, $tables, &$restored): void {
            foreach ($this->availableBackupTables() as $table) {
                $rows = $tables[$table] ?? [];
                if (!is_array($rows)) {
                    continue;
                }
                $columns = Schema::getColumnListing($table);
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $clean = array_intersect_key($row, array_flip($columns));
                    $clean['tenant_id'] = $auth->tenant_id;
                    if (array_key_exists('id', $clean)) {
                        DB::table($table)->updateOrInsert(
                            ['tenant_id' => $auth->tenant_id, 'id' => $clean['id']],
                            $clean
                        );
                    } else {
                        DB::table($table)->insert($clean);
                    }
                    $restored[$table] = ($restored[$table] ?? 0) + 1;
                }
            }
        });

        $summary = collect($restored)->map(fn ($count, $table) => "{$table}: {$count}")->implode(', ');
        return back()->with('success', 'Backup restored. '.$summary);
    }

    private function availableBackupTables(): array
    {
        return array_values(array_filter(self::BACKUP_TABLES, function (string $table): bool {
            return Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id');
        }));
    }
}

