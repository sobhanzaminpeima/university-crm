<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Document;
use App\Models\Student;
use App\Models\StudentMessage;
use App\Models\Task;
use App\Models\University;
use App\Models\User;
use App\Services\StudentDocumentService;
use App\Services\UniversityMatchingService;
use App\Services\WhatsappNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    private const STAGE_OPTIONS = ['lead','inquiry','applicant','documents_pending','interview_scheduled','admitted','visa_process','tuition_paid','enrolled','alumni'];
    private const LEAD_SOURCES = ['website_form','landing_page','meta_ads','google_ads','whatsapp','instagram','telegram','email','phone_call','education_fair','referral','walk_in','other'];

    public function __construct(
        private readonly StudentDocumentService $documentService,
        private readonly UniversityMatchingService $matchingService,
        private readonly WhatsappNotificationService $whatsapp
    ) {
    }

    public function index(Request $request): View
    {
        $user = $this->authUser($request);
        $q = (string) $request->query('q', '');
        $stage = (string) $request->query('stage', '');
        $country = (string) $request->query('country', '');
        $gpaMin = $request->query('gpa_min');
        $gpaMax = $request->query('gpa_max');
        $perPage = $this->perPage($request);

        $students = Student::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->whereNull('deleted_at')
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('students.view_all'), function ($query) use ($user) {
                if ($user->role_slug === 'agent') {
                    $query->where('agent_id', $user->id);
                } else {
                    $query->where('sub_agent_id', $user->id);
                }
            })
            ->with(['agent:id,name', 'subAgent:id,name'])
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('full_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('field_of_study', 'like', "%{$q}%");
            }))
            ->when($stage !== '', fn ($query) => $query->where('stage', $stage))
            ->when($country !== '', fn ($query) => $query->where('target_country', 'like', "%{$country}%"))
            ->when($gpaMin !== null && $gpaMin !== '', fn ($query) => $query->where('gpa', '>=', (float) $gpaMin))
            ->when($gpaMax !== null && $gpaMax !== '', fn ($query) => $query->where('gpa', '<=', (float) $gpaMax))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        [$agents, $subAgents] = $this->agentOptions($user);
        $studyFields = collect();
        if (Schema::hasTable('study_fields')) {
            $studyFields = DB::table('study_fields')
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', 1)
                ->orderBy('name')
                ->pluck('name');
        }
        if ($studyFields->isEmpty() && Schema::hasTable('university_programs')) {
            $studyFields = DB::table('university_programs')
                ->where('tenant_id', $user->tenant_id)
                ->whereNotNull('program_name')
                ->where('program_name', '!=', '')
                ->orderBy('program_name')
                ->limit(300)
                ->pluck('program_name')
                ->map(function (string $name) {
                    $parts = preg_split('/[-,(|]/', $name);
                    return trim($parts[0] ?? $name);
                })
                ->filter()
                ->unique()
                ->values();
        }
        if ($studyFields->isEmpty()) {
            $studyFields = collect(['Business', 'Computer Science', 'Engineering', 'Medicine', 'Law', 'Architecture']);
        }

        return view('students.index', compact('students', 'q', 'stage', 'country', 'gpaMin', 'gpaMax', 'perPage', 'agents', 'subAgents', 'studyFields'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $data = $request->validate([
            'full_name' => 'required|string|max:120',
            'email' => [
                'required',
                'email',
                'max:120',
                Rule::unique('students', 'email')->where(function ($query) use ($user) {
                    $query->where('tenant_id', $user->tenant_id)->whereNull('deleted_at');
                }),
            ],
            'account_password' => 'required|string|min:8|max:120',
            'phone' => 'nullable|string|max:30',
            'nationality' => 'nullable|string|max:60',
            'gpa' => 'nullable|numeric|min:0|max:4',
            'field_of_study' => 'nullable|string|max:150',
            'preferred_university_language' => 'nullable|string|in:en,tr',
            'english_level' => 'nullable|string|max:80',
            'stage' => 'required|string|in:lead,inquiry,applicant,documents_pending,interview_scheduled,admitted,visa_process,tuition_paid,enrolled,alumni',
            'lifecycle_stage' => 'nullable|string|in:lead,inquiry,applicant,admitted,enrolled,alumni',
            'target_country' => 'nullable|string|max:60',
            'lead_source' => 'nullable|string|in:website_form,landing_page,meta_ads,google_ads,whatsapp,instagram,telegram,email,phone_call,education_fair,referral,walk_in,other',
            'budget_usd' => 'nullable|numeric|min:0',
            'passport_number' => 'nullable|string|max:40',
            'is_active' => 'nullable|boolean',
            'agent_id' => 'nullable|integer|exists:users,id',
            'sub_agent_id' => 'nullable|integer|exists:users,id',
        ]);
        $this->normalizeOwnerAssignment($user, $data);

        if (!$request->boolean('confirm_duplicate')) {
            $duplicates = Student::query()
                ->forTenant($user->tenant_id, $user->role_slug)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($data) {
                    $q->whereRaw('LOWER(TRIM(full_name)) = ?', [mb_strtolower(trim($data['full_name']))]);
                    if (!empty($data['phone'])) {
                        $q->orWhere('phone', $data['phone']);
                    }
                })
                ->limit(5)
                ->get(['id', 'full_name', 'email', 'phone']);
            if ($duplicates->isNotEmpty()) {
                return back()
                    ->withInput()
                    ->with('duplicate_warning', $duplicates);
            }
        }

        $existingPortalUser = User::query()->where('email', $data['email'])->whereNull('deleted_at')->first();
        if ($existingPortalUser && $existingPortalUser->role_slug !== 'student') {
            return back()->withErrors(['email' => 'Email is already used by another account.'])->withInput();
        }
        $studentUser = $existingPortalUser;
        if (!$studentUser) {
            $studentUser = User::query()->create([
                'tenant_id' => $user->tenant_id,
                'name' => $data['full_name'],
                'email' => $data['email'],
                'password' => Hash::make((string) $data['account_password']),
                'role_slug' => 'student',
                'language' => 'en',
                'is_active' => (int) ($data['is_active'] ?? 1),
            ]);
        } else {
            $studentUser->update([
                'tenant_id' => $user->tenant_id,
                'name' => $data['full_name'],
                'password' => Hash::make((string) $data['account_password']),
                'is_active' => (int) ($data['is_active'] ?? 1),
            ]);
        }

        unset($data['account_password']);
        $data['tenant_id'] = $user->tenant_id;
        if (Schema::hasColumn('students', 'user_id')) {
            $data['user_id'] = $studentUser->id;
        }
        $data['stage_temperature'] = match ($data['stage']) {
            'enrolled', 'alumni' => 'hot',
            'admitted', 'visa_process', 'tuition_paid', 'interview_scheduled', 'documents_pending', 'applicant' => 'warm',
            default => 'cold',
        };
        $data['lifecycle_stage'] = $this->deriveLifecycleStage($data['stage'], (string) ($data['lifecycle_stage'] ?? ''));
        $data['is_active'] = (int) ($data['is_active'] ?? 1);

        $student = Student::query()->create($data);
        $this->audit($request, 'student.create', 'student', $student->id, $data);
        $this->whatsapp->notifyTenant($user->tenant_id, 'new_student', 'New student created: '.$student->full_name);

        return back()->with('success', 'Student created.');
    }

    public function show(Request $request, int $id): View
    {
        $user = $this->authUser($request);
        $student = Student::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->whereNull('deleted_at')
            ->with(['agent:id,name,email', 'subAgent:id,name,email'])
            ->findOrFail($id);
        $this->enforceStudentOwnershipOrFail($user, (int) $student->id, 'students.show');

        $apps = Application::query()->forTenant($user->tenant_id, $user->role_slug)->where('student_id', $student->id)->get();
        $docs = Document::query()->forTenant($user->tenant_id, $user->role_slug)->where('student_id', $student->id)->get();
        $requiredDocs = $this->documentService->requiredRows($user->tenant_id, $student->id);
        $tasks = Task::query()->forTenant($user->tenant_id, $user->role_slug)->where('student_id', $student->id)->get();
        $offerLetters = Document::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('student_id', $student->id)
            ->where('type', 'acceptance_letter')
            ->orderByDesc('id')
            ->get();
        $messages = collect();
        if (Schema::hasTable('student_messages')) {
            $messages = StudentMessage::query()
                ->forTenant($user->tenant_id, $user->role_slug)
                ->where('student_id', $student->id)
                ->latest('id')
                ->limit(50)
                ->get();
        }
        $aiInsights = $this->buildAiInsights($user->tenant_id, $student, $apps, $docs, $tasks);
        $timeline = $this->buildTimeline($user->tenant_id, $student, $apps, $docs, $tasks, $messages);

        return view('students.show', compact('student', 'apps', 'docs', 'requiredDocs', 'tasks', 'messages', 'offerLetters', 'aiInsights', 'timeline'));
    }

    private function buildTimeline(int $tenantId, Student $student, $apps, $docs, $tasks, $messages): \Illuminate\Support\Collection
    {
        $events = collect();

        $events->push([
            'icon' => '🆕',
            'title' => 'Student profile created',
            'body' => null,
            'at' => $student->created_at,
        ]);

        foreach ($apps as $app) {
            $events->push([
                'icon' => '📄',
                'title' => 'Application created: '.$app->program,
                'body' => 'Status: '.ucwords(str_replace('_', ' ', $app->status)),
                'at' => $app->created_at,
            ]);
        }

        foreach ($docs as $doc) {
            $events->push([
                'icon' => '📎',
                'title' => 'Document uploaded: '.ucwords(str_replace('_', ' ', $doc->type)),
                'body' => 'Status: '.ucwords($doc->status).($doc->review_note ? ' — '.$doc->review_note : ''),
                'at' => $doc->created_at,
            ]);
        }

        foreach ($tasks as $task) {
            $events->push([
                'icon' => '✅',
                'title' => 'Task created: '.$task->title,
                'body' => 'Status: '.ucwords(str_replace('_', ' ', $task->status)),
                'at' => $task->created_at,
            ]);
        }

        foreach ($messages as $msg) {
            $events->push([
                'icon' => '💬',
                'title' => $msg->sender_role === 'student' ? 'Message from student' : 'Message sent to student',
                'body' => \Illuminate\Support\Str::limit($msg->body, 160),
                'at' => $msg->created_at,
            ]);
        }

        $auditLogs = \App\Models\AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where('entity_type', 'student')
            ->where('entity_id', $student->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get();
        foreach ($auditLogs as $log) {
            $events->push([
                'icon' => '📝',
                'title' => ucwords(str_replace(['.', '_'], [' — ', ' '], $log->action)),
                'body' => null,
                'at' => $log->created_at,
            ]);
        }

        return $events->sortByDesc('at')->values();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $student = Student::query()->forTenant($user->tenant_id, $user->role_slug)->whereNull('deleted_at')->findOrFail($id);
        $this->enforceStudentOwnershipOrFail($user, (int) $student->id, 'students.update');
        $data = $request->validate([
            'full_name' => 'required|string|max:120',
            'email' => [
                'required',
                'email',
                'max:120',
                Rule::unique('students', 'email')
                    ->ignore($student->id)
                    ->where(function ($query) use ($user) {
                        $query->where('tenant_id', $user->tenant_id)->whereNull('deleted_at');
                    }),
            ],
            'account_password' => 'nullable|string|min:8|max:120',
            'phone' => 'nullable|string|max:30',
            'nationality' => 'nullable|string|max:60',
            'gpa' => 'nullable|numeric|min:0|max:4',
            'field_of_study' => 'nullable|string|max:150',
            'preferred_university_language' => 'nullable|string|in:en,tr',
            'english_level' => 'nullable|string|max:80',
            'stage' => 'required|string|in:lead,inquiry,applicant,documents_pending,interview_scheduled,admitted,visa_process,tuition_paid,enrolled,alumni',
            'lifecycle_stage' => 'nullable|string|in:lead,inquiry,applicant,admitted,enrolled,alumni',
            'target_country' => 'nullable|string|max:60',
            'lead_source' => 'nullable|string|in:website_form,landing_page,meta_ads,google_ads,whatsapp,instagram,telegram,email,phone_call,education_fair,referral,walk_in,other',
            'budget_usd' => 'nullable|numeric|min:0',
            'passport_number' => 'nullable|string|max:40',
            'is_active' => 'nullable|boolean',
            'agent_id' => 'nullable|integer|exists:users,id',
            'sub_agent_id' => 'nullable|integer|exists:users,id',
        ]);
        $this->normalizeOwnerAssignment($user, $data);
        $conflict = User::query()
            ->where('email', $data['email'])
            ->whereNull('deleted_at')
            ->when($student->user_id, fn ($query) => $query->where('id', '!=', $student->user_id))
            ->exists();
        if ($conflict && $data['email'] !== $student->email) {
            return back()->withErrors(['email' => 'Email is already used by another account.'])->withInput();
        }
        $studentUser = null;
        if ($student->user_id) {
            $studentUser = User::query()
                ->where('id', $student->user_id)
                ->where('role_slug', 'student')
                ->first();
        }
        if (!$studentUser) {
            $studentUser = User::query()->create([
                'tenant_id' => $user->tenant_id,
                'name' => $data['full_name'],
                'email' => $data['email'],
                'password' => Hash::make((string) ($data['account_password'] ?: bin2hex(random_bytes(8)))),
                'role_slug' => 'student',
                'language' => 'en',
                'is_active' => (int) ($data['is_active'] ?? $student->is_active ?? 1),
            ]);
            if (Schema::hasColumn('students', 'user_id')) {
                $data['user_id'] = $studentUser->id;
            }
        }

        $userUpdate = [
            'name' => $data['full_name'],
            'email' => $data['email'],
            'is_active' => (int) ($data['is_active'] ?? $student->is_active ?? 1),
        ];
        if (!empty($data['account_password'])) {
            $userUpdate['password'] = Hash::make((string) $data['account_password']);
        }
        $studentUser->update($userUpdate);
        unset($data['account_password']);
        $data['is_active'] = (int) ($data['is_active'] ?? $student->is_active ?? 1);
        $data['stage_temperature'] = match ($data['stage']) {
            'enrolled', 'alumni' => 'hot',
            'admitted', 'visa_process', 'tuition_paid', 'interview_scheduled', 'documents_pending', 'applicant' => 'warm',
            default => 'cold',
        };
        $data['lifecycle_stage'] = $this->deriveLifecycleStage($data['stage'], (string) ($data['lifecycle_stage'] ?? ''));
        $student->update($data);
        $this->audit($request, 'student.update', 'student', $student->id, $data);

        return back()->with('success', 'Student updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $student = Student::query()->forTenant($user->tenant_id, $user->role_slug)->whereNull('deleted_at')->findOrFail($id);
        $this->enforceStudentOwnershipOrFail($user, (int) $student->id, 'students.destroy');
        $student->update(['deleted_at' => now()]);
        if ($student->user_id) {
            User::query()->where('id', $student->user_id)->update([
                'is_active' => 0,
                'deleted_at' => now(),
            ]);
        }
        $this->audit($request, 'student.delete', 'student', $student->id);

        return back()->with('success', 'Student deleted.');
    }

    public function resetPassword(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $student = Student::query()->forTenant($user->tenant_id, $user->role_slug)->whereNull('deleted_at')->findOrFail($id);
        $this->enforceStudentOwnershipOrFail($user, (int) $student->id, 'students.reset_password');

        $data = $request->validate([
            'new_password' => 'required|string|min:8|max:120',
        ]);

        $studentUser = null;
        if ($student->user_id) {
            $studentUser = User::query()->where('id', $student->user_id)->where('role_slug', 'student')->first();
        }
        if (!$studentUser) {
            $studentUser = User::query()->where('email', $student->email)->where('role_slug', 'student')->first();
        }
        if (!$studentUser) {
            return back()->withErrors(['new_password' => 'Student login account not found.']);
        }

        $studentUser->update(['password' => Hash::make((string) $data['new_password'])]);
        $this->audit($request, 'student.reset_password', 'student', $student->id);

        return back()->with('success', 'Student password reset successfully.');
    }

    public function uploadDocument(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $student = Student::query()->forTenant($user->tenant_id, $user->role_slug)->whereNull('deleted_at')->findOrFail($id);
        $this->enforceStudentOwnershipOrFail($user, (int) $student->id, 'students.upload_document');
        $data = $request->validate([
            'type' => 'required|string|in:passport,diploma,transcript,english_certificate,photo,other_documents,payment_receipt,acceptance_letter',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'letter_title' => 'nullable|string|max:190',
            'letter_date' => 'nullable|date',
        ]);

        $path = $request->file('file')->store('docs', 'public');
        $existing = null;
        if ($data['type'] !== 'acceptance_letter') {
            $existing = Document::query()
                ->forTenant($user->tenant_id, $user->role_slug)
                ->where('student_id', $student->id)
                ->where('type', $data['type'])
                ->latest('id')
                ->first();
        }

        $payload = [
            'tenant_id' => $user->tenant_id,
            'student_id' => $student->id,
            'type' => $data['type'],
            'file_url' => '/storage/'.$path,
            'file_name' => $request->file('file')->getClientOriginalName(),
            'status' => 'uploaded',
            'expiry_date' => null,
            'ocr_json' => $data['type'] === 'acceptance_letter'
                ? json_encode([
                    'title' => trim((string) ($data['letter_title'] ?? 'Acceptance Letter')),
                    'letter_date' => $data['letter_date'] ?? null,
                ])
                : null,
        ];
        if ($existing) {
            $existing->update($payload);
        } else {
            Document::query()->create($payload);
        }

        $this->audit($request, 'document.upload', 'student', $student->id, ['type' => $data['type']]);
        $this->whatsapp->notifyTenant($user->tenant_id, 'document_update', 'Document uploaded for '.$student->full_name.' ('.$data['type'].').');

        return back()->with('success', 'Document uploaded successfully.');
    }

    private function deriveLifecycleStage(string $stage, string $explicitLifecycle): string
    {
        $explicitLifecycle = trim($explicitLifecycle);
        if (in_array($explicitLifecycle, ['lead', 'inquiry', 'applicant', 'admitted', 'enrolled', 'alumni'], true)) {
            return $explicitLifecycle;
        }

        return match ($stage) {
            'lead' => 'lead',
            'inquiry' => 'inquiry',
            'applicant', 'documents_pending', 'interview_scheduled' => 'applicant',
            'admitted', 'visa_process', 'tuition_paid' => 'admitted',
            'enrolled' => 'enrolled',
            'alumni' => 'alumni',
            default => 'lead',
        };
    }

    private function buildAiInsights(int $tenantId, Student $student, $apps, $docs, $tasks): array
    {
        $docCount = $docs->count();
        $verifiedDocs = $docs->where('status', 'verified')->count();
        $openTasks = $tasks->whereIn('status', ['todo', 'in_progress', 'blocked'])->count();
        $appCount = $apps->count();
        $acceptedOrEnrolled = $apps->whereIn('status', ['offer_sent', 'tuition_paid', 'enrolled'])->count();
        $gpa = (float) ($student->gpa ?? 0);

        $leadScore = 40;
        $leadScore += $gpa >= 3.2 ? 15 : ($gpa >= 2.6 ? 8 : 2);
        $leadScore += $docCount > 0 ? (int) round(($verifiedDocs / max(1, $docCount)) * 20) : 0;
        $leadScore += min(15, $acceptedOrEnrolled * 7);
        $leadScore -= min(10, $openTasks * 2);
        $leadScore = max(0, min(100, $leadScore));

        $enrollProbability = max(1, min(99, $leadScore + ($student->stage === 'enrolled' ? 15 : 0)));
        $riskScore = max(1, min(99, 100 - $leadScore + ($openTasks >= 3 ? 10 : 0)));

        $summary = 'Student is in '.$student->stage.' stage with '.$appCount.' application(s), '
            .$verifiedDocs.'/'.$docCount.' verified documents, and '.$openTasks.' open task(s).';

        $universities = University::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', 1)
            ->limit(80)
            ->get();
        $ranked = $this->matchingService->rankedForStudent($universities, [
            'field' => $student->field_of_study,
            'country' => $student->target_country,
            'budget' => $student->budget_usd,
            'language' => $student->preferred_university_language === 'tr' ? 'turkish' : 'english',
            'gpa' => $student->gpa,
        ])->take(3)->values();

        return [
            'lead_score' => $leadScore,
            'enroll_probability' => $enrollProbability,
            'risk_score' => $riskScore,
            'summary' => $summary,
            'recommended_universities' => $ranked,
        ];
    }

    public function viewDocument(Request $request, int $id, int $documentId)
    {
        $user = $this->authUser($request);
        $student = Student::query()->forTenant($user->tenant_id, $user->role_slug)->whereNull('deleted_at')->findOrFail($id);
        $this->enforceStudentOwnershipOrFail($user, (int) $student->id, 'students.view_document');
        $document = Document::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('student_id', $student->id)
            ->findOrFail($documentId);

        $relative = ltrim((string) str_replace('/storage/', '', (string) $document->file_url), '/');
        if (!Storage::disk('public')->exists($relative)) {
            abort(404, 'Document file not found on server.');
        }

        if ($request->boolean('download')) {
            return Storage::disk('public')->download($relative, $document->file_name ?: basename($relative));
        }

        return response()->file(Storage::disk('public')->path($relative));
    }

    public function verifyDocument(Request $request, int $id, int $documentId): RedirectResponse
    {
        $user = $this->authUser($request);
        $student = Student::query()->forTenant($user->tenant_id, $user->role_slug)->whereNull('deleted_at')->findOrFail($id);
        $this->enforceStudentOwnershipOrFail($user, (int) $student->id, 'students.verify_document');
        $document = Document::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('student_id', $student->id)
            ->findOrFail($documentId);

        $document->update(['status' => 'verified', 'review_note' => null]);
        $this->audit($request, 'document.verify', 'document', $document->id, ['status' => 'verified']);
        $this->whatsapp->notifyTenant($user->tenant_id, 'document_update', 'Document verified for '.$student->full_name.' ('.$document->type.').');

        return back()->with('success', 'Document verified.');
    }

    public function rejectDocument(Request $request, int $id, int $documentId): RedirectResponse
    {
        $user = $this->authUser($request);
        $student = Student::query()->forTenant($user->tenant_id, $user->role_slug)->whereNull('deleted_at')->findOrFail($id);
        $this->enforceStudentOwnershipOrFail($user, (int) $student->id, 'students.verify_document');
        $document = Document::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('student_id', $student->id)
            ->findOrFail($documentId);

        $data = $request->validate([
            'review_note' => 'required|string|max:500',
        ]);

        $document->update(['status' => 'rejected', 'review_note' => $data['review_note']]);
        $this->audit($request, 'document.reject', 'document', $document->id, ['status' => 'rejected', 'note' => $data['review_note']]);
        $this->whatsapp->notifyTenant($user->tenant_id, 'document_update', 'Document rejected for '.$student->full_name.' ('.$document->type.'): '.$data['review_note']);

        return back()->with('success', 'Document rejected with feedback.');
    }

    public function deleteDocument(Request $request, int $id, int $documentId): RedirectResponse
    {
        $user = $this->authUser($request);
        $student = Student::query()->forTenant($user->tenant_id, $user->role_slug)->whereNull('deleted_at')->findOrFail($id);
        $this->enforceStudentOwnershipOrFail($user, (int) $student->id, 'students.delete_document');
        $document = Document::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('student_id', $student->id)
            ->findOrFail($documentId);

        $document->delete();
        $this->audit($request, 'document.delete', 'document', $documentId);
        $this->whatsapp->notifyTenant($user->tenant_id, 'document_update', 'Document deleted for '.$student->full_name.' ('.$document->type.').');

        return back()->with('success', 'Document deleted.');
    }

    private function agentOptions(User $user): array
    {
        $hasParentUser = Schema::hasColumn('users', 'parent_user_id');
        if ($user->role_slug === 'agent') {
            $agents = User::query()
                ->forTenant($user->tenant_id, $user->role_slug)
                ->where('id', $user->id)
                ->get(['id', 'name']);

            $subAgents = collect();
            if ($hasParentUser) {
                $subAgents = User::query()
                    ->forTenant($user->tenant_id, $user->role_slug)
                    ->where('role_slug', 'sub_agent')
                    ->where('parent_user_id', $user->id)
                    ->whereNull('deleted_at')
                    ->orderBy('name')
                    ->get(['id', 'name']);
            }

            return [$agents, $subAgents];
        }

        $agents = User::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('role_slug', 'agent')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $subAgents = User::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('role_slug', 'sub_agent')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get($hasParentUser ? ['id', 'name', 'parent_user_id'] : ['id', 'name']);

        return [$agents, $subAgents];
    }

    private function normalizeOwnerAssignment(User $authUser, array &$data): void
    {
        if ($authUser->role_slug === 'agent') {
            $data['agent_id'] = $authUser->id;
        }

        if (!empty($data['sub_agent_id'])) {
            $subAgent = User::query()
                ->forTenant($authUser->tenant_id, $authUser->role_slug)
                ->where('id', (int) $data['sub_agent_id'])
                ->where('role_slug', 'sub_agent')
                ->whereNull('deleted_at')
                ->first();
            if (!$subAgent) {
                abort(422, 'Invalid sub-agent selected.');
            }

            if ($authUser->role_slug === 'agent' && (int) ($subAgent->parent_user_id ?? 0) !== (int) $authUser->id) {
                abort(422, 'Selected sub-agent is not assigned to this agent.');
            }

            if (empty($data['agent_id']) && !empty($subAgent->parent_user_id)) {
                $data['agent_id'] = (int) $subAgent->parent_user_id;
            }
        }

        if (!empty($data['agent_id'])) {
            $agent = User::query()
                ->forTenant($authUser->tenant_id, $authUser->role_slug)
                ->where('id', (int) $data['agent_id'])
                ->where('role_slug', 'agent')
                ->whereNull('deleted_at')
                ->first();
            if (!$agent) {
                abort(422, 'Invalid agent selected.');
            }
        } else {
            $data['agent_id'] = null;
        }
    }
}

