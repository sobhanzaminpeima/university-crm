<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DeadlineReminderCommand extends Command
{
    protected $signature = 'crm:deadline-reminders';
    protected $description = 'Notify agents/admins about application deadlines that are 3 days out, 1 day out, due today, or just became overdue';

    private const MILESTONE_DAYS = [3, 1, 0, -1];

    public function handle(): int
    {
        $today = Carbon::today();
        $count = 0;

        Application::query()
            ->whereNotNull('deadline')
            ->whereNotIn('status', ['enrolled', 'rejected'])
            ->chunkById(200, function ($applications) use ($today, &$count) {
                foreach ($applications as $application) {
                    $daysLeft = $today->diffInDays(Carbon::parse($application->deadline), false);
                    if (!in_array($daysLeft, self::MILESTONE_DAYS, true)) {
                        continue;
                    }

                    $student = Student::query()->find($application->student_id);
                    if (!$student) {
                        continue;
                    }

                    $recipientId = $student->agent_id ?? $student->sub_agent_id;
                    if (!$recipientId) {
                        $recipientId = User::query()
                            ->where('tenant_id', $application->tenant_id)
                            ->whereIn('role_slug', ['admin', 'super_admin'])
                            ->where('is_active', 1)
                            ->value('id');
                    }
                    if (!$recipientId) {
                        continue;
                    }

                    $tag = match (true) {
                        $daysLeft < 0 => 'is OVERDUE by '.abs($daysLeft).' day(s)',
                        $daysLeft === 0 => 'is due TODAY',
                        default => "is due in {$daysLeft} day(s)",
                    };

                    Notification::query()->create([
                        'tenant_id' => $application->tenant_id,
                        'user_id' => $recipientId,
                        'type' => 'deadline_reminder',
                        'title' => 'Application deadline reminder',
                        'body' => "{$student->full_name}'s application (#{$application->id}, {$application->program}) {$tag}.",
                        'meta_json' => json_encode(['application_id' => $application->id, 'student_id' => $student->id], JSON_UNESCAPED_UNICODE),
                    ]);
                    $count++;
                }
            });

        $this->info("Deadline reminders sent: {$count}");
        return self::SUCCESS;
    }
}
