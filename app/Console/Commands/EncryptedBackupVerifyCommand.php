<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class EncryptedBackupVerifyCommand extends Command
{
    protected $signature = 'crm:backup-verify {backup? : Backup directory name, defaults to latest}';
    protected $description = 'Verify checksums and decryptability of an encrypted CRM backup';

    public function handle(): int
    {
        $backup = (string) ($this->argument('backup') ?: collect(Storage::disk('local')->directories('backups'))->sortDesc()->first());
        if ($backup === '') {
            $this->error('No backup exists.');
            return self::FAILURE;
        }
        if (!str_starts_with($backup, 'backups/')) {
            $backup = 'backups/'.basename($backup);
        }
        $manifestRaw = Storage::disk('local')->get($backup.'/manifest.json');
        $manifest = json_decode((string) $manifestRaw, true);
        if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'crm-encrypted-v1') {
            $this->error('Backup manifest is invalid.');
            return self::FAILURE;
        }
        foreach (($manifest['tables'] ?? []) as $table => $metadata) {
            $ciphertext = Storage::disk('local')->get($backup.'/'.$table.'.json.enc');
            if (!is_string($ciphertext) || !hash_equals((string) ($metadata['sha256'] ?? ''), hash('sha256', $ciphertext))) {
                $this->error("Checksum failed: {$table}");
                return self::FAILURE;
            }
            try {
                $rows = json_decode(Crypt::decryptString($ciphertext), true, flags: JSON_THROW_ON_ERROR);
            } catch (\Throwable $exception) {
                report($exception);
                $this->error("Decrypt failed: {$table}");
                return self::FAILURE;
            }
            if (!is_array($rows) || count($rows) !== (int) ($metadata['rows'] ?? -1)) {
                $this->error("Row count failed: {$table}");
                return self::FAILURE;
            }
        }
        $this->info('Backup verified: '.$backup);
        return self::SUCCESS;
    }
}
