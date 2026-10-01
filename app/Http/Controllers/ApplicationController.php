<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Student;
use App\Models\University;
use App\Services\WhatsappNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly WhatsappNotificationService $whatsapp
    ) {
    }

    public function index(Request $request): View
    {
        $user = $this->authUser($request);
        $status = (string) $request->query('status', '');
        $q = (string) $request->query('q', '');
        $universitySort = (string) $request->query('university_sort', 'name_asc');
        $perPage = $this->perPage($request);

        $applications = Application::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('applications.view_all'), function ($query) use ($user) {
                $query->whereIn('applications.student_id', function ($sub) use ($user) {
                    $sub->select('id')
                        ->from('students')
                        ->where('tenant_id', $user->tenant_id)
                        ->when($user->role_slug === 'agent', fn ($q) => $q->where('agent_id', $user->id))
                        ->when($user->role_slug === 'sub_agent', fn ($q) => $q->where('sub_agent_id', $user->id));
                });
            })
            ->leftJoin('students', 'students.id', '=', 'applications.student_id')
            ->leftJoin('universities', 'universities.id', '=', 'applications.university_id')
            ->select([
                'applications.*',
                DB::raw('students.full_name as student_name'),
                DB::raw('universities.name as university_name'),
            ])
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('students.full_name', 'like', "%{$q}%")
                    ->orWhere('applications.program', 'like', "%{$q}%")
                    ->orWhere('universities.name', 'like', "%{$q}%")
                    ->orWhere('applications.notes', 'like', "%{$q}%");
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $students = Student::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->whereNull('deleted_at')
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('students.view_all'), fn ($query) => $this->applyStudentOwnershipScope($query, $user))
            ->orderBy('full_name')
            ->get();
        $universitiesQuery = University::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('is_active', 1)
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('universities.view_all'), fn ($query) => $query->where('created_by_user_id', $user->id));
        if ($universitySort === 'name_desc') {
            $universitiesQuery->orderByDesc('name');
        } elseif ($universitySort === 'created_asc') {
            $universitiesQuery->orderBy('created_at')->orderBy('id');
        } else {
            $universitiesQuery->orderBy('name');
        }
        $universities = $universitiesQuery->get();
        $countryOptions = University::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('universities.view_all'), fn ($query) => $query->where('created_by_user_id', $user->id))
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country')
            ->values()
            ->all();
        if (empty($countryOptions)) {
            $countryOptions = ['Turkey', 'Northern Cyprus'];
        } else {
            if (!in_array('Turkey', $countryOptions, true)) {
                $countryOptions[] = 'Turkey';
            }
            if (!in_array('Northern Cyprus', $countryOptions, true)) {
                $countryOptions[] = 'Northern Cyprus';
            }
            sort($countryOptions);
        }
        $intakeTerms = collect();
        if (Schema::hasTable('intake_terms')) {
            $intakeTerms = DB::table('intake_terms')
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', 1)
                ->orderBy('name')
                ->pluck('name');
        }

        return view('applications.index', compact('applications', 'students', 'universities', 'countryOptions', 'intakeTerms', 'status', 'q', 'universitySort', 'perPage'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $data = $request->validate([
            'student_id' => 'required|integer',
            'university_id' => 'required|integer',
            'program' => 'required|string|max:160',
            'intake' => 'required|string|max:80',
            'status' => 'required|string|in:new_lead,interested,application_started,documents_pending,interview_scheduled,offer_sent,visa_process,tuition_paid,enrolled,rejected',
            'deadline' => 'nullable|date',
            'next_followup_at' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
        ]);
        $data['tenant_id'] = $user->tenant_id;
        $data['last_activity_at'] = now();
        [$data['enroll_probability'], $data['explainability'], $data['best_next_action']] = $this->score($data);
        $this->enforceStudentOwnershipOrFail($user, (int) $data['student_id'], 'applications.store');

        $duplicate = Application::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('student_id', (int) $data['student_id'])
            ->where('university_id', (int) $data['university_id'])
            ->whereRaw('LOWER(TRIM(program)) = ?', [mb_strtolower(trim((string) $data['program']))])
            ->whereRaw('LOWER(TRIM(intake)) = ?', [mb_strtolower(trim((string) $data['intake']))])
            ->first();
        if ($duplicate) {
            return back()->withErrors([
                'program' => 'This student already has an application for the same university, program and intake.',
            ])->withInput();
        }

        $application = Application::query()->create($data);
        $this->audit($request, 'application.create', 'application', $application->id, $data);
        $student = Student::query()->where('tenant_id', $user->tenant_id)->find($application->student_id);
        $this->whatsapp->notifyTenant(
            $user->tenant_id,
            'application_update',
            'Application created: '.($student?->full_name ?? 'Student').' / '.$application->program.' ('.$application->status.')'
        );

        return back()->with('success', 'Application created.');
    }

    public function show(Request $request, int $id): View
    {
        $user = $this->authUser($request);
        $application = Application::query()->forTenant($user->tenant_id, $user->role_slug)->findOrFail($id);
        $this->enforceStudentOwnershipOrFail($user, (int) $application->student_id, 'applications.show');
        $student = Student::query()->forTenant($user->tenant_id, $user->role_slug)->find($application->student_id);
        $university = University::query()->forTenant($user->tenant_id, $user->role_slug)->find($application->university_id);
        $students = Student::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->whereNull('deleted_at')
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('students.view_all'), fn ($query) => $this->applyStudentOwnershipScope($query, $user))
            ->orderBy('full_name')
            ->get();
        $universities = University::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('is_active', 1)
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('universities.view_all'), fn ($query) => $query->where('created_by_user_id', $user->id))
            ->orderBy('name')
            ->get();
        $intakeTerms = collect();
        if (Schema::hasTable('intake_terms')) {
            $intakeTerms = DB::table('intake_terms')
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', 1)
                ->orderBy('name')
                ->pluck('name');
        }
        $requirements = \App\Models\DocumentRequirement::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('university_id', $application->university_id)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
        $studentDocsByType = \App\Models\Document::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('student_id', $application->student_id)
            ->latest('id')
            ->get()
            ->unique('type')
            ->keyBy('type');
        $checklist = $requirements->map(function ($req) use ($studentDocsByType) {
            $doc = $studentDocsByType->get($req->doc_type);
            return (object) [
                'requirement' => $req,
                'document' => $doc,
                'status' => $doc ? $doc->status : 'missing',
            ];
        });

        $visaCase = \App\Models\VisaCase::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('application_id', $application->id)
            ->first();

        return view('applications.show', compact('application', 'student', 'university', 'students', 'universities', 'intakeTerms', 'checklist', 'visaCase'));
    }

    public function saveVisaCase(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $application = Application::query()->forTenant($user->tenant_id, $user->role_slug)->findOrFail($id);
        $data = $request->validate([
            'visa_type' => 'nullable|string|max:60',
            'submission_date' => 'nullable|date',
            'embassy_appointment_date' => 'nullable|date',
            'interview_date' => 'nullable|date',
            'decision' => 'required|string|in:pending,approved,rejected,in_process',
            'decision_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        \App\Models\VisaCase::query()->updateOrCreate(
            ['tenant_id' => $user->tenant_id, 'application_id' => $application->id],
            array_merge($data, ['student_id' => $application->student_id])
        );

        $this->audit($request, 'visa_case.save', 'application', $application->id, $data);

        return back()->with('success', 'Visa case updated.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $application = Application::query()->forTenant($user->tenant_id, $user->role_slug)->findOrFail($id);
        $data = $request->validate([
            'student_id' => 'required|integer',
            'university_id' => 'required|integer',
            'program' => 'required|string|max:160',
            'intake' => 'required|string|max:80',
            'status' => 'required|string|in:new_lead,interested,application_started,documents_pending,interview_scheduled,offer_sent,visa_process,tuition_paid,enrolled,rejected',
            'deadline' => 'nullable|date',
            'next_followup_at' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
        ]);
        $this->enforceStudentOwnershipOrFail($user, (int) $application->student_id, 'applications.update.current');
        $this->enforceStudentOwnershipOrFail($user, (int) $data['student_id'], 'applications.update.target');
        [$data['enroll_probability'], $data['explainability'], $data['best_next_action']] = $this->score($data);
        $data['last_activity_at'] = now();
        $application->update($data);
        $this->audit($request, 'application.update', 'application', $application->id, $data);
        $student = Student::query()->where('tenant_id', $user->tenant_id)->find($application->student_id);
        $this->whatsapp->notifyTenant(
            $user->tenant_id,
            'application_update',
            'Application updated: '.($student?->full_name ?? 'Student').' / '.$application->program.' ('.$application->status.')'
        );

        return back()->with('success', 'Application updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $application = Application::query()->forTenant($user->tenant_id, $user->role_slug)->findOrFail($id);
        $this->enforceStudentOwnershipOrFail($user, (int) $application->student_id, 'applications.destroy');
        $application->delete();
        $this->audit($request, 'application.delete', 'application', $id);

        return back()->with('success', 'Application deleted.');
    }

    private function score(array $data): array
    {
        $score = 50;
        $reason = ['Base score = 50'];

        if (in_array($data['status'], ['submitted', 'under_review'], true)) {
            $score += 10;
            $reason[] = 'Application active in pipeline (+10)';
        }
        if (in_array($data['status'], ['accepted', 'enrolled'], true)) {
            $score += 25;
            $reason[] = 'Offer/Enroll stage (+25)';
        }
        if (!empty($data['deadline'])) {
            $days = now()->diffInDays($data['deadline'], false);
            if ($days < 0) {
                $score -= 20;
                $reason[] = 'Deadline passed (-20)';
            }
            if ($days >= 0 && $days <= 7) {
                $score += 5;
                $reason[] = 'Near-term deadline keeps momentum (+5)';
            }
        }

        $score = max(0, min(99, $score));
        $nextAction = $score < 55 ? 'Follow up with student and collect missing docs' : 'Push university communication and fee confirmation';

        return [$score, implode(' | ', $reason), $nextAction];
    }

    public function universitiesByCountry(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        $country = trim((string) $request->query('country', ''));
        $sort = trim((string) $request->query('sort', 'name_asc'));
        $rows = University::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->when($country !== '', fn ($query) => $query->where('country', $country))
            ->where('is_active', 1)
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('universities.view_all'), fn ($query) => $query->where('created_by_user_id', $user->id))
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('name'))
            ->when($sort === 'created_asc', fn ($query) => $query->orderBy('created_at')->orderBy('id'))
            ->when($sort === 'created_desc', fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->when(!in_array($sort, ['name_desc', 'created_asc', 'created_desc'], true), fn ($query) => $query->orderBy('name'))
            ->get(['id', 'name', 'country']);
        return response()->json($rows);
    }

    public function programsByUniversity(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        $universityId = (int) $request->query('university_id', 0);
        $degree = trim((string) $request->query('degree', ''));
        $thesisType = trim((string) $request->query('thesis_type', ''));
        $programLanguage = trim((string) $request->query('program_language', ''));
        if ($universityId < 1 || !Schema::hasTable('university_programs')) {
            return response()->json([]);
        }
        $hasThesisColumn = Schema::hasColumn('university_programs', 'thesis_type');
        $query = DB::table('university_programs')
            ->where('tenant_id', $user->tenant_id)
            ->where('university_id', $universityId)
            ->whereExists(function ($sub) use ($user) {
                $sub->selectRaw('1')
                    ->from('universities')
                    ->whereColumn('universities.id', 'university_programs.university_id')
                    ->where('universities.tenant_id', $user->tenant_id)
                    ->where('universities.is_active', 1)
                    ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('universities.view_all'), fn ($q) => $q->where('universities.created_by_user_id', $user->id));
            })
            ->when($degree !== '', fn ($q) => $q->where('degree_level', $degree))
            ->when($hasThesisColumn && in_array($thesisType, ['thesis', 'non_thesis', 'both'], true), fn ($q) => $q->where(function ($sub) use ($thesisType) {
                $sub->where('thesis_type', $thesisType);
                if ($thesisType !== 'both') {
                    $sub->orWhere('thesis_type', 'both');
                }
            }))
            ->when(in_array($programLanguage, ['English', 'Turkish', 'Arabic', 'Both'], true), fn ($q) => $q->where('language', $programLanguage))
            ->orderBy('program_name')
            ->distinct()
            ->limit(500);
        $select = ['program_name', 'degree_level'];
        if ($hasThesisColumn) {
            $select[] = 'thesis_type';
        }
        $rows = $query->get($select);
        return response()->json($rows);
    }

}
