<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class EncryptedBackupCommand extends Command
{
    protected $signature = 'crm:backup {--retention=14 : Number of daily backups to retain}';
    protected $description = 'Create an application-encrypted database backup in private storage';

    private const EXCLUDED_TABLES = ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens'];

    public function handle(): int
    {
        $stamp = now()->format('Ymd-His');
        $directory = 'backups/'.$stamp;
        $manifest = ['created_at' => now()->toIso8601String(), 'format' => 'crm-encrypted-v1', 'tables' => []];

        foreach (Schema::getTableListing() as $tableName) {
            $table = str_contains($tableName, '.') ? substr($tableName, strrpos($tableName, '.') + 1) : $tableName;
            if (in_array($table, self::EXCLUDED_TABLES, true)) {
                continue;
            }
            $rows = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $encrypted = Crypt::encryptString($json);
            $path = $directory.'/'.$table.'.json.enc';
            if (!Storage::disk('local')->put($path, $encrypted)) {
                $this->error("Unable to write backup table: {$table}");
                return self::FAILURE;
            }
            $manifest['tables'][$table] = ['rows' => count($rows), 'sha256' => hash('sha256', $encrypted)];
            unset($rows, $json, $encrypted);
        }

        Storage::disk('local')->put($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->prune(max(2, (int) $this->option('retention')));
        $this->info('Encrypted backup created: '.$directory);

        return self::SUCCESS;
    }

    private function prune(int $retention): void
    {
        $directories = collect(Storage::disk('local')->directories('backups'))->sortDesc()->values();
        foreach ($directories->slice($retention) as $directory) {
            Storage::disk('local')->deleteDirectory($directory);
        }
    }
}
