<?php

namespace App\Http\Controllers;

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

    public function index(Request $request): View|StreamedResponse
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
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($export === 'universities') {
            return $this->exportUniversities($universities);
        }
        if ($export === 'programs') {
            return $this->exportPrograms(
                $user->tenant_id,
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

    private function exportUniversities($rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            $universityIds = collect($rows)->pluck('id')->filter()->values()->all();
            $programLangMap = [];
            if (!empty($universityIds) && Schema::hasTable('university_programs')) {
                $langRows = DB::table('university_programs')
                    ->whereIn('university_id', $universityIds)
                    ->whereNotNull('language')
                    ->where('language', '!=', '')
                    ->select('university_id', 'language')
                    ->get();
                foreach ($langRows as $langRow) {
                    $programLangMap[$langRow->university_id] ??= [];
                    $programLangMap[$langRow->university_id][] = $langRow->language;
                }
                foreach ($programLangMap as $key => $langs) {
                    $programLangMap[$key] = implode(', ', array_values(array_unique($langs)));
                }
            }
            fputcsv($out, ['Name', 'Country', 'City', 'Type', 'University Language', 'Program Languages', 'Programs']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->name,
                    $row->country,
                    $row->city ?? '',
                    $row->institution_type ?? '',
                    $row->language ?? '',
                    $programLangMap[$row->id] ?? '',
                    $row->programs_summary ?? '',
                ]);
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="universities-search.csv"');
        return $response;
    }

    private function exportPrograms(
        int $tenantId,
        string $keywords,
        string $countryUniversity,
        int $universityId,
        string $degree,
        string $studyField,
        string $programName,
        string $thesisType,
        string $programLanguage
    ): StreamedResponse
    {
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
            $rows = DB::table('university_programs')
                ->join('universities', function ($join) use ($tenantId) {
                    $join->on('universities.id', '=', 'university_programs.university_id')
                        ->where('universities.tenant_id', '=', $tenantId);
                })
                ->where('university_programs.tenant_id', $tenantId)
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
        $response = new StreamedResponse(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['University ID', 'University', 'Country', 'Program', 'Degree', 'Thesis Type', 'Tuition', 'Tuition Type', 'Language']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->university_id ?? '',
                    $row->university_name ?? '',
                    $row->country ?? '',
                    $row->program_name ?? '',
                    $row->degree_level ?? '',
                    $row->thesis_type ?? '',
                    $row->fee ?? '',
                    $this->feeTypeLabel($row->fee_type ?? ''),
                    $row->language ?? '',
                ]);
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="programs-search.csv"');
        return $response;
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
