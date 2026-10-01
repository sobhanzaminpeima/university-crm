<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PipelineController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authUser($request);
        $students = Student::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->whereNull('deleted_at')
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('students.view_all'), fn ($query) => $this->applyStudentOwnershipScope($query, $user))
            ->get();
        $settings = $this->pipelineSettings($user->tenant_id, $user->id);
        $stageOrder = $settings['stage_order'] ?? ['lead', 'inquiry', 'applicant', 'documents_pending', 'interview_scheduled', 'admitted', 'visa_process', 'tuition_paid', 'enrolled', 'alumni'];
        $hidden = collect($settings['hidden_stages'] ?? [])->filter()->values()->all();
        $cardOrder = $settings['card_order'] ?? [];
        $columns = [];
        foreach ($stageOrder as $stage) {
            if (in_array($stage, $hidden, true)) {
                continue;
            }
            $items = $students->where('stage', $stage)->values();
            if (isset($cardOrder[$stage]) && is_array($cardOrder[$stage])) {
                $orderMap = array_flip(array_map('intval', $cardOrder[$stage]));
                $items = $items->sortBy(fn ($student) => $orderMap[(int) $student->id] ?? 999999)->values();
            }
            $columns[$stage] = $items;
        }

        return view('pipeline.index', ['columns' => $columns, 'settings' => $settings]);
    }

    public function move(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        $data = $request->validate([
            'student_id' => 'required|integer',
            'stage' => 'required|in:lead,inquiry,applicant,documents_pending,interview_scheduled,admitted,visa_process,tuition_paid,enrolled,alumni',
        ]);

        $student = Student::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->whereNull('deleted_at')
            ->findOrFail((int) $data['student_id']);
        $this->enforceStudentOwnershipOrFail($user, (int) $student->id, 'pipeline.move');

        $stage = $data['stage'];
        $student->update([
            'stage' => $stage,
            'lifecycle_stage' => match ($stage) {
                'lead' => 'lead',
                'inquiry' => 'inquiry',
                'applicant', 'documents_pending', 'interview_scheduled' => 'applicant',
                'admitted', 'visa_process', 'tuition_paid' => 'admitted',
                'enrolled' => 'enrolled',
                'alumni' => 'alumni',
                default => 'lead',
            },
            'stage_temperature' => match ($stage) {
                'enrolled', 'alumni' => 'hot',
                'admitted', 'visa_process', 'tuition_paid', 'interview_scheduled', 'documents_pending', 'applicant' => 'warm',
                default => 'cold',
            },
        ]);
        $this->audit($request, 'student.move_stage', 'student', $student->id, ['stage' => $stage]);
        if ($request->has('card_order') && is_array($request->input('card_order'))) {
            $this->savePipelineSettings($user->tenant_id, $user->id, [
                'card_order' => $request->input('card_order'),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function savePreferences(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        $data = $request->validate([
            'stage_order' => 'nullable|array',
            'stage_order.*' => 'string',
            'hidden_stages' => 'nullable|array',
            'hidden_stages.*' => 'string',
            'filters' => 'nullable|array',
            'card_order' => 'nullable|array',
        ]);
        $this->savePipelineSettings($user->tenant_id, $user->id, $data);
        return response()->json(['ok' => true]);
    }

    private function pipelineSettings(int $tenantId, int $userId): array
    {
        if (!Schema::hasTable('user_pipeline_settings')) {
            return [];
        }
        $raw = DB::table('user_pipeline_settings')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->value('settings_json');
        if (!$raw) {
            return [];
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function savePipelineSettings(int $tenantId, int $userId, array $delta): void
    {
        if (!Schema::hasTable('user_pipeline_settings')) {
            return;
        }
        $current = $this->pipelineSettings($tenantId, $userId);
        $merged = array_merge($current, $delta);
        DB::table('user_pipeline_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'user_id' => $userId],
            ['settings_json' => json_encode($merged, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
