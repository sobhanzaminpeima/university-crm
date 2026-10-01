<?php

namespace App\Http\Controllers;

use App\Models\University;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UniversityController extends Controller
{
    private const DEGREE_ALIASES = [
        'Diploma' => ['Diploma', 'High School Diploma', 'دیپلم'],
        'Associate' => ['Associate', 'Associate Degree', 'فوق دیپلم', 'کاردانی'],
        'Bachelor' => ['Bachelor', 'BSc', 'BA', 'کارشناسی', 'لیسانس'],
        'Master' => ['Master', 'MSc', 'MA', 'کارشناسی ارشد', 'فوق لیسانس'],
        'PhD' => ['PhD', 'Doctorate', 'دکتری', 'دکترا'],
    ];
    private const IMPORT_COLUMNS = [
        'name',
        'country',
        'city',
        'institution_type',
        'website',
        'currency',
        'tuition_range',
        'tuition_fee_type',
        'language',
        'visa_notes',
        'description',
        'programs_summary',
        'program_degree_level',
        'program_thesis_type',
        'program_name',
        'program_language',
        'program_duration',
        'program_currency',
        'program_notes',
        'program_fee',
        'program_fee_type',
        'deadline',
    ];

    public function index(Request $request): View
    {
        $user = $this->authUser($request);
        $q = (string) $request->query('q', '');
        $sort = (string) $request->query('sort', 'created_desc');
        $country = trim((string) $request->query('country', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $perPage = $this->perPage($request);
        $universities = University::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('universities.view_all'), function ($query) use ($user) {
                $query->where('created_by_user_id', $user->id);
            })
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('country', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhere('institution_type', 'like', "%{$q}%")
                    ->orWhere('programs_summary', 'like', "%{$q}%");
            }))
            ->when($country !== '', fn ($query) => $query->where('country', $country))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->when($sort === 'name_asc', fn ($query) => $query->orderBy('name')->orderBy('id'))
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('name')->orderByDesc('id'))
            ->when($sort === 'created_asc', fn ($query) => $query->orderBy('created_at')->orderBy('id'))
            ->when(!in_array($sort, ['name_asc', 'name_desc', 'created_asc'], true), fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString();
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
        $applicationCounts = DB::table('applications')
            ->where('tenant_id', $user->tenant_id)
            ->selectRaw('university_id, COUNT(*) as total')
            ->groupBy('university_id')
            ->pluck('total', 'university_id');
        $currencies = DB::table('currencies')
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', 1)
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->pluck('code')
            ->values()
            ->all();
        if (empty($currencies)) {
            $currencies = ['USD', 'EUR', 'TRY'];
        }
        $programQuality = [
            'total_programs' => 0,
            'missing_fee' => 0,
            'missing_language' => 0,
            'missing_thesis' => 0,
        ];
        if (Schema::hasTable('university_programs')) {
            $programQuality['total_programs'] = (int) DB::table('university_programs')->where('tenant_id', $user->tenant_id)->count();
            $programQuality['missing_fee'] = (int) DB::table('university_programs')->where('tenant_id', $user->tenant_id)->whereNull('fee')->count();
            $programQuality['missing_language'] = (int) DB::table('university_programs')->where('tenant_id', $user->tenant_id)->where(function ($q) {
                $q->whereNull('language')->orWhere('language', '');
            })->count();
            if (Schema::hasColumn('university_programs', 'thesis_type')) {
                $programQuality['missing_thesis'] = (int) DB::table('university_programs')->where('tenant_id', $user->tenant_id)->where(function ($q) {
                    $q->whereNull('thesis_type')->orWhere('thesis_type', '');
                })->count();
            }
        }
        $programsByUniversity = [];
        if (Schema::hasTable('university_programs')) {
            $ids = $universities->pluck('id')->all();
            if (!empty($ids)) {
                $programRows = DB::table('university_programs')
                    ->where('tenant_id', $user->tenant_id)
                    ->whereIn('university_id', $ids)
                    ->orderBy('program_name')
                    ->get(['id', 'university_id', 'degree_level', 'thesis_type', 'program_name', 'language', 'duration', 'currency', 'fee', 'fee_type', 'notes']);
                $seenProgramKeys = [];
                foreach ($programRows as $row) {
                    $key = $this->programDuplicateKey($row, Schema::hasColumn('university_programs', 'thesis_type'));
                    if (isset($seenProgramKeys[$row->university_id][$key])) {
                        continue;
                    }
                    $seenProgramKeys[$row->university_id][$key] = true;
                    $programsByUniversity[$row->university_id] ??= [];
                    $programsByUniversity[$row->university_id][] = $row;
                }
            }
        }

        return view('universities.index', compact('universities', 'q', 'sort', 'country', 'dateFrom', 'dateTo', 'perPage', 'countryOptions', 'applicationCounts', 'currencies', 'programQuality', 'programsByUniversity'));
    }

    public function deduplicate(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $removedUniversities = $this->dedupeTenantUniversities($user->tenant_id);
        $removedPrograms = $this->dedupeTenantPrograms($user->tenant_id);
        $removedApplications = $this->dedupeTenantApplications($user->tenant_id);
        $this->audit($request, 'university.deduplicate', 'university', 0, [
            'removed_universities' => $removedUniversities,
            'removed_programs' => $removedPrograms,
            'removed_applications' => $removedApplications,
        ]);
        return back()->with('success', "Duplicate cleanup completed. Removed {$removedUniversities} duplicate universities, {$removedPrograms} duplicate programs and {$removedApplications} duplicate applications.");
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'country' => 'required|string|max:80',
            'city' => 'nullable|string|max:120',
            'institution_type' => 'required|string|in:university,school',
            'website' => 'nullable|url|max:255',
            'currency' => 'required|string|max:8',
            'tuition_range' => 'nullable|string|max:255',
            'tuition_fee_type' => 'nullable|string|in:per_semester,yearly,total_program',
            'language' => 'nullable|string|in:Turkish,English,Arabic,Both',
            'programs_summary' => 'nullable|string|max:5000',
            'deadline' => 'nullable|date',
            'visa_notes' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:7000',
            'logo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192',
            'program_degree_level' => 'nullable|string|max:60',
            'program_name' => 'nullable|string|max:255',
            'program_thesis_type' => 'nullable|string|in:thesis,non_thesis,both',
            'program_language' => 'nullable|string|in:Turkish,English,Arabic,Both',
            'program_duration' => 'nullable|string|max:40',
            'program_currency' => 'nullable|string|max:8',
            'program_fee' => 'nullable|numeric',
            'program_notes' => 'nullable|string|max:2000',
            'programs' => 'nullable|array',
            'programs.*.program_id' => 'nullable|integer',
            'programs.*.program_degree_level' => 'nullable|string|max:60',
            'programs.*.program_name' => 'nullable|string|max:255',
            'programs.*.program_thesis_type' => 'nullable|string|in:thesis,non_thesis,both',
            'programs.*.program_language' => 'nullable|string|in:Turkish,English,Arabic,Both',
            'programs.*.program_duration' => 'nullable|string|max:40',
            'programs.*.program_currency' => 'nullable|string|max:8',
            'programs.*.program_fee' => 'nullable|numeric',
            'programs.*.program_fee_type' => 'nullable|string|in:per_semester,yearly,total_program',
            'programs.*.program_notes' => 'nullable|string|max:2000',
            'deleted_program_ids' => 'nullable|array',
            'deleted_program_ids.*' => 'nullable|integer',
        ]);
        $duplicate = University::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $data['name']))])
            ->whereRaw('LOWER(country) = ?', [mb_strtolower(trim((string) $data['country']))])
            ->whereRaw('LOWER(COALESCE(city, "")) = ?', [mb_strtolower(trim((string) ($data['city'] ?? '')))])
            ->first();
        if ($duplicate) {
            return back()->withErrors([
                'name' => 'This university already exists for this country/city. Edit the existing one instead of creating duplicate.',
            ])->withInput();
        }
        $data['tenant_id'] = $user->tenant_id;
        $data['created_by_user_id'] = $user->id;
        $data['is_active'] = 1;
        if (!Schema::hasColumn('universities', 'tuition_fee_type')) {
            unset($data['tuition_fee_type']);
        }
        if ($request->hasFile('logo')) {
            $data['logo_url'] = '/storage/'.$request->file('logo')->store('universities/logos', 'public');
        }
        if ($request->hasFile('image')) {
            $data['image_url'] = '/storage/'.$request->file('image')->store('universities/images', 'public');
        }
        $university = University::query()->create($data);
        $this->deleteProgramRows($user->tenant_id, $university->id, $request);
        $this->upsertProgramRows($user->tenant_id, $university->id, $request);
        $this->removeDuplicatePrograms($user->tenant_id, $university->id);
        $this->cleanupApplicationsForUniversity($user->tenant_id, $university->id);
        $this->removeDuplicateApplications($user->tenant_id, $university->id);

        $this->audit($request, 'university.create', 'university', $university->id, $data);

        return back()->with('success', 'University created.');
    }

    public function show(Request $request, int $id): View
    {
        $user = $this->authUser($request);
        $university = University::query()->forTenant($user->tenant_id, $user->role_slug)->findOrFail($id);
        $university->name = $this->displayText($university->name);
        $university->country = $this->displayText($university->country);
        $university->city = $this->displayText((string) ($university->city ?? ''));
        $university->tuition_range = $this->displayText((string) ($university->tuition_range ?? ''));
        $university->language = $this->displayText((string) ($university->language ?? ''));
        $university->visa_notes = $this->displayLongText((string) ($university->visa_notes ?? ''));
        $university->description = $this->displayLongText((string) ($university->description ?? ''));
        $university->programs_summary = $this->displayLongText((string) ($university->programs_summary ?? ''));
        $programs = collect();
        if (Schema::hasTable('university_programs')) {
            $programs = DB::table('university_programs')
                ->where('tenant_id', $user->tenant_id)
                ->where('university_id', $university->id)
                ->orderByRaw("FIELD(degree_level,'Diploma','Associate','Bachelor','Master','PhD')")
                ->orderBy('program_name')
                ->get(['degree_level', 'program_name', 'language', 'duration', 'currency', 'fee', 'notes']);
            $programs = $programs->map(function ($p) {
                $p->degree_level = $this->displayText((string) ($p->degree_level ?? ''));
                $p->program_name = $this->displayText((string) ($p->program_name ?? ''));
                $language = $this->displayText((string) ($p->language ?? ''));
                if ($language === '') {
                    $name = mb_strtolower((string) ($p->program_name ?? ''));
                    if (str_contains($name, 'english') || str_contains($name, 'ingilizce')) {
                        $language = 'English';
                    } elseif (str_contains($name, 'turkish') || str_contains($name, 'türkçe')) {
                        $language = 'Turkish';
                    } elseif (str_contains($name, 'arabic') || str_contains($name, 'arapça')) {
                        $language = 'Arabic';
                    }
                }
                $p->language = $language;
                $p->duration = $this->displayText((string) ($p->duration ?? ''));
                $p->notes = $this->displayLongText((string) ($p->notes ?? ''));
                return $p;
            });
        }
        $documentRequirements = \App\Models\DocumentRequirement::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('university_id', $university->id)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return view('universities.show', compact('university', 'programs', 'documentRequirements'));
    }

    public function storeDocumentRequirement(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $university = University::query()->forTenant($user->tenant_id, $user->role_slug)->findOrFail($id);
        $data = $request->validate([
            'doc_type' => 'required|string|in:passport,diploma,transcript,english_certificate,photo,other_documents,payment_receipt,acceptance_letter',
            'label' => 'required|string|max:190',
            'is_mandatory' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        \App\Models\DocumentRequirement::query()->create([
            'tenant_id' => $user->tenant_id,
            'university_id' => $university->id,
            'doc_type' => $data['doc_type'],
            'label' => $data['label'],
            'is_mandatory' => (bool) ($data['is_mandatory'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('success', 'Document requirement added.');
    }

    public function destroyDocumentRequirement(Request $request, int $id, int $requirementId): RedirectResponse
    {
        $user = $this->authUser($request);
        $university = University::query()->forTenant($user->tenant_id, $user->role_slug)->findOrFail($id);
        $requirement = \App\Models\DocumentRequirement::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('university_id', $university->id)
            ->findOrFail($requirementId);
        $requirement->delete();

        return back()->with('success', 'Document requirement removed.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $university = University::query()->forTenant($user->tenant_id, $user->role_slug)->findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'country' => 'required|string|max:80',
            'city' => 'nullable|string|max:120',
            'institution_type' => 'required|string|in:university,school',
            'website' => 'nullable|url|max:255',
            'currency' => 'required|string|max:8',
            'tuition_range' => 'nullable|string|max:255',
            'tuition_fee_type' => 'nullable|string|in:per_semester,yearly,total_program',
            'language' => 'nullable|string|in:Turkish,English,Arabic,Both',
            'programs_summary' => 'nullable|string|max:5000',
            'deadline' => 'nullable|date',
            'visa_notes' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:7000',
            'is_active' => 'nullable|boolean',
            'logo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192',
            'program_degree_level' => 'nullable|string|max:60',
            'program_name' => 'nullable|string|max:255',
            'program_thesis_type' => 'nullable|string|in:thesis,non_thesis,both',
            'program_language' => 'nullable|string|in:Turkish,English,Arabic,Both',
            'program_duration' => 'nullable|string|max:40',
            'program_currency' => 'nullable|string|max:8',
            'program_fee' => 'nullable|numeric',
            'program_notes' => 'nullable|string|max:2000',
            'programs' => 'nullable|array',
            'programs.*.program_id' => 'nullable|integer',
            'programs.*.program_degree_level' => 'nullable|string|max:60',
            'programs.*.program_name' => 'nullable|string|max:255',
            'programs.*.program_thesis_type' => 'nullable|string|in:thesis,non_thesis,both',
            'programs.*.program_language' => 'nullable|string|in:Turkish,English,Arabic,Both',
            'programs.*.program_duration' => 'nullable|string|max:40',
            'programs.*.program_currency' => 'nullable|string|max:8',
            'programs.*.program_fee' => 'nullable|numeric',
            'programs.*.program_fee_type' => 'nullable|string|in:per_semester,yearly,total_program',
            'programs.*.program_notes' => 'nullable|string|max:2000',
            'deleted_program_ids' => 'nullable|array',
            'deleted_program_ids.*' => 'nullable|integer',
        ]);
        $duplicate = University::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('id', '!=', $university->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $data['name']))])
            ->whereRaw('LOWER(country) = ?', [mb_strtolower(trim((string) $data['country']))])
            ->whereRaw('LOWER(COALESCE(city, "")) = ?', [mb_strtolower(trim((string) ($data['city'] ?? '')))])
            ->first();
        if ($duplicate) {
            return back()->withErrors([
                'name' => 'Another university with same name/country/city already exists.',
            ])->withInput();
        }
        if ($request->hasFile('logo')) {
            $data['logo_url'] = '/storage/'.$request->file('logo')->store('universities/logos', 'public');
        }
        if ($request->hasFile('image')) {
            $data['image_url'] = '/storage/'.$request->file('image')->store('universities/images', 'public');
        }
        if (!Schema::hasColumn('universities', 'tuition_fee_type')) {
            unset($data['tuition_fee_type']);
        }
        $university->update($data);
        $this->deleteProgramRows($user->tenant_id, $university->id, $request);
        $this->upsertProgramRows($user->tenant_id, $university->id, $request);
        $this->removeDuplicatePrograms($user->tenant_id, $university->id);
        $this->cleanupApplicationsForUniversity($user->tenant_id, $university->id);
        $this->removeDuplicateApplications($user->tenant_id, $university->id);
        $this->audit($request, 'university.update', 'university', $university->id, $data);

        return back()->with('success', 'University updated.');
    }

    public function destroy(Request $request, $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $id = (int) $id;
        if ($id < 1) {
            return back()->withErrors(['university' => 'Invalid university id.']);
        }
        $university = University::query()->forTenant($user->tenant_id, $user->role_slug)->findOrFail($id);
        if (Schema::hasTable('applications')) {
            DB::table('applications')->where('tenant_id', $user->tenant_id)->where('university_id', $university->id)->delete();
        }
        if (Schema::hasTable('scholarships')) {
            DB::table('scholarships')->where('tenant_id', $user->tenant_id)->where('university_id', $university->id)->delete();
        }
        if (Schema::hasTable('university_programs')) {
            DB::table('university_programs')->where('tenant_id', $user->tenant_id)->where('university_id', $university->id)->delete();
        }
        $university->delete();
        $this->audit($request, 'university.delete', 'university', $id);

        return back()->with('success', 'University deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        if (empty($ids)) {
            return back()->withErrors(['ids' => 'No universities selected.']);
        }
        $query = University::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->whereIn('id', $ids);
        if (in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('universities.view_all')) {
            $query->where('created_by_user_id', $user->id);
        }
        $deleted = (clone $query)->count();
        $idsForCleanup = (clone $query)->pluck('id')->all();
        if (!empty($idsForCleanup) && Schema::hasTable('applications')) {
            DB::table('applications')->where('tenant_id', $user->tenant_id)->whereIn('university_id', $idsForCleanup)->delete();
        }
        if (!empty($idsForCleanup) && Schema::hasTable('scholarships')) {
            DB::table('scholarships')->where('tenant_id', $user->tenant_id)->whereIn('university_id', $idsForCleanup)->delete();
        }
        if (!empty($idsForCleanup) && Schema::hasTable('university_programs')) {
            DB::table('university_programs')->where('tenant_id', $user->tenant_id)->whereIn('university_id', $idsForCleanup)->delete();
        }
        if ($deleted > 0) {
            $query->delete();
        }
        $this->audit($request, 'university.bulk_delete', 'university', 0, ['count' => $deleted, 'ids' => $ids]);
        return back()->with('success', "Deleted {$deleted} universities.");
    }

    public function deleteAll(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $query = University::query()->forTenant($user->tenant_id, $user->role_slug);
        if (in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('universities.view_all')) {
            $query->where('created_by_user_id', $user->id);
        }
        $ids = (clone $query)->pluck('id')->all();
        if (!empty($ids) && Schema::hasTable('applications')) {
            DB::table('applications')->where('tenant_id', $user->tenant_id)->whereIn('university_id', $ids)->delete();
        }
        if (!empty($ids) && Schema::hasTable('scholarships')) {
            DB::table('scholarships')->where('tenant_id', $user->tenant_id)->whereIn('university_id', $ids)->delete();
        }
        if (!empty($ids) && Schema::hasTable('university_programs')) {
            DB::table('university_programs')->where('tenant_id', $user->tenant_id)->whereIn('university_id', $ids)->delete();
        }
        $deleted = (clone $query)->count();
        $query->delete();
        $this->audit($request, 'university.delete_all', 'university', 0, ['count' => $deleted]);
        return back()->with('success', "Deleted {$deleted} universities.");
    }

    public function destroyProgram(Request $request, $id, $programId): RedirectResponse
    {
        $user = $this->authUser($request);
        $id = (int) $id;
        $programId = (int) $programId;
        if ($id < 1 || $programId < 1) {
            return back()->withErrors(['program' => 'Invalid program id.']);
        }

        $university = University::query()->forTenant($user->tenant_id, $user->role_slug)->findOrFail($id);
        if (!Schema::hasTable('university_programs')) {
            return back()->withErrors(['program' => 'Program table is not available.']);
        }

        $deleted = DB::table('university_programs')
            ->where('id', $programId)
            ->where('tenant_id', $user->tenant_id)
            ->where('university_id', $university->id)
            ->delete();

        if ($deleted < 1) {
            return back()->withErrors(['program' => 'Program not found or already deleted.']);
        }

        $this->audit($request, 'university.program.delete', 'university_program', $programId, [
            'university_id' => $university->id,
        ]);

        return back()->with('success', 'Program deleted.');
    }

    public function exportTemplate(Request $request): Response
    {
        $user = $this->authUser($request);
        $sampleRows = [
            [
                'Istanbul Atlas University',
                'Turkey',
                'Istanbul',
                'university',
                'https://www.atlas.edu.tr',
                'USD',
                '9000-25000',
                'yearly',
                'Both',
                'Visa support available',
                'Sample university description',
                'Medicine, Dentistry, Engineering',
                'Bachelor',
                'non_thesis',
                'Medicine',
                'Arabic',
                '6 years',
                'USD',
                'Installments available',
                '25000',
                'yearly',
                '2026-11-30',
            ],
            [
                'Istanbul Atlas University',
                'Turkey',
                'Istanbul',
                'university',
                'https://www.atlas.edu.tr',
                'USD',
                '9000-25000',
                'yearly',
                'Both',
                '',
                '',
                '',
                'Bachelor',
                'non_thesis',
                'Computer Engineering',
                'English',
                '4 years',
                'USD',
                '',
                '9000',
                'yearly',
                '2026-11-30',
            ],
        ];

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, self::IMPORT_COLUMNS);
        foreach ($sampleRows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        $csvWithBom = "\xEF\xBB\xBF".$csv;
        $filename = 'universities-import-template.csv';

        return response($csvWithBom, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $data = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $data['file'];
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return back()->withErrors(['file' => 'Cannot open the uploaded file.']);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return back()->withErrors(['file' => 'The file is empty.']);
        }

        $header = array_map(function ($value) {
            $value = (string) $value;
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
            $value = trim($value, "\" \t\n\r\0\x0B");
            $value = trim(mb_strtolower($value));
            return preg_replace('/[^a-z0-9_]/', '', $value) ?? $value;
        }, $header);

        $requiredColumns = array_map(function ($c) {
            $v = trim(mb_strtolower($c));
            return preg_replace('/[^a-z0-9_]/', '', $v) ?? $v;
        }, ['name', 'country']);

        $missing = array_diff($requiredColumns, $header);
        if (!empty($missing)) {
            fclose($handle);
            return back()->withErrors(['file' => 'Missing required columns: '.implode(', ', $missing)]);
        }

        $indexByName = [];
        foreach ($header as $idx => $name) {
            $indexByName[$name] = $idx;
        }

        $created = 0;
        $updated = 0;
        $programsUpserted = 0;
        $errors = [];
        $lineNo = 1;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $lineNo++;
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $payload = [];
                foreach (self::IMPORT_COLUMNS as $column) {
                    $lookup = mb_strtolower($column);
                    $colIndex = $indexByName[$lookup] ?? null;
                    $value = $colIndex !== null ? ($row[$colIndex] ?? null) : null;
                    $payload[$column] = is_string($value) ? trim($value) : $value;
                }

                if ($payload['name'] === '' || $payload['country'] === '') {
                    $errors[] = "Line {$lineNo}: name and country are required.";
                    continue;
                }

                $type = $payload['institution_type'] !== '' ? strtolower($payload['institution_type']) : 'university';
                if (!in_array($type, ['university', 'school'], true)) {
                    $type = 'university';
                }

                $universityData = [
                    'tenant_id' => $user->tenant_id,
                    'name' => $this->cleanText($this->displayText($payload['name']), 160) ?: $payload['name'],
                    'country' => $this->cleanText($this->displayText($payload['country']), 80) ?: $payload['country'],
                    'city' => $this->cleanText($this->displayText((string) ($payload['city'] ?? '')), 120) ?: null,
                    'institution_type' => $type,
                    'website' => $payload['website'] ?: null,
                    'currency' => $payload['currency'] ?: 'USD',
                    'tuition_range' => $this->cleanText($this->displayText((string) ($payload['tuition_range'] ?? '')), 255),
                    'language' => $this->normalizeProgramLanguage((string) ($payload['language'] ?? '')),
                    'deadline' => $payload['deadline'] ?: null,
                    'visa_notes' => $this->cleanText($this->displayLongText((string) ($payload['visa_notes'] ?? '')), 2000),
                    'description' => $this->cleanText($this->displayLongText((string) ($payload['description'] ?? '')), 7000),
                    'programs_summary' => $this->cleanText($this->displayLongText((string) ($payload['programs_summary'] ?? '')), 5000),
                    'created_by_user_id' => $user->id,
                    'is_active' => 1,
                ];
                if (Schema::hasColumn('universities', 'tuition_fee_type')) {
                    $universityData['tuition_fee_type'] = $this->normalizeFeeType((string) ($payload['tuition_fee_type'] ?? ''));
                }

                $university = University::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($payload['name'])])
                    ->whereRaw('LOWER(country) = ?', [mb_strtolower($payload['country'])])
                    ->whereRaw('LOWER(COALESCE(city, "")) = ?', [mb_strtolower((string) ($payload['city'] ?? ''))])
                    ->first();

                if ($university) {
                    $university->update($universityData);
                    $updated++;
                } else {
                    $university = University::query()->create($universityData);
                    $created++;
                }

                if ($payload['program_name'] !== '') {
                    $normalizedDegree = $this->normalizeDegreeLabel((string) ($payload['program_degree_level'] ?? ''));
                    $thesisType = $this->normalizeThesisType((string) ($payload['program_thesis_type'] ?? ''));
                    $programLanguage = $this->normalizeProgramLanguage((string) ($payload['program_language'] ?? ''));
                    $programDuration = $this->cleanText($payload['program_duration'] ?? null, 40);
                    $programCurrency = $this->cleanText($payload['program_currency'] ?: ($payload['currency'] ?: 'USD'), 8);
                    $programFee = $this->normalizeFee($payload['program_fee'] ?? null);
                    $programNotes = $this->cleanText($payload['program_notes'] ?? null, 2000);
                    $programName = $this->cleanText($payload['program_name'], 255);
                    $programFeeType = $this->normalizeFeeType((string) ($payload['program_fee_type'] ?? ''));
                    if ($programName === null || $programName === '') {
                        continue;
                    }
                    $match = [
                        'tenant_id' => $user->tenant_id,
                        'university_id' => $university->id,
                        'degree_level' => $normalizedDegree ?: 'Bachelor',
                        'program_name' => $programName,
                        'language' => $programLanguage,
                        'duration' => $programDuration,
                        'currency' => $programCurrency,
                        'fee' => $programFee,
                    ];
                    $update = [
                        'notes' => $programNotes,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ];
                    if (Schema::hasColumn('university_programs', 'thesis_type')) {
                        $match['thesis_type'] = $thesisType;
                        $update['thesis_type'] = $thesisType;
                    }
                    if (Schema::hasColumn('university_programs', 'fee_type')) {
                        $match['fee_type'] = $programFeeType;
                        $update['fee_type'] = $programFeeType;
                    }
                    DB::table('university_programs')->updateOrInsert($match, $update);
                    $programsUpserted++;
                }
            }

            fclose($handle);
            DB::commit();
        } catch (\Throwable $e) {
            fclose($handle);
            DB::rollBack();
            throw $e;
        }

        $duplicateProgramsRemoved = $this->dedupeTenantPrograms($user->tenant_id);

        $this->audit($request, 'university.import', 'university', 0, [
            'created' => $created,
            'updated' => $updated,
            'programs_upserted' => $programsUpserted,
            'duplicate_programs_removed' => $duplicateProgramsRemoved,
            'errors' => $errors,
        ]);
        $this->syncStudyFieldsFromPrograms($user->tenant_id);

        $message = "Import finished. Created: {$created}, Updated: {$updated}, Programs: {$programsUpserted}.";
        if ($duplicateProgramsRemoved > 0) {
            $message .= " Duplicate programs removed: {$duplicateProgramsRemoved}.";
        }
        if (!empty($errors)) {
            $message .= ' Some rows were skipped: '.implode(' | ', array_slice($errors, 0, 5));
        }

        return back()->with('success', $message);
    }

    private function syncStudyFieldsFromPrograms(int $tenantId): void
    {
        if (!Schema::hasTable('study_fields') || !Schema::hasTable('university_programs')) {
            return;
        }
        $names = DB::table('university_programs')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('program_name')
            ->where('program_name', '!=', '')
            ->distinct()
            ->pluck('program_name');
        foreach ($names as $name) {
            $parts = preg_split('/[-,(|]/', (string) $name);
            $value = trim((string) ($parts[0] ?? $name));
            if ($value === '' || preg_match('/^[0-9\\-\\s]+$/', $value) === 1) {
                continue;
            }
            DB::table('study_fields')->updateOrInsert(
                ['tenant_id' => $tenantId, 'name' => mb_substr($value, 0, 150)],
                ['is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function upsertProgramRows(int $tenantId, int $universityId, Request $request): void
    {
        $rows = $request->input('programs', []);
        if (!is_array($rows)) {
            $rows = [];
        }
        $legacyProgramName = trim((string) $request->input('program_name', ''));
        if ($legacyProgramName !== '') {
            $rows[] = [
                'program_id' => $request->input('program_id'),
                'program_degree_level' => $request->input('program_degree_level'),
                'program_thesis_type' => $request->input('program_thesis_type'),
                'program_name' => $legacyProgramName,
                'program_language' => $request->input('program_language'),
                'program_duration' => $request->input('program_duration'),
                'program_currency' => $request->input('program_currency'),
                'program_fee' => $request->input('program_fee'),
                'program_fee_type' => $request->input('program_fee_type'),
                'program_notes' => $request->input('program_notes'),
            ];
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $programName = trim((string) ($row['program_name'] ?? ''));
            if ($programName === '') {
                continue;
            }
            $normalizedDegree = $this->normalizeDegreeLabel((string) ($row['program_degree_level'] ?? ''));
            $thesisType = $this->normalizeThesisType((string) ($row['program_thesis_type'] ?? ''));
            $degreeLevel = $normalizedDegree ?: 'Bachelor';
            $normalizedProgramName = mb_substr($programName, 0, 255);
            $programLanguage = $this->normalizeProgramLanguage((string) ($row['program_language'] ?? ''));
            $programDuration = ($row['program_duration'] ?? null) ?: null;
            $programCurrency = ($row['program_currency'] ?? null) ?: 'USD';
            $programFee = is_numeric((string) ($row['program_fee'] ?? '')) ? (float) $row['program_fee'] : null;
            $programId = (int) ($row['program_id'] ?? 0);
            $update = [
            'degree_level' => $degreeLevel,
            'program_name' => $normalizedProgramName,
            'language' => $programLanguage,
            'duration' => $programDuration,
            'currency' => $programCurrency,
            'fee' => $programFee,
            'notes' => ($row['program_notes'] ?? null) ?: null,
            'updated_at' => now(),
            ];
            if (Schema::hasColumn('university_programs', 'thesis_type')) {
                $update['thesis_type'] = $thesisType;
            }
            if (Schema::hasColumn('university_programs', 'fee_type')) {
                $feeType = $this->normalizeFeeType((string) ($row['program_fee_type'] ?? ''));
                $update['fee_type'] = $feeType;
            }

            if ($programId > 0) {
                $updated = DB::table('university_programs')
                    ->where('id', $programId)
                    ->where('tenant_id', $tenantId)
                    ->where('university_id', $universityId)
                    ->update($update);
                if ($updated > 0) {
                    continue;
                }
            }

            $match = [
                'tenant_id' => $tenantId,
                'university_id' => $universityId,
                'degree_level' => $degreeLevel,
                'program_name' => $normalizedProgramName,
                'language' => $programLanguage,
            ];
            if (Schema::hasColumn('university_programs', 'thesis_type')) {
                $match['thesis_type'] = $thesisType;
            }
            DB::table('university_programs')->updateOrInsert(
                $match,
                array_merge($update, ['created_at' => now()])
            );
        }
    }

    private function deleteProgramRows(int $tenantId, int $universityId, Request $request): void
    {
        $ids = collect($request->input('deleted_program_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        if (empty($ids) || !Schema::hasTable('university_programs')) {
            return;
        }
        DB::table('university_programs')
            ->where('tenant_id', $tenantId)
            ->where('university_id', $universityId)
            ->whereIn('id', $ids)
            ->delete();
        if (!Schema::hasTable('applications')) {
            return;
        }
        DB::table('applications')
            ->where('tenant_id', $tenantId)
            ->where('university_id', $universityId)
            ->whereNotIn('program', function ($query) use ($tenantId, $universityId) {
                $query->select('program_name')
                    ->from('university_programs')
                    ->where('tenant_id', $tenantId)
                    ->where('university_id', $universityId);
            })
            ->delete();
    }

    private function cleanupApplicationsForUniversity(int $tenantId, int $universityId): void
    {
        if (!Schema::hasTable('applications') || !Schema::hasTable('university_programs')) {
            return;
        }
        DB::table('applications')
            ->where('tenant_id', $tenantId)
            ->where('university_id', $universityId)
            ->whereNotIn('program', function ($query) use ($tenantId, $universityId) {
                $query->select('program_name')
                    ->from('university_programs')
                    ->where('tenant_id', $tenantId)
                    ->where('university_id', $universityId);
            })
            ->delete();
    }

    private function removeDuplicateApplications(int $tenantId, int $universityId): int
    {
        if (!Schema::hasTable('applications')) {
            return 0;
        }
        $rows = DB::table('applications')
            ->where('tenant_id', $tenantId)
            ->where('university_id', $universityId)
            ->selectRaw("student_id, university_id, LOWER(TRIM(program)) as normalized_program, LOWER(TRIM(intake)) as normalized_intake, MIN(id) as keep_id, COUNT(*) as total")
            ->groupByRaw("student_id, university_id, LOWER(TRIM(program)), LOWER(TRIM(intake))")
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $removed = 0;
        foreach ($rows as $row) {
            $deleteIds = DB::table('applications')
                ->where('tenant_id', $tenantId)
                ->where('student_id', $row->student_id)
                ->where('university_id', $row->university_id)
                ->whereRaw('LOWER(TRIM(program)) = ?', [$row->normalized_program])
                ->whereRaw('LOWER(TRIM(intake)) = ?', [$row->normalized_intake])
                ->where('id', '!=', $row->keep_id)
                ->pluck('id')
                ->all();
            if (empty($deleteIds)) {
                continue;
            }
            DB::table('applications')
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $deleteIds)
                ->delete();
            $removed += count($deleteIds);
        }

        return $removed;
    }

    private function dedupeTenantApplications(int $tenantId): int
    {
        if (!Schema::hasTable('applications')) {
            return 0;
        }
        $removed = 0;
        $universityIds = DB::table('applications')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('university_id')
            ->distinct()
            ->pluck('university_id');
        foreach ($universityIds as $universityId) {
            $removed += $this->removeDuplicateApplications($tenantId, (int) $universityId);
        }
        return $removed;
    }

    private function removeDuplicatePrograms(int $tenantId, int $universityId): int
    {
        if (!Schema::hasTable('university_programs')) {
            return 0;
        }
        $hasThesisColumn = Schema::hasColumn('university_programs', 'thesis_type');
        $hasFeeTypeColumn = Schema::hasColumn('university_programs', 'fee_type');
        $columns = ['id', 'degree_level', 'program_name', 'language', 'duration', 'currency', 'fee', 'notes'];
        if ($hasThesisColumn) {
            $columns[] = 'thesis_type';
        }
        if ($hasFeeTypeColumn) {
            $columns[] = 'fee_type';
        }

        $rows = DB::table('university_programs')
            ->where('tenant_id', $tenantId)
            ->where('university_id', $universityId)
            ->whereNotNull('program_name')
            ->where('program_name', '!=', '')
            ->orderBy('id')
            ->get($columns);

        $groups = [];
        foreach ($rows as $row) {
            $groups[$this->programDuplicateKey($row, $hasThesisColumn)][] = $row;
        }

        $removed = 0;
        foreach ($groups as $groupRows) {
            if (count($groupRows) < 2) {
                continue;
            }
            usort($groupRows, fn ($a, $b) => $this->programCompletenessScore($b) <=> $this->programCompletenessScore($a));
            $keeper = array_shift($groupRows);
            $deleteIds = array_map(fn ($row) => (int) $row->id, $groupRows);

            $merged = [
                'degree_level' => $keeper->degree_level,
                'program_name' => $keeper->program_name,
                'language' => $keeper->language,
                'duration' => $keeper->duration,
                'currency' => $keeper->currency,
                'fee' => $keeper->fee,
                'notes' => $keeper->notes,
                'updated_at' => now(),
            ];
            if ($hasThesisColumn) {
                $merged['thesis_type'] = $keeper->thesis_type ?? null;
            }
            if ($hasFeeTypeColumn) {
                $merged['fee_type'] = $keeper->fee_type ?? null;
            }
            foreach ($groupRows as $duplicate) {
                foreach ($merged as $field => $value) {
                    if ($field === 'updated_at') {
                        continue;
                    }
                    if (($value === null || $value === '') && isset($duplicate->{$field}) && $duplicate->{$field} !== '') {
                        $merged[$field] = $duplicate->{$field};
                    }
                }
            }

            DB::table('university_programs')
                ->where('tenant_id', $tenantId)
                ->where('university_id', $universityId)
                ->where('id', $keeper->id)
                ->update($merged);
            DB::table('university_programs')
                ->where('tenant_id', $tenantId)
                ->where('university_id', $universityId)
                ->whereIn('id', $deleteIds)
                ->delete();
            $removed += count($deleteIds);
        }

        return $removed;
    }

    private function dedupeTenantPrograms(int $tenantId): int
    {
        if (!Schema::hasTable('university_programs')) {
            return 0;
        }
        $removed = 0;
        $universityIds = DB::table('university_programs')
            ->where('tenant_id', $tenantId)
            ->distinct()
            ->pluck('university_id');
        foreach ($universityIds as $universityId) {
            $removed += $this->removeDuplicatePrograms($tenantId, (int) $universityId);
        }
        return $removed;
    }

    private function programDuplicateKey(object $row, bool $hasThesisColumn): string
    {
        $parts = [
            $this->normalizeComparable((string) ($row->program_name ?? '')),
            $this->normalizeComparable((string) ($row->degree_level ?? '')),
            $this->normalizeComparable((string) ($row->language ?? '')),
        ];
        if ($hasThesisColumn) {
            $parts[] = $this->normalizeComparable((string) ($row->thesis_type ?? ''));
        }
        return implode('|', $parts);
    }

    private function programCompletenessScore(object $row): int
    {
        $score = 0;
        foreach (['fee', 'currency', 'duration', 'notes', 'language', 'degree_level', 'thesis_type', 'fee_type'] as $field) {
            if (isset($row->{$field}) && $row->{$field} !== null && $row->{$field} !== '') {
                $score++;
            }
        }
        return $score;
    }

    private function normalizeComparable(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return $value;
    }

    private function normalizeUniversityLanguage(string $value): ?string
    {
        $needle = mb_strtolower(trim($value));
        if ($needle === '') {
            return null;
        }
        if (in_array($needle, ['tr', 'turkish', 'ترکی'], true)) {
            return 'Turkish';
        }
        if (in_array($needle, ['en', 'english', 'انگلیسی'], true)) {
            return 'English';
        }
        if (in_array($needle, ['both', 'english/turkish', 'turkish/english', 'هردو'], true)) {
            return 'Both';
        }
        return null;
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

    private function normalizeProgramLanguage(string $value): ?string
    {
        $needle = mb_strtolower(trim($value));
        if ($needle === '') {
            return null;
        }
        if (in_array($needle, ['tr', 'turkish', 'turkce', 'türkçe'], true)) {
            return 'Turkish';
        }
        if (in_array($needle, ['en', 'english'], true)) {
            return 'English';
        }
        if (in_array($needle, ['ar', 'arabic'], true)) {
            return 'Arabic';
        }
        if (in_array($needle, ['both', 'english/turkish', 'turkish/english'], true)) {
            return 'Both';
        }
        return null;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function cleanText(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        return mb_substr($text, 0, $maxLength);
    }

    private function normalizeFee(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $normalized = str_replace(['$', '€', '£', '₺', ' '], '', $raw);
        $normalized = str_replace(',', '.', $normalized);
        if (!is_numeric($normalized)) {
            return null;
        }
        return (float) $normalized;
    }

    private function normalizeThesisType(string $value): ?string
    {
        $v = mb_strtolower(trim($value));
        if ($v === '') {
            return null;
        }
        if (in_array($v, ['tezli', 'thesis', 'with_thesis', 'with thesis'], true)) {
            return 'thesis';
        }
        if (in_array($v, ['tezsiz', 'non_thesis', 'without thesis', 'without_thesis'], true)) {
            return 'non_thesis';
        }
        if (in_array($v, ['both', 'herd', 'both options'], true)) {
            return 'both';
        }
        return null;
    }

    private function normalizeFeeType(string $value): ?string
    {
        $v = mb_strtolower(trim($value));
        if ($v === '') {
            return null;
        }
        if (in_array($v, ['semester', 'per_semester', 'term', 'ترمی'], true)) {
            return 'per_semester';
        }
        if (in_array($v, ['yearly', 'annual', 'سالانه'], true)) {
            return 'yearly';
        }
        if (in_array($v, ['total', 'total_program', 'program_total', 'کل دوره'], true)) {
            return 'total_program';
        }
        return null;
    }

    private function displayText(string $value): string
    {
        $text = trim($value);
        if ($text === '') {
            return '';
        }
        return $this->tryFixMojibake($text);
    }

    private function displayLongText(string $value): string
    {
        $text = $this->displayText($value);
        if ($text === '') {
            return '';
        }
        $text = preg_replace('/\s*\|\s*/u', ' | ', $text) ?? $text;
        $text = preg_replace('/\s*;\s*/u', ";\n", $text) ?? $text;
        $text = preg_replace('/\R{3,}/u', "\n\n", $text) ?? $text;
        return trim($text);
    }

    private function tryFixMojibake(string $text): string
    {
        $badScore = $this->mojibakeScore($text);
        $candidates = [$text];

        $latin1 = @mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        if (is_string($latin1)) {
            $candidates[] = $latin1;
        }
        $cp1252 = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        if (is_string($cp1252)) {
            $candidates[] = $cp1252;
        }

        $best = $text;
        $bestScore = $badScore;
        foreach ($candidates as $candidate) {
            $score = $this->mojibakeScore($candidate);
            if ($score < $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }
        return $best;
    }

    private function mojibakeScore(string $text): int
    {
        $needles = ['Ã', 'Ä', 'Å', 'Â', 'â€', 'â€™', 'â€œ', 'â€'];
        $score = 0;
        foreach ($needles as $needle) {
            $score += substr_count($text, $needle);
        }
        return $score;
    }

    private function dedupeTenantUniversities(int $tenantId): int
    {
        $removed = 0;
        $rows = DB::table('universities')
            ->where('tenant_id', $tenantId)
            ->selectRaw("LOWER(name) as lname, LOWER(country) as lcountry, LOWER(COALESCE(city,'')) as lcity, MIN(id) as keep_id, COUNT(*) as cnt")
            ->groupByRaw("LOWER(name), LOWER(country), LOWER(COALESCE(city,''))")
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($rows as $row) {
            $dupeIds = DB::table('universities')
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [$row->lname])
                ->whereRaw('LOWER(country) = ?', [$row->lcountry])
                ->whereRaw("LOWER(COALESCE(city,'')) = ?", [$row->lcity])
                ->where('id', '!=', $row->keep_id)
                ->pluck('id')
                ->all();

            if (empty($dupeIds)) {
                continue;
            }

            if (Schema::hasTable('university_programs')) {
                DB::table('university_programs')->whereIn('university_id', $dupeIds)->update(['university_id' => $row->keep_id]);
                $this->removeDuplicatePrograms($tenantId, (int) $row->keep_id);
            }
            if (Schema::hasTable('applications')) {
                DB::table('applications')->whereIn('university_id', $dupeIds)->update(['university_id' => $row->keep_id]);
                $this->removeDuplicateApplications($tenantId, (int) $row->keep_id);
            }
            if (Schema::hasTable('scholarships')) {
                DB::table('scholarships')->whereIn('university_id', $dupeIds)->update(['university_id' => $row->keep_id]);
            }
            DB::table('universities')->whereIn('id', $dupeIds)->delete();
            $removed += count($dupeIds);
        }

        return $removed;
    }
}
