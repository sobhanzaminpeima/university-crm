<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Student;
use App\Models\Task;
use App\Services\FollowupCommunicationService;
use App\Services\WhatsappNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AutomationRuleController extends Controller
{
    public function __construct(
        private readonly WhatsappNotificationService $whatsapp,
        private readonly FollowupCommunicationService $communication
    ) {
    }

    public function index(Request $request): View
    {
        $auth = $this->authUser($request);
        $rules = DB::table('automation_rules')
            ->where('tenant_id', $auth->tenant_id)
            ->orderByDesc('id')
            ->get();

        return view('automation.index', compact('rules'));
    }

    public function store(Request $request): RedirectResponse
    {
        $auth = $this->authUser($request);
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'trigger_key' => 'required|string|in:sla_overdue_tasks,daily_followup,documents_pending_3days',
            'is_active' => 'nullable|boolean',
        ]);

        DB::table('automation_rules')->insert([
            'tenant_id' => $auth->tenant_id,
            'name' => $data['name'],
            'trigger_key' => $data['trigger_key'],
            'conditions_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'actions_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'is_active' => (int) ($data['is_active'] ?? 1),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Automation rule created.');
    }

    public function run(Request $request): RedirectResponse
    {
        $auth = $this->authUser($request);
        $rules = DB::table('automation_rules')
            ->where('tenant_id', $auth->tenant_id)
            ->where('is_active', 1)
            ->get();

        foreach ($rules as $rule) {
            if ($rule->trigger_key === 'sla_overdue_tasks') {
                $overdue = Task::query()
                    ->forTenant($auth->tenant_id, $auth->role_slug)
                    ->whereIn('status', ['todo', 'in_progress'])
                    ->whereNotNull('deadline')
                    ->where('deadline', '<', now())
                    ->get();
                foreach ($overdue as $task) {
                    Notification::query()->create([
                        'tenant_id' => $auth->tenant_id,
                        'user_id' => $task->assigned_to,
                        'type' => 'sla_alert',
                        'title' => 'SLA alert: overdue task',
                        'body' => 'Task "'.$task->title.'" is overdue.',
                        'meta_json' => json_encode(['task_id' => $task->id], JSON_UNESCAPED_UNICODE),
                    ]);
                    $this->whatsapp->notifyTenant(
                        $auth->tenant_id,
                        'application_update',
                        'SLA alert: overdue task "'.$task->title.'".'
                    );
                }
            }
            if ($rule->trigger_key === 'documents_pending_3days') {
                $notifSettings = null;
                if (Schema::hasTable('tenant_notification_settings')) {
                    $notifSettings = DB::table('tenant_notification_settings')->where('tenant_id', $auth->tenant_id)->first();
                }
                $days = max(1, (int) ($notifSettings->docs_pending_days ?? 3));
                $taskEnabled = (int) ($notifSettings->docs_pending_task_enabled ?? 1) === 1;
                $emailEnabled = (int) ($notifSettings->docs_pending_email_enabled ?? 0) === 1;
                $whatsappEnabled = (int) ($notifSettings->docs_pending_whatsapp_enabled ?? 1) === 1;
                $smsEnabled = (int) ($notifSettings->docs_pending_sms_enabled ?? 0) === 1;

                $stale = DB::table('applications')
                    ->where('tenant_id', $auth->tenant_id)
                    ->where('status', 'documents_pending')
                    ->where('updated_at', '<', now()->subDays($days))
                    ->get(['id', 'student_id', 'program', 'updated_at']);

                foreach ($stale as $application) {
                    $student = Student::query()
                        ->forTenant($auth->tenant_id, $auth->role_slug)
                        ->find($application->student_id);
                    if (!$student) {
                        continue;
                    }

                    $assigneeId = (int) ($student->sub_agent_id ?: $student->agent_id ?: $auth->id);
                    if ($assigneeId < 1) {
                        $assigneeId = (int) $auth->id;
                    }

                    $exists = Task::query()
                        ->forTenant($auth->tenant_id, $auth->role_slug)
                        ->where('student_id', $student->id)
                        ->where('status', '!=', 'completed')
                        ->where('title', 'like', 'Follow-up: Missing documents%')
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    if ($taskEnabled) {
                        Task::query()->create([
                            'tenant_id' => $auth->tenant_id,
                            'student_id' => $student->id,
                            'assigned_to' => $assigneeId,
                            'title' => 'Follow-up: Missing documents',
                            'description' => 'Application #'.$application->id.' is still in Documents Pending for more than '.$days.' days.',
                            'priority' => 'high',
                            'status' => 'todo',
                            'deadline' => now()->addDay(),
                            'escalation_level' => 0,
                        ]);
                    }

                    Notification::query()->create([
                        'tenant_id' => $auth->tenant_id,
                        'user_id' => $assigneeId,
                        'type' => 'automation_followup',
                        'title' => 'Document follow-up required',
                        'body' => $student->full_name.' has pending documents for over 3 days.',
                        'meta_json' => json_encode(['student_id' => $student->id, 'application_id' => $application->id], JSON_UNESCAPED_UNICODE),
                    ]);

                    $text = 'Automation: document follow-up for '.$student->full_name.' (Application #'.$application->id.').';
                    if ($whatsappEnabled) {
                        $this->whatsapp->notifyTenant($auth->tenant_id, 'application_update', $text);
                    }
                    if ($emailEnabled) {
                        $this->communication->sendEmailToTenantStaff(
                            $auth->tenant_id,
                            'Documents Pending Follow-up',
                            $text.' Please contact student immediately.'
                        );
                    }
                    if ($smsEnabled) {
                        $this->communication->sendSmsToTenantStaff($auth->tenant_id, $text);
                    }
                }
            }
        }

        return back()->with('success', 'Automation rules executed.');
    }
}
