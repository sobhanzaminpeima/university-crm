<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Document;
use App\Models\Notification;
use App\Models\StudentRequest;
use App\Models\StudentMessage;
use App\Models\Student;
use App\Models\University;
use App\Models\User;
use App\Models\Tenant;
use App\Services\StudentDocumentService;
use App\Services\UniversityMatchingService;
use App\Services\WhatsappNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PortalWebController extends Controller
{
    private const DEGREE_ALIASES = [
        'Diploma' => ['Diploma', 'High School Diploma', 'دیپلم'],
        'Associate' => ['Associate', 'Associate Degree', 'فوق دیپلم', 'کاردانی'],
        'Bachelor' => ['Bachelor', 'BSc', 'BA', 'کارشناسی', 'لیسانس'],
        'Master' => ['Master', 'MSc', 'MA', 'کارشناسی ارشد', 'فوق لیسانس'],
        'PhD' => ['PhD', 'Doctorate', 'دکتری', 'دکترا'],
    ];

    public function __construct(
        private readonly StudentDocumentService $documentService,
        private readonly UniversityMatchingService $matchingService,
        private readonly WhatsappNotificationService $whatsapp
    ) {
    }

    public function showLogin(): View
    {
        return view('portal.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => 'nullable|string|max:190',
            'email' => 'nullable|string|max:190',
            'password' => 'required|string',
        ]);
        $login = trim((string) ($data['login'] ?? $data['email'] ?? ''));
        if ($login === '') {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['login' => 'The login field is required.']);
        }
        try {
            $normalizedLogin = mb_strtolower($login);
            $user = User::query()
                ->where(function ($q) use ($login, $normalizedLogin) {
                    $q->whereRaw('LOWER(email) = ?', [$normalizedLogin])
                        ->orWhere('name', $login);
                })
                ->where('role_slug', 'student')
                ->whereNull('deleted_at')
                ->first();
        } catch (QueryException $e) {
            report($e);
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['login' => 'Database connection failed. Please start MySQL and try again.']);
        }

        if (!$user) {
            $studentExists = Student::query()
                ->where(function ($q) use ($login) {
                    $q->where('email', $login)->orWhere('full_name', $login);
                })
                ->exists();

            if ($studentExists) {
                return back()
                    ->withInput($request->except('password'))
                    ->withErrors(['login' => 'Portal account is not configured for this student. Please contact admin.']);
            }
        }

        $passwordOk = $user ? Hash::check($data['password'], (string) $user->password) : false;

        if ($user && !(bool) $user->is_active) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['login' => 'Your student account is inactive. Please contact admin.']);
        }
        if ($user) {
            $tenant = Tenant::query()->find($user->tenant_id);
            if (!$tenant || (int) ($tenant->is_active ?? 1) !== 1 || (string) ($tenant->subscription_status ?? 'active') !== 'active') {
                return back()
                    ->withInput($request->except('password'))
                    ->withErrors(['login' => 'Portal is not active for your company yet.']);
            }
        }

        if (!$user || !$passwordOk) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['login' => 'Invalid credentials']);
        }
        Auth::guard('crm')->logout();
        Auth::guard('student')->login($user, false);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect('/portal/dashboard');
    }

    public function dashboard(Request $request): View
    {
        $user = $this->authUser($request);
        $student = $this->studentForUser($user);
        $applications = Application::query()->where('tenant_id', $user->tenant_id)->where('student_id', $student->id)->get();
        $documents = $this->documentService->requiredRows($user->tenant_id, $student->id);
        $messages = collect();
        if (Schema::hasTable('student_messages')) {
            $messages = StudentMessage::query()->where('tenant_id', $user->tenant_id)->where('student_user_id', $user->id)->latest('id')->limit(5)->get();
        }
        $uploadedCount = $documents->where('is_missing', false)->count();
        $requiredCount = count(StudentDocumentService::REQUIRED_DOCUMENTS);
        $documentProgress = $requiredCount > 0 ? (int) round(($uploadedCount / $requiredCount) * 100) : 0;

        return view('portal.dashboard', compact('student', 'applications', 'documents', 'messages', 'uploadedCount', 'requiredCount', 'documentProgress'));
    }

    public function universities(Request $request): View
    {
        $user = $this->authUser($request);
        $student = $this->studentForUser($user);
        $keywords = trim((string) $request->query('keywords', ''));
        $countryUniversity = trim((string) $request->query('country_university', ''));
        $cityUniversity = trim((string) $request->query('city_university', ''));
        $universityType = trim((string) $request->query('university_type', ''));
        $universityName = trim((string) $request->query('university_name', ''));
        $degree = trim((string) $request->query('degree', ''));
        $degree = $this->normalizeDegreeLabel($degree) ?: $degree;
        $studyField = trim((string) $request->query('study_field', ''));
        $hasCityColumn = Schema::hasColumn('universities', 'city');
        $hasTypeColumn = Schema::hasColumn('universities', 'institution_type');
        $hasProgramsTable = Schema::hasTable('university_programs');

        $programUniversityIds = null;
        if ($hasProgramsTable && ($degree !== '' || $studyField !== '')) {
            $degreeAliases = $this->degreeAliasesForFilter($degree);
            $programUniversityIds = DB::table('university_programs')
                ->where('tenant_id', $user->tenant_id)
                ->when($degree !== '', function ($query) use ($degreeAliases) {
                    $query->where(function ($sub) use ($degreeAliases) {
                        foreach ($degreeAliases as $alias) {
                            $sub->orWhere('degree_level', 'like', "%{$alias}%");
                        }
                    });
                })
                ->when($studyField !== '', fn ($query) => $query->where('program_name', 'like', "%{$studyField}%"))
                ->distinct()
                ->pluck('university_id')
                ->all();
        }

        $universities = University::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', 1)
            ->when($keywords !== '', fn ($query) => $query->where(function ($sub) use ($keywords) {
                $sub->where('name', 'like', "%{$keywords}%")
                    ->orWhere('country', 'like', "%{$keywords}%")
                    ->orWhere('language', 'like', "%{$keywords}%")
                    ->orWhere('programs_summary', 'like', "%{$keywords}%")
                    ->orWhere('description', 'like', "%{$keywords}%");
            }))
            ->when($countryUniversity !== '', fn ($query) => $query->where('country', $countryUniversity))
            ->when($universityName !== '', fn ($query) => $query->where('name', 'like', "%{$universityName}%"))
            ->when($cityUniversity !== '', function ($query) use ($cityUniversity, $hasCityColumn) {
                if ($hasCityColumn) {
                    $query->where('city', $cityUniversity);
                    return;
                }
                $query->where(function ($sub) use ($cityUniversity) {
                    $sub->where('name', 'like', "%{$cityUniversity}%")
                        ->orWhere('description', 'like', "%{$cityUniversity}%")
                        ->orWhere('programs_summary', 'like', "%{$cityUniversity}%");
                });
            })
            ->when($universityType !== '', function ($query) use ($universityType, $hasTypeColumn) {
                $needle = mb_strtolower($universityType);
                if ($hasTypeColumn) {
                    $query->whereRaw('LOWER(institution_type) = ?', [$needle]);
                    return;
                }
                if ($needle === 'school') {
                    $query->where(function ($sub) {
                        $sub->whereRaw('LOWER(name) like ?', ['%school%'])
                            ->orWhereRaw('LOWER(description) like ?', ['%school%']);
                    });
                    return;
                }
                $query->where(function ($sub) {
                    $sub->whereRaw('LOWER(name) like ?', ['%university%'])
                        ->orWhereRaw('LOWER(description) like ?', ['%university%']);
                });
            })
            ->when(is_array($programUniversityIds) && !empty($programUniversityIds), fn ($query) => $query->whereIn('id', $programUniversityIds))
            ->when(is_array($programUniversityIds) && empty($programUniversityIds) && ($degree !== '' || $studyField !== ''), fn ($query) => $query->whereRaw('1 = 0'))
            ->get();
        $universities = $this->matchingService->rankedForStudent($universities, [
            'field' => $studyField !== '' ? $studyField : $request->query('field', $student->field_of_study),
            'country' => $countryUniversity !== '' ? $countryUniversity : $request->query('country', $student->target_country),
            'budget' => $request->query('budget', $student->budget_usd ?? 0),
            'language' => $request->query('language', $student->english_level),
            'gpa' => $student->gpa,
        ]);

        $countryOptions = University::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', 1)
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country')
            ->values()
            ->all();

        $citiesByCountry = [];
        if ($hasCityColumn) {
            $cityRows = University::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', 1)
                ->whereNotNull('country')
                ->whereNotNull('city')
                ->where('country', '!=', '')
                ->where('city', '!=', '')
                ->get(['country', 'city']);
            foreach ($cityRows as $row) {
                $citiesByCountry[$row->country] ??= [];
                if (!in_array($row->city, $citiesByCountry[$row->country], true)) {
                    $citiesByCountry[$row->country][] = $row->city;
                }
            }
            foreach ($citiesByCountry as $countryKey => $cities) {
                $citiesByCountry[$countryKey] = collect($cities)->unique()->sort()->values()->all();
            }
        }

        $degreeOptions = [];
        if ($hasProgramsTable) {
            $rawDegreeOptions = DB::table('university_programs')
                ->where('tenant_id', $user->tenant_id)
                ->whereNotNull('degree_level')
                ->where('degree_level', '!=', '')
                ->distinct()
                ->orderBy('degree_level')
                ->pluck('degree_level')
                ->values()
                ->all();
            $degreeOptions = $this->normalizeDegreeOptions($rawDegreeOptions);
        }
        if (empty($degreeOptions)) {
            $degreeOptions = $this->defaultDegreeOptions();
        }

        $studyFieldOptions = [];
        if ($hasProgramsTable) {
            $studyFieldOptions = DB::table('university_programs')
                ->where('tenant_id', $user->tenant_id)
                ->whereNotNull('program_name')
                ->where('program_name', '!=', '')
                ->distinct()
                ->orderBy('program_name')
                ->limit(120)
                ->pluck('program_name')
                ->map(function (string $name) {
                    $pieces = preg_split('/[-,(|]/', $name);
                    return trim($pieces[0] ?? $name);
                })
                ->filter()
                ->unique()
                ->values()
                ->all();
        }
        if (empty($studyFieldOptions)) {
            $studyFieldOptions = ['Business', 'Computer Science', 'Engineering', 'Medicine', 'Law', 'Architecture'];
        }

        return view('portal.universities', compact(
            'universities',
            'student',
            'keywords',
            'countryUniversity',
            'cityUniversity',
            'universityType',
            'universityName',
            'degree',
            'studyField',
            'countryOptions',
            'citiesByCountry',
            'degreeOptions',
            'studyFieldOptions'
        ));
    }

    private function normalizeDegreeOptions(array $raw): array
    {
        return $this->defaultDegreeOptions();
    }

    private function normalizeDegreeLabel(string $value): string
    {
        $needle = mb_strtolower(trim($value));
        if ($needle === '') {
            return '';
        }
        foreach (self::DEGREE_ALIASES as $target => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($needle, mb_strtolower($alias))) {
                    return $target;
                }
            }
        }
        return '';
    }

    private function degreeAliasesForFilter(string $selected): array
    {
        $label = $this->normalizeDegreeLabel($selected);
        if ($label === '' || !isset(self::DEGREE_ALIASES[$label])) {
            return [$selected];
        }
        return self::DEGREE_ALIASES[$label];
    }

    private function defaultDegreeOptions(): array
    {
        return ['Diploma', 'Associate', 'Bachelor', 'Master', 'PhD'];
    }

    public function applications(Request $request): View
    {
        $user = $this->authUser($request);
        $student = $this->studentForUser($user);
        $applications = Application::query()->where('tenant_id', $user->tenant_id)->where('student_id', $student->id)->get();
        return view('portal.applications', compact('applications'));
    }

    public function documents(Request $request): View
    {
        $user = $this->authUser($request);
        $student = $this->studentForUser($user);
        $documents = $this->documentService->requiredRows($user->tenant_id, $student->id);
        $offerLetters = Document::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('student_id', $student->id)
            ->where('type', 'acceptance_letter')
            ->orderByDesc('id')
            ->get();
        return view('portal.documents', compact('documents', 'student', 'offerLetters'));
    }

    public function uploadDocument(Request $request): RedirectResponse
    {
        return back()->withErrors(['documents' => 'Students cannot upload documents directly. Please contact your agent.']);
    }

    public function viewDocument(Request $request, int $documentId)
    {
        $user = $this->authUser($request);
        $student = $this->studentForUser($user);
        $document = Document::query()
            ->where('tenant_id', $user->tenant_id)
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

    public function applyToUniversity(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $student = $this->studentForUser($user);
        $data = $request->validate([
            'university_id' => 'required|integer|exists:universities,id',
            'target_program' => 'nullable|string|max:190',
        ]);

        $university = University::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', 1)
            ->findOrFail((int) $data['university_id']);

        $targetProgram = trim((string) ($data['target_program'] ?? ''));
        $targetProgram = $targetProgram !== '' ? $targetProgram : ($student->field_of_study ?: 'General Admission');
        $payloadProgram = $targetProgram.' @ '.$university->name;

        $existingPending = StudentRequest::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('email', $student->email)
            ->where('status', 'pending')
            ->where('target_program', $payloadProgram)
            ->first();
        if ($existingPending) {
            return back()->withErrors(['target_program' => 'You already sent this request and it is pending review.']);
        }

        $requestRow = StudentRequest::query()->create([
            'tenant_id' => $user->tenant_id,
            'full_name' => $student->full_name,
            'email' => $student->email,
            'phone' => $student->phone,
            'nationality' => $student->nationality,
            'target_program' => $payloadProgram,
            'status' => 'pending',
        ]);

        $admins = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('role_slug', ['admin', 'super_admin'])
            ->where('is_active', 1)
            ->get();
        foreach ($admins as $admin) {
            Notification::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $admin->id,
                'type' => 'student_request',
                'title' => 'New portal application request',
                'body' => $student->full_name.' requested '.$payloadProgram,
                'meta_json' => json_encode([
                    'student_request_id' => $requestRow->id,
                    'student_id' => $student->id,
                    'university_id' => $university->id,
                ]),
            ]);
        }
        $this->whatsapp->notifyTenant(
            $user->tenant_id,
            'application_update',
            'New portal request: '.$student->full_name.' requested '.$payloadProgram
        );

        return back()->with('success', 'Your request was sent to admin for review.');
    }

    public function messages(Request $request): View
    {
        $user = $this->authUser($request);
        if (!Schema::hasTable('student_messages')) {
            return back()->withErrors(['message' => 'Messaging module is not migrated yet.']);
        }
        $messages = StudentMessage::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('student_user_id', $user->id)
            ->latest('id')
            ->paginate(20);

        return view('portal.messages', compact('messages'));
    }

    public function sendMessage(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        if (!Schema::hasTable('student_messages')) {
            return back()->withErrors(['message' => 'Messaging module is not migrated yet.']);
        }
        $student = $this->studentForUser($user);
        $data = $request->validate([
            'message' => 'required|string|min:3|max:4000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip|max:10240',
        ]);

        $recipient = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('role_slug', ['admin', 'agent', 'super_admin'])
            ->where('is_active', 1)
            ->orderByRaw("FIELD(role_slug, 'agent', 'admin', 'super_admin')")
            ->first();

        $payload = [
            'tenant_id' => $user->tenant_id,
            'student_id' => $student->id,
            'student_user_id' => $user->id,
            'recipient_user_id' => $recipient?->id,
            'sender_role' => 'student',
            'body' => $data['message'],
        ];
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('messages', 'public');
            $payload['attachment_url'] = '/storage/'.$path;
            $payload['attachment_name'] = $request->file('attachment')->getClientOriginalName();
        }
        $row = StudentMessage::query()->create($payload);

        if ($recipient) {
            Notification::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $recipient->id,
                'type' => 'student_message',
                'title' => 'New message from student',
                'body' => $student->full_name.': '.$data['message'],
                'meta_json' => json_encode(['message_id' => $row->id, 'student_id' => $student->id]),
            ]);
        }
        $this->whatsapp->notifyTenant(
            $user->tenant_id,
            'application_update',
            'New student message from '.$student->full_name
        );

        return back()->with('success', 'Message sent.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/portal/login');
    }

    private function studentForUser(User $user): Student
    {
        $query = Student::query()->where('tenant_id', $user->tenant_id);
        if (Schema::hasColumn('students', 'user_id')) {
            $query->where(function ($sub) use ($user) {
                $sub->where('user_id', $user->id)->orWhere('email', $user->email);
            });
        } else {
            $query->where('email', $user->email);
        }

        return $query->firstOrFail();
    }
}
