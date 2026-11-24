<?php

namespace App\Http\Controllers;

use App\Support\Export\SpreadsheetExport;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

use App\Models\Application;
use App\Models\Student;
use App\Models\University;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class SearchController extends Controller
{
    private const DEGREE_ALIASES = [
        'Diploma' => ['Diploma', 'High School Diploma', 'دیپلم'],
        'Associate' => ['Associate', 'Associate Degree', 'فوق دیپلم', 'کاردانی'],
        'Bachelor' => ['Bachelor', 'BSc', 'BA', 'کارشناسی', 'لیسانس'],
        'Master' => ['Master', 'MSc', 'MA', 'کارشناسی ارشد', 'فوق لیسانس'],
        'PhD' => ['PhD', 'Doctorate', 'دکتری', 'دکترا'],
    ];

    public function index(Request $request): View|HttpResponse
    {
        $user = $this->authUser($request);
        $q = trim((string) $request->query('q', ''));
        $keywords = trim((string) $request->query('keywords', $q));
        $countryUniversity = trim((string) $request->query('country_university', ''));
        $cityUniversity = trim((string) $request->query('city_university', ''));
        $universityType = trim((string) $request->query('university_type', ''));
        $universityName = trim((string) $request->query('university_name', ''));
        $universityId = (int) $request->query('university_id', 0);
        $programName = trim((string) $request->query('program_name', ''));
        $degree = trim((string) $request->query('degree', ''));
        $degree = $this->normalizeDegreeLabel($degree) ?: $degree;
        $thesisType = trim((string) $request->query('thesis_type', ''));
        $programLanguage = trim((string) $request->query('program_language', ''));
        $studyField = trim((string) $request->query('study_field', ''));
        $stage = trim((string) $request->query('stage', ''));
        $country = trim((string) $request->query('country', ''));
        $status = trim((string) $request->query('status', ''));
        $universityLanguage = trim((string) $request->query('university_language', ''));
        $hasCityColumn = Schema::hasColumn('universities', 'city');
        $hasTypeColumn = Schema::hasColumn('universities', 'institution_type');
        $hasProgramsTable = Schema::hasTable('university_programs');
        $hasThesisColumn = $hasProgramsTable && Schema::hasColumn('university_programs', 'thesis_type');
        $export = trim((string) $request->query('export', ''));

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
            ->when($keywords !== '', fn ($query) => $query->where(function ($sub) use ($keywords) {
                $sub->where('full_name', 'like', "%{$keywords}%")
                    ->orWhere('email', 'like', "%{$keywords}%")
                    ->orWhere('phone', 'like', "%{$keywords}%")
                    ->orWhere('field_of_study', 'like', "%{$keywords}%");
            }))
            ->when($studyField !== '', fn ($query) => $query->where('field_of_study', 'like', "%{$studyField}%"))
            ->when(in_array($universityLanguage, ['en', 'tr'], true), fn ($query) => $query->where('preferred_university_language', $universityLanguage))
            ->when($stage !== '', fn ($query) => $query->where('stage', $stage))
            ->when($country !== '', fn ($query) => $query->where('target_country', 'like', "%{$country}%"))
            ->latest('id')
            ->limit(30)
            ->get();

        $programUniversityIds = null;
        if ($hasProgramsTable && ($degree !== '' || $studyField !== '' || $universityId > 0 || $programName !== '' || in_array($thesisType, ['thesis','non_thesis','both'], true) || in_array($programLanguage, ['English','Turkish','Arabic','Both'], true))) {
            $degreeAliases = $this->degreeAliasesForFilter($degree);
            $programUniversityIds = DB::table('university_programs')
                ->where('tenant_id', $user->tenant_id)
                ->when($universityId > 0, fn ($q) => $q->where('university_id', $universityId))
                ->when($degree !== '', function ($query) use ($degreeAliases) {
                    $query->where(function ($sub) use ($degreeAliases) {
                        foreach ($degreeAliases as $alias) {
                            $sub->orWhere('degree_level', 'like', "%{$alias}%");
                        }
                    });
                })
                ->when($studyField !== '', fn ($query) => $query->where('program_name', 'like', "%{$studyField}%"))
                ->when($programName !== '', fn ($query) => $query->where('program_name', 'like', "%{$programName}%"))
                ->when($hasThesisColumn && in_array($thesisType, ['thesis','non_thesis','both'], true), fn ($q) => $q->where(function ($sub) use ($thesisType) {
                    $sub->where('thesis_type', $thesisType);
                    if ($thesisType !== 'both') {
                        $sub->orWhere('thesis_type', 'both');
                    }
                }))
                ->when(in_array($programLanguage, ['English','Turkish','Arabic','Both'], true), fn ($q) => $q->where('language', $programLanguage))
                ->distinct()
                ->pluck('university_id')
                ->all();
        }
        $keywordProgramUniversityIds = null;
        if ($hasProgramsTable && $keywords !== '') {
            $keywordProgramUniversityIds = DB::table('university_programs')
                ->where('tenant_id', $user->tenant_id)
                ->where(function ($query) use ($keywords) {
                    $query->where('program_name', 'like', "%{$keywords}%")
                        ->orWhere('notes', 'like', "%{$keywords}%")
                        ->orWhere('degree_level', 'like', "%{$keywords}%");
                })
                ->distinct()
                ->pluck('university_id')
                ->all();
        }

        $applications = Application::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('applications.view_all'), function ($query) use ($user) {
                $query->whereIn('student_id', function ($sub) use ($user) {
                    $sub->select('id')->from('students')->where('tenant_id', $user->tenant_id)
                        ->when($user->role_slug === 'agent', fn ($q) => $q->where('agent_id', $user->id))
                        ->when($user->role_slug === 'sub_agent', fn ($q) => $q->where('sub_agent_id', $user->id));
                });
            })
            ->when($keywords !== '', fn ($query) => $query->where(function ($sub) use ($keywords) {
                $sub->where('program', 'like', "%{$keywords}%")
                    ->orWhere('intake', 'like', "%{$keywords}%")
                    ->orWhere('notes', 'like', "%{$keywords}%");
            }))
            ->when($degree !== '', fn ($query) => $query->where('program', 'like', "%{$degree}%"))
            ->when($studyField !== '', fn ($query) => $query->where('program', 'like', "%{$studyField}%"))
            ->when($programName !== '', fn ($query) => $query->where('program', 'like', "%{$programName}%"))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->limit(30)
            ->get();

        $universities = University::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('universities.view_all'), fn ($query) => $query->where('created_by_user_id', $user->id))
            ->when($keywords !== '', fn ($query) => $query->where(function ($sub) use ($keywords, $keywordProgramUniversityIds) {
                $sub->where('name', 'like', "%{$keywords}%")
                    ->orWhere('country', 'like', "%{$keywords}%")
                    ->orWhere('programs_summary', 'like', "%{$keywords}%")
                    ->orWhere('description', 'like', "%{$keywords}%");
                if (is_array($keywordProgramUniversityIds) && !empty($keywordProgramUniversityIds)) {
                    $sub->orWhereIn('id', $keywordProgramUniversityIds);
                }
            }))
            ->when($countryUniversity !== '', fn ($query) => $query->where('country', $countryUniversity))
            ->when($universityName !== '', fn ($query) => $query->where('name', 'like', "%{$universityName}%"))
            ->when($universityId > 0, fn ($query) => $query->where('id', $universityId))
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
            ->orderBy('name')
            ->orderBy('id');

        if ($export === 'universities') {
            // Export every matching university (the screen only previews the first 20).
            return $this->exportUniversities((clone $universities)->limit(5000)->get(), $request);
        }
        $universities = $universities->limit(20)->get();
        if ($export === 'programs') {
            return $this->exportPrograms(
                $user,
                $request,
                $keywords,
                $countryUniversity,
                $universityId,
                $degree,
                $studyField,
                $programName,
                $thesisType,
                $programLanguage
            );
        }

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

        $citiesByCountry = [];
        $universitiesByCountry = [];
        if ($hasCityColumn) {
            $cityRows = University::query()
                ->forTenant($user->tenant_id, $user->role_slug)
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
        }
        $uRows = University::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('universities.view_all'), fn ($query) => $query->where('created_by_user_id', $user->id))
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'country']);
        foreach ($uRows as $row) {
            $universitiesByCountry[$row->country] ??= [];
            $universitiesByCountry[$row->country][] = ['id' => $row->id, 'name' => $row->name];
        }
        foreach ($citiesByCountry as $countryKey => $cities) {
            $citiesByCountry[$countryKey] = collect($cities)->unique()->sort()->values()->all();
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
        if (Schema::hasTable('study_fields')) {
            $studyFieldOptions = DB::table('study_fields')
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', 1)
                ->orderBy('name')
                ->pluck('name')
                ->filter()
                ->values()
                ->all();
        }
        if (empty($studyFieldOptions) && $hasProgramsTable) {
            $degreeAliases = $this->degreeAliasesForFilter($degree);
            $studyFieldOptions = DB::table('university_programs')
                ->where('tenant_id', $user->tenant_id)
                ->when($degree !== '', function ($query) use ($degreeAliases) {
                    $query->where(function ($sub) use ($degreeAliases) {
                        foreach ($degreeAliases as $alias) {
                            $sub->orWhere('degree_level', 'like', "%{$alias}%");
                        }
                    });
                })
                ->whereNotNull('program_name')
                ->where('program_name', '!=', '')
                ->orderBy('program_name')
                ->limit(200)
                ->pluck('program_name')
                ->map(function (string $name) {
                    $parts = preg_split('/[-,(|]/', $name);
                    return trim($parts[0] ?? $name);
                })
                ->filter()
                ->unique()
                ->values()
                ->all();
        }
        if (empty($studyFieldOptions)) {
            $studyFieldOptions = Student::query()
                ->forTenant($user->tenant_id, $user->role_slug)
                ->whereNotNull('field_of_study')
                ->where('field_of_study', '!=', '')
                ->distinct()
                ->orderBy('field_of_study')
                ->pluck('field_of_study')
                ->values()
                ->all();
        }
        if (empty($studyFieldOptions)) {
            $studyFieldOptions = ['Business', 'Computer Science', 'Engineering', 'Medicine', 'Law', 'Architecture'];
        }

        $programOptions = [];
        if ($hasProgramsTable && $universityId > 0) {
            $programOptions = DB::table('university_programs')
                ->where('tenant_id', $user->tenant_id)
                ->where('university_id', $universityId)
                ->when($degree !== '', fn ($q) => $q->where('degree_level', $degree))
                ->when($hasThesisColumn && in_array($thesisType, ['thesis', 'non_thesis', 'both'], true), fn ($q) => $q->where(function ($sub) use ($thesisType) {
                    $sub->where('thesis_type', $thesisType);
                    if ($thesisType !== 'both') {
                        $sub->orWhere('thesis_type', 'both');
                    }
                }))
                ->when(in_array($programLanguage, ['English','Turkish','Arabic','Both'], true), fn ($q) => $q->where('language', $programLanguage))
                ->orderBy('program_name')
                ->pluck('program_name')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }
        $thesisOptions = ['thesis' => 'Thesis', 'non_thesis' => 'Non-Thesis', 'both' => 'Both'];

        return view('search.index', compact(
            'q',
            'keywords',
            'countryUniversity',
            'cityUniversity',
            'universityType',
            'universityName',
            'universityId',
            'programName',
            'degree',
            'thesisType',
            'programLanguage',
            'studyField',
            'stage',
            'country',
            'status',
            'universityLanguage',
            'students',
            'applications',
            'universities',
            'countryOptions',
            'citiesByCountry',
            'universitiesByCountry',
            'degreeOptions',
            'studyFieldOptions',
            'programOptions',
            'thesisOptions'
        ));
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
                        ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('universities.view_all'), fn ($q) => $q->where('universities.created_by_user_id', $user->id));
            })
            ->when($degree !== '', fn ($q) => $q->where('degree_level', $degree))
            ->when($hasThesisColumn && in_array($thesisType, ['thesis', 'non_thesis', 'both'], true), fn ($q) => $q->where(function ($sub) use ($thesisType) {
                $sub->where('thesis_type', $thesisType);
                if ($thesisType !== 'both') {
                    $sub->orWhere('thesis_type', 'both');
                }
            }))
            ->when(in_array($programLanguage, ['English','Turkish','Arabic','Both'], true), fn ($q) => $q->where('language', $programLanguage))
            ->orderBy('program_name')
            ->limit(800);
        $select = ['program_name', 'degree_level'];
        if ($hasThesisColumn) {
            $select[] = 'thesis_type';
        }
        $rows = $query->get($select);
        return response()->json($rows);
    }

    private function exportUniversities($rows, Request $request): HttpResponse
    {
        $programLangMap = [];
        $programCountMap = [];
        $universityIds = collect($rows)->pluck('id')->filter()->values()->all();
        if (!empty($universityIds) && Schema::hasTable('university_programs')) {
            foreach (array_chunk($universityIds, 1000) as $chunk) {
                $langRows = DB::table('university_programs')
                    ->whereIn('university_id', $chunk)
                    ->select('university_id', 'language')
                    ->get();
                foreach ($langRows as $langRow) {
                    $programCountMap[$langRow->university_id] = ($programCountMap[$langRow->university_id] ?? 0) + 1;
                    if (trim((string) $langRow->language) !== '') {
                        $programLangMap[$langRow->university_id][$langRow->language] = true;
                    }
                }
            }
        }

        $columns = [
            ['label' => 'University', 'type' => 'string'],
            ['label' => 'Country', 'type' => 'string'],
            ['label' => 'City', 'type' => 'string'],
            ['label' => 'Type', 'type' => 'string'],
            ['label' => 'University Language', 'type' => 'string'],
            ['label' => 'Program Languages', 'type' => 'string'],
            ['label' => 'Programs (count)', 'type' => 'integer'],
            ['label' => 'Programs Summary', 'type' => 'string', 'width' => 60],
        ];

        $data = collect($rows)->map(fn ($row) => [
            $row->name,
            $row->country,
            $row->city ?? '',
            SpreadsheetExport::human($row->institution_type ?? ''),
            $row->language ?? '',
            implode(', ', array_keys($programLangMap[$row->id] ?? [])),
            $programCountMap[$row->id] ?? 0,
            $row->programs_summary ?? '',
        ]);

        return SpreadsheetExport::make('University Search Results', $columns, $data, $this->exportFilters($request))
            ->download('universities');
    }

    /** Active search filters, labelled for the export header. */
    private function exportFilters(Request $request): array
    {
        $labels = [
            'q' => 'Keywords', 'country_university' => 'Country', 'city_university' => 'City',
            'university_name' => 'University', 'university_type' => 'Type', 'degree' => 'Degree',
            'study_field' => 'Field', 'program_name' => 'Program', 'thesis_type' => 'Thesis',
            'program_language' => 'Language',
        ];
        $filters = [];
        foreach ($labels as $key => $label) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                $filters[$label] = SpreadsheetExport::human($value);
            }
        }

        return $filters;
    }

    private function exportPrograms(
        $user,
        Request $request,
        string $keywords,
        string $countryUniversity,
        int $universityId,
        string $degree,
        string $studyField,
        string $programName,
        string $thesisType,
        string $programLanguage
    ): HttpResponse
    {
        $tenantId = (int) $user->tenant_id;
        $rows = collect();
        if (Schema::hasTable('university_programs')) {
            $hasThesisColumn = Schema::hasColumn('university_programs', 'thesis_type');
            $hasFeeTypeColumn = Schema::hasColumn('university_programs', 'fee_type');
            $degreeAliases = $this->degreeAliasesForFilter($degree);
            $keywordVariants = $this->keywordVariants($keywords);
            $columns = [
                'university_programs.university_id',
                'universities.name as university_name',
                'universities.country',
                'university_programs.program_name',
                'university_programs.degree_level',
                'university_programs.fee',
                'university_programs.language',
            ];
            if ($hasThesisColumn) {
                $columns[] = 'university_programs.thesis_type';
            }
            if ($hasFeeTypeColumn) {
                $columns[] = 'university_programs.fee_type';
            }
            foreach (['duration', 'currency', 'discounted_fee_full', 'discounted_fee_installment'] as $optional) {
                if (Schema::hasColumn('university_programs', $optional)) {
                    $columns[] = 'university_programs.'.$optional;
                }
            }
            // University-level defaults, used when a program has no language/tuition of its own.
            $columns[] = 'universities.language as university_language';
            $columns[] = 'universities.currency as university_currency';
            $columns[] = 'universities.tuition_range as university_tuition_range';
            if (Schema::hasColumn('universities', 'tuition_fee_type')) {
                $columns[] = 'universities.tuition_fee_type as university_fee_type';
            }
            $columns[] = 'university_programs.notes';
            $rows = DB::table('university_programs')
                ->join('universities', function ($join) use ($tenantId) {
                    $join->on('universities.id', '=', 'university_programs.university_id')
                        ->where('universities.tenant_id', '=', $tenantId);
                })
                ->where('university_programs.tenant_id', $tenantId)
                // Same visibility rule as the university list: agents only see universities they created.
                ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('universities.view_all'), fn ($query) => $query->where('universities.created_by_user_id', $user->id))
                ->when(!empty($keywordVariants), function ($query) use ($keywordVariants) {
                    $query->where(function ($sub) use ($keywordVariants) {
                        foreach ($keywordVariants as $term) {
                            $sub->orWhere('university_programs.program_name', 'like', "%{$term}%")
                                ->orWhere('university_programs.degree_level', 'like', "%{$term}%")
                                ->orWhere('university_programs.language', 'like', "%{$term}%")
                                ->orWhere('university_programs.notes', 'like', "%{$term}%")
                                ->orWhere('universities.name', 'like', "%{$term}%");
                        }
                    });
                })
                ->when($countryUniversity !== '', fn ($query) => $query->where('universities.country', $countryUniversity))
                // Same university filters as the on-screen list (previously ignored, so the export
                // mixed in programs from universities the user had filtered out).
                ->when(trim((string) $request->query('university_name', '')) !== '', fn ($query) => $query->where('universities.name', 'like', '%'.trim((string) $request->query('university_name')).'%'))
                ->when(trim((string) $request->query('city_university', '')) !== '', function ($query) use ($request) {
                    $city = trim((string) $request->query('city_university'));
                    if (Schema::hasColumn('universities', 'city')) {
                        $query->where('universities.city', $city);
                    } else {
                        $query->where(fn ($sub) => $sub->where('universities.name', 'like', "%{$city}%")->orWhere('universities.description', 'like', "%{$city}%"));
                    }
                })
                ->when(trim((string) $request->query('university_type', '')) !== '' && Schema::hasColumn('universities', 'institution_type'), fn ($query) => $query->whereRaw('LOWER(universities.institution_type) = ?', [mb_strtolower(trim((string) $request->query('university_type')))]))
                ->when($universityId > 0, fn ($query) => $query->where('university_programs.university_id', $universityId))
                ->when($degree !== '', function ($query) use ($degreeAliases) {
                    $query->where(function ($sub) use ($degreeAliases) {
                        foreach ($degreeAliases as $alias) {
                            $sub->orWhere('university_programs.degree_level', 'like', "%{$alias}%");
                        }
                    });
                })
                ->when($studyField !== '', fn ($query) => $query->where('university_programs.program_name', 'like', "%{$studyField}%"))
                ->when($programName !== '', fn ($query) => $query->where('university_programs.program_name', 'like', "%{$programName}%"))
                ->when(in_array($programLanguage, ['English', 'Turkish', 'Arabic', 'Both'], true), fn ($query) => $query->where('university_programs.language', $programLanguage))
                ->when($hasThesisColumn && in_array($thesisType, ['thesis', 'non_thesis', 'both'], true), fn ($query) => $query->where(function ($sub) use ($thesisType) {
                    $sub->where('thesis_type', $thesisType);
                    if ($thesisType !== 'both') {
                        $sub->orWhere('thesis_type', 'both');
                    }
                }))
                ->orderBy('university_programs.program_name')
                ->limit(3000)
                ->get($columns);
        }
        $columns = [
            ['label' => 'University', 'type' => 'string'],
            ['label' => 'Country', 'type' => 'string'],
            ['label' => 'Program', 'type' => 'string'],
            ['label' => 'Degree', 'type' => 'string'],
            ['label' => 'Thesis Type', 'type' => 'string'],
            ['label' => 'Language', 'type' => 'string'],
            ['label' => 'Duration', 'type' => 'string'],
            ['label' => 'Currency', 'type' => 'string'],
            ['label' => 'Tuition', 'type' => 'money'],
            ['label' => 'Tuition Basis', 'type' => 'string'],
            ['label' => 'Discounted (Full Payment)', 'type' => 'money'],
            ['label' => 'Discounted (Installments)', 'type' => 'money'],
            ['label' => 'University Tuition Range', 'type' => 'string'],
            ['label' => 'Data Notes', 'type' => 'string', 'width' => 46],
        ];
        $data = $this->completeProgramRows($rows);

        return SpreadsheetExport::make('Program Search Results', $columns, $data, $this->exportFilters($request))
            ->download('programs');
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

    /**
     * Turn raw program rows into complete export rows.
     *
     * 1. Duplicates (same university name + country + program + degree + thesis + language, created by
     *    older imports or by universities registered twice) are merged into one row; every empty field is
     *    filled from its twin, so e.g. one copy's "Yearly" completes the other.
     * 2. Remaining gaps are filled from the university profile (language, currency, fee type, range).
     * 3. Anything still unknown is written explicitly ("Not specified" / "N/A") instead of left blank.
     * Values are never invented; the "Data Notes" column explains where filled values came from.
     */
    private function completeProgramRows($rows)
    {
        $norm = fn ($v) => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $v)) ?? '');
        $blank = fn ($v) => $v === null || trim((string) $v) === '';
        $feeTypeWords = ['total program' => 'total_program', 'total' => 'total_program', 'yearly' => 'yearly', 'per year' => 'yearly', 'annual' => 'yearly', 'per semester' => 'per_semester', 'semester' => 'per_semester'];

        $prepared = collect($rows)->map(function ($row) use ($blank) {
            $row = clone $row;
            if ($blank($row->language ?? null) && preg_match('/Language as imported: ([^|]+)/u', (string) ($row->notes ?? ''), $m)) {
                $row->language_from = 'import';
                $row->language = trim($m[1]);
            }

            return $row;
        });

        // Group duplicates; a group with several different real tuition values stays split per tuition.
        $groups = [];
        foreach ($prepared as $row) {
            $key = implode('|', [
                $norm($row->university_name ?? ''), $norm($row->country ?? ''), $norm($row->program_name ?? ''),
                $norm($row->degree_level ?? ''), $norm($row->thesis_type ?? ''), $norm($row->language ?? ''),
            ]);
            $groups[$key][] = $row;
        }

        $fields = ['duration', 'currency', 'fee', 'fee_type', 'discounted_fee_full', 'discounted_fee_installment',
            'thesis_type', 'language', 'university_language', 'university_currency', 'university_tuition_range', 'university_fee_type'];
        $out = [];
        foreach ($groups as $group) {
            $fees = array_values(array_unique(array_map(fn ($r) => (string) (float) $r->fee, array_filter($group, fn ($r) => !$blank($r->fee ?? null)))));
            $buckets = count($fees) <= 1 ? [$group] : array_map(fn ($fee) => array_values(array_filter($group, fn ($r) => !$blank($r->fee ?? null) && (string) (float) $r->fee === $fee)), $fees);
            $feeless = array_values(array_filter($group, fn ($r) => $blank($r->fee ?? null)));
            foreach ($buckets as $bucket) {
                $members = count($fees) <= 1 ? $bucket : array_merge($bucket, $feeless);
                $base = clone $bucket[0];
                foreach ($fields as $field) {
                    if ($blank($base->{$field} ?? null)) {
                        foreach ($members as $other) {
                            if (!$blank($other->{$field} ?? null)) {
                                $base->{$field} = $other->{$field};
                                break;
                            }
                        }
                    }
                }
                $base->merged_count = count($members);
                $base->tuition_conflict = count($fees) > 1;
                $out[] = $base;
            }
        }

        usort($out, fn ($a, $b) => [$norm($a->program_name), $norm($a->university_name)] <=> [$norm($b->program_name), $norm($b->university_name)]);

        return collect($out)->map(function ($row) use ($blank, $feeTypeWords) {
            $notes = [];
            if (($row->merged_count ?? 1) > 1) {
                $notes[] = 'Merged '.$row->merged_count.' duplicate rows';
            }
            if (!empty($row->tuition_conflict)) {
                $notes[] = 'Another row lists a different tuition for this program — please verify';
            }

            // Language
            $language = trim((string) ($row->language ?? ''));
            if (($row->language_from ?? '') === 'import') {
                $notes[] = 'Language as typed on import';
            }
            if ($language === '' && !$blank($row->university_language ?? null)) {
                $language = (string) $row->university_language;
                $notes[] = 'Language from university profile';
            }

            // Currency (normalised: EURO/Euro/€ -> EUR ...)
            $currency = $this->normalizeCurrency((string) ($row->currency ?? '')) ?: $this->normalizeCurrency((string) ($row->university_currency ?? ''));

            // Tuition basis and range ("total program" typed into the range field is really a basis)
            $range = trim((string) ($row->university_tuition_range ?? ''));
            $rangeAsBasis = $feeTypeWords[mb_strtolower($range)] ?? null;
            if ($rangeAsBasis !== null) {
                $range = '';
            }
            $basis = $this->feeTypeLabel($row->fee_type ?? '');
            if ($basis === '' && $this->feeTypeLabel($row->university_fee_type ?? '') !== '') {
                $basis = $this->feeTypeLabel($row->university_fee_type);
                $notes[] = 'Tuition basis from university profile';
            } elseif ($basis === '' && $rangeAsBasis !== null) {
                $basis = $this->feeTypeLabel($rangeAsBasis);
                $notes[] = 'Tuition basis from university profile';
            }

            $fee = $blank($row->fee ?? null) ? null : (float) $row->fee;
            if ($fee === null) {
                $notes[] = $range !== '' ? 'No program tuition — see university range' : 'Tuition missing';
            } elseif ($fee > 0 && $fee < 100) {
                $notes[] = 'Tuition looks too low — please verify';
            }
            if ($language === '') {
                $notes[] = 'Language missing';
            }

            $degree = trim((string) ($row->degree_level ?? ''));
            $thesis = SpreadsheetExport::human($row->thesis_type ?? '');
            if ($thesis === '') {
                $thesis = preg_match('/master|msc|m\.s|ma\b|mba/i', $degree) ? 'Not specified' : 'N/A';
            }

            $na = 'Not specified';

            return [
                $row->university_name ?: $na,
                $row->country ?: $na,
                $row->program_name ?: $na,
                $degree ?: $na,
                $thesis,
                $language ?: $na,
                $this->normalizeDuration((string) ($row->duration ?? '')) ?: $na,
                $currency ?: $na,
                $fee ?? $na,
                $basis ?: $na,
                $blank($row->discounted_fee_full ?? null) ? 'None listed' : (float) $row->discounted_fee_full,
                $blank($row->discounted_fee_installment ?? null) ? 'None listed' : (float) $row->discounted_fee_installment,
                $range ?: $na,
                $notes ? implode('; ', $notes) : 'Complete',
            ];
        });
    }

    private function normalizeCurrency(string $value): string
    {
        $v = mb_strtoupper(trim($value));
        if ($v === '') {
            return '';
        }

        return match (true) {
            in_array($v, ['EURO', 'EUROS', 'EUR', '€'], true) => 'EUR',
            in_array($v, ['USD', 'US$', '$', 'DOLLAR', 'DOLLARS', 'US DOLLAR'], true) => 'USD',
            in_array($v, ['TRY', 'TL', '₺', 'LIRA', 'TURKISH LIRA'], true) => 'TRY',
            in_array($v, ['GBP', '£', 'POUND', 'POUNDS'], true) => 'GBP',
            default => $v,
        };
    }

    /** "4" -> "4 years", "3,5 - 5" -> "3.5–5 years"; text that already has a unit is kept. */
    private function normalizeDuration(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }
        if (preg_match('/^(\d+(?:[.,]\d+)?)(?:\s*[-–]\s*(\d+(?:[.,]\d+)?))?$/u', $v, $m)) {
            $from = str_replace(',', '.', $m[1]);
            $to = isset($m[2]) ? str_replace(',', '.', $m[2]) : null;

            return $to !== null ? "{$from}–{$to} years" : $from.((float) $from === 1.0 ? ' year' : ' years');
        }

        return $v;
    }

    private function feeTypeLabel(?string $value): string
    {
        return match ($value) {
            'per_semester' => 'Per semester',
            'yearly' => 'Yearly',
            'total_program' => 'Total program',
            default => '',
        };
    }

    private function keywordVariants(string $keywords): array
    {
        $base = trim($keywords);
        if ($base === '') {
            return [];
        }
        $normalized = strtr(mb_strtolower($base), [
            'ı' => 'i',
            'İ' => 'i',
            'ş' => 's',
            'ğ' => 'g',
            'ü' => 'u',
            'ö' => 'o',
            'ç' => 'c',
        ]);
        return collect([$base, $normalized])->filter()->unique()->values()->all();
    }
}
