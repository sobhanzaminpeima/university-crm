<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OpsCheckCommand extends Command
{
    protected $signature = 'crm:ops-check';
    protected $description = 'Verify database, private storage, and recent backups';

    public function handle(): int
    {
        try {
            DB::select('SELECT 1');
            $this->line('database=ok');
        } catch (\Throwable $exception) {
            $this->error('database=failed');
            report($exception);
            return self::FAILURE;
        }

        $probe = 'health/write-probe.txt';
        if (!Storage::disk('local')->put($probe, now()->toIso8601String())) {
            $this->error('private_storage=failed');
            return self::FAILURE;
        }
        Storage::disk('local')->delete($probe);
        $this->line('private_storage=ok');

        $latest = collect(Storage::disk('local')->directories('backups'))->sortDesc()->first();
        $this->line('latest_backup='.($latest ?: 'missing'));
        return self::SUCCESS;
    }
}
