<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Storage;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('crm:sla-check')->hourly();
        $schedule->command('crm:deadline-reminders')->dailyAt('08:00');
        $schedule->command('crm:backup --retention=14')->dailyAt('02:30')->withoutOverlapping();
        $schedule->command('crm:backup-verify')->dailyAt('03:00')->withoutOverlapping();
        $schedule->command('crm:ops-check')->hourly()->withoutOverlapping();
        $schedule->call(function (): void {
            Storage::disk('local')->put('health/scheduler-heartbeat.txt', now()->toIso8601String());
        })->everyMinute()->name('scheduler-heartbeat')->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
