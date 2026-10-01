<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\University;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authUser($request);
        $status = (string) $request->query('status', 'pending');
        $perPage = $this->perPage($request);
        $baseQuery = StudentRequest::query()->forTenant($user->tenant_id, $user->role_slug);
        if (in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('student_requests.view_all')) {
            $baseQuery->whereIn('email', function ($sub) use ($user) {
                $sub->select('email')
                    ->from('students')
                    ->where('tenant_id', $user->tenant_id)
                    ->whereNull('deleted_at')
                    ->when($user->role_slug === 'agent', fn ($q) => $q->where('agent_id', $user->id))
                    ->when($user->role_slug === 'sub_agent', fn ($q) => $q->where('sub_agent_id', $user->id));
            });
        }
        $pendingCount = (clone $baseQuery)->where('status', 'pending')->count();
        $processedCount = (clone $baseQuery)->whereIn('status', ['approved', 'rejected'])->count();
        $requests = (clone $baseQuery)
            ->when($status === 'processed', fn ($q) => $q->whereIn('status', ['approved', 'rejected']), fn ($q) => $q->where('status', 'pending'))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
        $agents = User::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->whereNull('deleted_at')
            ->whereIn('role_slug', ['admin', 'agent', 'sub_agent'])
            ->orderBy('name')
            ->get(['id', 'name', 'role_slug']);

        return view('requests.index', compact('requests', 'status', 'pendingCount', 'processedCount', 'agents', 'perPage'));
    }

    public function show(Request $request, int $id): View
    {
        $user = $this->authUser($request);
        $query = StudentRequest::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('id', $id);
        if (in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('student_requests.view_all')) {
            $query->whereIn('email', function ($sub) use ($user) {
                $sub->select('email')
                    ->from('students')
                    ->where('tenant_id', $user->tenant_id)
                    ->whereNull('deleted_at')
                    ->when($user->role_slug === 'agent', fn ($q) => $q->where('agent_id', $user->id))
                    ->when($user->role_slug === 'sub_agent', fn ($q) => $q->where('sub_agent_id', $user->id));
            });
        }
        $requestItem = $query->firstOrFail();

        return view('requests.show', compact('requestItem'));
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $query = StudentRequest::query()->forTenant($user->tenant_id, $user->role_slug)->where('status', 'pending')->where('id', $id);
        if (in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('student_requests.view_all')) {
            $query->whereIn('email', function ($sub) use ($user) {
                $sub->select('email')
                    ->from('students')
                    ->where('tenant_id', $user->tenant_id)
                    ->whereNull('deleted_at')
                    ->when($user->role_slug === 'agent', fn ($q) => $q->where('agent_id', $user->id))
                    ->when($user->role_slug === 'sub_agent', fn ($q) => $q->where('sub_agent_id', $user->id));
            });
        }
        $requestItem = $query->firstOrFail();
        $payload = $request->validate([
            'assigned_to' => 'nullable|integer|exists:users,id',
            'note' => 'nullable|string|max:3000',
        ]);

        $assignedAgentId = null;
        if (!empty($payload['assigned_to'])) {
            $assignedAgent = User::query()
                ->forTenant($user->tenant_id, $user->role_slug)
                ->whereIn('role_slug', ['agent', 'sub_agent'])
                ->whereNull('deleted_at')
                ->find((int) $payload['assigned_to']);
            if (!$assignedAgent) {
                abort(422, 'Invalid agent selected.');
            }
            $assignedAgentId = $assignedAgent->id;
        }

        $student = Student::query()->create([
            'tenant_id' => $user->tenant_id,
            'agent_id' => $assignedAgentId,
            'full_name' => $requestItem->full_name,
            'email' => $requestItem->email,
            'phone' => $requestItem->phone,
            'nationality' => $requestItem->nationality,
            'field_of_study' => $requestItem->target_program,
            'stage' => 'lead',
            'stage_temperature' => 'warm',
            'is_active' => 1,
        ]);

        $requestedUniversityName = null;
        if (!empty($requestItem->target_program) && str_contains((string) $requestItem->target_program, '@')) {
            $parts = explode('@', (string) $requestItem->target_program);
            $requestedUniversityName = trim((string) end($parts));
        }
        $defaultUniversityId = University::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($requestedUniversityName, fn ($query) => $query->where('name', 'like', '%'.$requestedUniversityName.'%'))
            ->orderBy('id')
            ->value('id');

        Application::query()->create([
            'tenant_id' => $user->tenant_id,
            'student_id' => $student->id,
            'university_id' => $defaultUniversityId ?: 1,
            'program' => $requestItem->target_program ?: 'General Admission',
            'intake' => date('Y').'-Fall',
            'status' => 'draft',
            'notes' => 'Auto-created from student request approval.'.(!empty($payload['assigned_to']) ? ' Assigned to agent ID '.$payload['assigned_to'].'.' : ''),
            'enroll_probability' => 40,
            'best_next_action' => 'Collect all required documents',
            'explainability' => 'Initial request approved and case opened.',
            'last_activity_at' => now(),
        ]);

        $requestItem->update([
            'status' => 'approved',
            'processed_by' => $user->id,
            'processed_at' => now(),
            'review_note' => (string) ($payload['note'] ?? 'Approved from CRM queue.'),
        ]);
        $this->audit($request, 'student_request.approve', 'student_request', $requestItem->id);

        return back()->with('success', 'Request approved and student profile created.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $query = StudentRequest::query()->forTenant($user->tenant_id, $user->role_slug)->where('status', 'pending')->where('id', $id);
        if (in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('student_requests.view_all')) {
            $query->whereIn('email', function ($sub) use ($user) {
                $sub->select('email')
                    ->from('students')
                    ->where('tenant_id', $user->tenant_id)
                    ->whereNull('deleted_at')
                    ->when($user->role_slug === 'agent', fn ($q) => $q->where('agent_id', $user->id))
                    ->when($user->role_slug === 'sub_agent', fn ($q) => $q->where('sub_agent_id', $user->id));
            });
        }
        $requestItem = $query->firstOrFail();
        $requestItem->update([
            'status' => 'rejected',
            'processed_by' => $user->id,
            'processed_at' => now(),
            'review_note' => (string) $request->input('note', 'Rejected by admin review.'),
        ]);
        $this->audit($request, 'student_request.reject', 'student_request', $requestItem->id);

        return back()->with('success', 'Request rejected.');
    }
}
