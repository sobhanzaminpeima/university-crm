<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $auth = $this->authUser($request);
        $status = (string) $request->query('status', '');
        $priority = (string) $request->query('priority', '');
        $assignedTo = (string) $request->query('assigned_to', '');
        $perPage = $this->perPage($request);

        $taskQuery = Task::query()
            ->forTenant($auth->tenant_id, $auth->role_slug);
        $this->applyTaskVisibilityScope($taskQuery, $auth);

        $tasks = $taskQuery
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
            ->when($assignedTo !== '' && $this->canViewAllTasks($auth), fn ($query) => $query->where('assigned_to', (int) $assignedTo))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $students = Student::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->whereNull('deleted_at')
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        $agents = $this->taskAssigneeOptions($auth);
        $taskRows = $tasks->getCollection();
        $userIdsForDisplay = collect($agents)->pluck('id')
            ->merge($taskRows->pluck('assigned_to'))
            ->merge($taskRows->pluck('created_by_user_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $userMap = User::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->whereNull('deleted_at')
            ->whereIn('id', $userIdsForDisplay)
            ->orderBy('name')
            ->get(['id', 'name', 'role_slug'])
            ->keyBy('id');
        $canViewAllTasks = $this->canViewAllTasks($auth);
        $currentUserId = (int) $auth->id;

        return view('tasks.index', compact('tasks', 'students', 'agents', 'userMap', 'status', 'priority', 'assignedTo', 'perPage', 'canViewAllTasks', 'currentUserId'));
    }

    public function show(Request $request, int $id): View
    {
        $auth = $this->authUser($request);
        $task = Task::query()->forTenant($auth->tenant_id, $auth->role_slug)->findOrFail($id);
        $this->authorizeTaskAction($auth, $task, 'view');

        $student = null;
        if ($task->student_id) {
            $student = Student::query()->forTenant($auth->tenant_id, $auth->role_slug)->find($task->student_id);
        }
        $assignee = User::query()->forTenant($auth->tenant_id, $auth->role_slug)->find($task->assigned_to);
        $creator = $this->taskHasCreatorColumn()
            ? User::query()->forTenant($auth->tenant_id, $auth->role_slug)->find($task->created_by_user_id)
            : null;

        return view('tasks.show', compact('task', 'student', 'assignee', 'creator'));
    }

    public function store(Request $request): RedirectResponse
    {
        $auth = $this->authUser($request);
        $data = $request->validate([
            'title' => 'required|string|max:190',
            'description' => 'nullable|string|max:5000',
            'assigned_to' => 'required|integer|exists:users,id',
            'student_id' => 'nullable|integer|exists:students,id',
            'deadline' => 'nullable|date',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'required|in:todo,in_progress,completed,blocked',
        ]);
        $this->authorizeAssignee($auth, (int) $data['assigned_to']);
        $data['tenant_id'] = $auth->tenant_id;
        $data['escalation_level'] = 0;
        if ($this->taskHasCreatorColumn()) {
            $data['created_by_user_id'] = $auth->id;
        }

        $task = Task::query()->create($data);
        $this->audit($request, 'task.create', 'task', $task->id, $data);

        return back()->with('success', 'Task created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $auth = $this->authUser($request);
        $task = Task::query()->forTenant($auth->tenant_id, $auth->role_slug)->findOrFail($id);
        $this->authorizeTaskAction($auth, $task, 'manage');
        $data = $request->validate([
            'title' => 'required|string|max:190',
            'description' => 'nullable|string|max:5000',
            'assigned_to' => 'required|integer|exists:users,id',
            'student_id' => 'nullable|integer|exists:students,id',
            'deadline' => 'nullable|date',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'required|in:todo,in_progress,completed,blocked',
        ]);
        $this->authorizeAssignee($auth, (int) $data['assigned_to']);
        $task->update($data);
        $this->audit($request, 'task.update', 'task', $task->id, $data);

        return back()->with('success', 'Task updated.');
    }

    public function markComplete(Request $request, int $id): RedirectResponse
    {
        $auth = $this->authUser($request);
        $task = Task::query()->forTenant($auth->tenant_id, $auth->role_slug)->findOrFail($id);
        $this->authorizeTaskAction($auth, $task, 'complete');
        $task->update(['status' => 'completed']);
        $this->audit($request, 'task.complete', 'task', $task->id, ['status' => 'completed']);

        return back()->with('success', 'Task marked as complete.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $auth = $this->authUser($request);
        $task = Task::query()->forTenant($auth->tenant_id, $auth->role_slug)->findOrFail($id);
        $this->authorizeTaskAction($auth, $task, 'manage');
        $task->delete();
        $this->audit($request, 'task.delete', 'task', $id);

        return back()->with('success', 'Task deleted.');
    }

    private function applyTaskVisibilityScope($query, User $auth): void
    {
        if ($this->canViewAllTasks($auth)) {
            return;
        }

        $query->where(function ($scope) use ($auth) {
            $scope->where('assigned_to', $auth->id);
            if ($this->taskHasCreatorColumn()) {
                $scope->orWhere('created_by_user_id', $auth->id);
            }
        });
    }

    private function authorizeTaskAction(User $auth, Task $task, string $action): void
    {
        if ($this->canViewAllTasks($auth)) {
            return;
        }

        $isAssignee = (int) $task->assigned_to === (int) $auth->id;
        $isCreator = $this->taskHasCreatorColumn() && (int) ($task->created_by_user_id ?? 0) === (int) $auth->id;

        if ($action === 'view' && ($isAssignee || $isCreator)) {
            return;
        }
        if ($action === 'complete' && ($isAssignee || $isCreator)) {
            return;
        }
        if ($action === 'manage' && ($isCreator || (!$this->taskHasCreatorColumn() && $isAssignee))) {
            return;
        }

        abort(403, 'You do not have access to this task.');
    }

    private function canViewAllTasks(User $auth): bool
    {
        return in_array($auth->role_slug, ['super_admin', 'admin'], true) || $auth->hasPermission('tasks.view_all');
    }

    private function taskAssigneeOptions(User $auth)
    {
        $query = User::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->whereNull('deleted_at')
            ->whereIn('role_slug', ['admin', 'agent', 'sub_agent']);

        if (!$this->canViewAllTasks($auth)) {
            if ($auth->role_slug === 'agent') {
                $query->where(function ($scope) use ($auth) {
                    $scope->where('id', $auth->id)
                        ->orWhere(function ($sub) use ($auth) {
                            $sub->where('role_slug', 'sub_agent')
                                ->where('parent_user_id', $auth->id);
                        });
                });
            } else {
                $query->where('id', $auth->id);
            }
        }

        return $query->orderBy('name')->get(['id', 'name', 'role_slug']);
    }

    private function authorizeAssignee(User $auth, int $assignedTo): void
    {
        if ($assignedTo < 1) {
            abort(422, 'Invalid assignee.');
        }
        $allowed = $this->taskAssigneeOptions($auth)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (!in_array($assignedTo, $allowed, true)) {
            abort(403, 'You cannot assign tasks to this user.');
        }
    }

    private function taskHasCreatorColumn(): bool
    {
        return Schema::hasColumn('tasks', 'created_by_user_id');
    }
}
