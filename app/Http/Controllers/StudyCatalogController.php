<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class StudyCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authUser($request);
        $fields = collect();
        $intakes = collect();
        $fieldSources = [];
        if (Schema::hasTable('study_fields')) {
            $this->cleanupInvalidStudyFields($user->tenant_id);
            $fields = DB::table('study_fields')->where('tenant_id', $user->tenant_id)->orderBy('name')->get();
            $fieldSources = $this->studyFieldSources($user->tenant_id, $fields->pluck('name')->all());
        }
        if (Schema::hasTable('intake_terms')) {
            $intakes = DB::table('intake_terms')->where('tenant_id', $user->tenant_id)->orderBy('name')->get();
        }
        return view('settings.study-catalogs', compact('fields', 'intakes', 'fieldSources'));
    }

    public function storeField(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        if (!Schema::hasTable('study_fields')) {
            return back()->withErrors(['study_fields' => 'Database structure is not updated: study_fields table is missing.']);
        }
        $data = $request->validate(['name' => 'required|string|max:150']);
        $name = $this->sanitizeStudyFieldValue((string) $data['name']);
        if ($name === null) {
            return back()->withErrors(['name' => 'Invalid study field value.']);
        }
        DB::table('study_fields')->updateOrInsert(
            ['tenant_id' => $user->tenant_id, 'name' => $name],
            ['is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
        );
        $this->cleanupInvalidStudyFields($user->tenant_id);
        return back()->with('success', 'Study field saved.');
    }

    public function deleteField(Request $request, $id): RedirectResponse
    {
        $user = $this->authUser($request);
        if (!Schema::hasTable('study_fields')) {
            return back()->withErrors(['study_fields' => 'Database structure is not updated: study_fields table is missing.']);
        }
        $id = (int) $id;
        if ($id < 1) {
            return back()->withErrors(['study_fields' => 'Invalid study field id.']);
        }
        DB::table('study_fields')->where('tenant_id', $user->tenant_id)->where('id', $id)->delete();
        return back()->with('success', 'Study field deleted.');
    }

    public function bulkDeleteFields(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        if (!Schema::hasTable('study_fields')) {
            return back()->withErrors(['study_fields' => 'Database structure is not updated: study_fields table is missing.']);
        }
        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        if (empty($ids)) {
            return back()->withErrors(['study_fields' => 'No study fields selected.']);
        }
        DB::table('study_fields')
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('id', $ids)
            ->delete();
        return back()->with('success', 'Selected study fields deleted.');
    }

    public function deleteAllFields(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        if (!Schema::hasTable('study_fields')) {
            return back()->withErrors(['study_fields' => 'Database structure is not updated: study_fields table is missing.']);
        }
        DB::table('study_fields')->where('tenant_id', $user->tenant_id)->delete();
        return back()->with('success', 'All study fields deleted.');
    }

    public function updateField(Request $request, $id): RedirectResponse
    {
        $user = $this->authUser($request);
        if (!Schema::hasTable('study_fields')) {
            return back()->withErrors(['study_fields' => 'Database structure is not updated: study_fields table is missing.']);
        }
        $id = (int) $id;
        if ($id < 1) {
            return back()->withErrors(['study_fields' => 'Invalid study field id.']);
        }
        $data = $request->validate(['name' => 'required|string|max:150']);
        $name = $this->sanitizeStudyFieldValue((string) $data['name']);
        if ($name === null) {
            return back()->withErrors(['name' => 'Invalid study field value.']);
        }
        DB::table('study_fields')
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->update([
                'name' => $name,
                'updated_at' => now(),
            ]);
        $this->cleanupInvalidStudyFields($user->tenant_id);
        return back()->with('success', 'Study field updated.');
    }

    public function storeIntake(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $data = $request->validate(['name' => 'required|string|max:120']);
        DB::table('intake_terms')->updateOrInsert(
            ['tenant_id' => $user->tenant_id, 'name' => trim((string) $data['name'])],
            ['is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
        );
        return back()->with('success', 'Intake term saved.');
    }

    public function deleteIntake(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        DB::table('intake_terms')->where('tenant_id', $user->tenant_id)->where('id', $id)->delete();
        return back()->with('success', 'Intake term deleted.');
    }

    public function syncFields(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $this->syncFieldsFromPrograms($user->tenant_id);
        $this->cleanupInvalidStudyFields($user->tenant_id);
        return back()->with('success', 'Study fields synced from programs/students.');
    }

    private function syncFieldsFromPrograms(int $tenantId): void
    {
        if (!Schema::hasTable('study_fields')) {
            return;
        }
        $values = collect();
        if (Schema::hasTable('university_programs')) {
            $programNames = DB::table('university_programs')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('program_name')
                ->where('program_name', '!=', '')
                ->distinct()
                ->pluck('program_name');
            foreach ($programNames as $name) {
                $parts = preg_split('/[-,(|]/', (string) $name);
                $values->push($this->sanitizeStudyFieldValue(trim((string) ($parts[0] ?? $name))));
            }
        }
        if (Schema::hasTable('students')) {
            $studentFields = DB::table('students')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->whereNotNull('field_of_study')
                ->where('field_of_study', '!=', '')
                ->distinct()
                ->pluck('field_of_study');
            foreach ($studentFields as $field) {
                $values->push($this->sanitizeStudyFieldValue(trim((string) $field)));
            }
        }
        foreach ($values->filter()->unique()->values() as $value) {
            DB::table('study_fields')->updateOrInsert(
                ['tenant_id' => $tenantId, 'name' => $value],
                ['is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function sanitizeStudyFieldValue(string $value): ?string
    {
        $value = $this->fixMojibake($value);
        $value = $this->normalizeKnownCatalogValue($value);
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^[0-9\-\s]+$/', $value) === 1) {
            return null;
        }
        if (mb_strlen($value) < 2) {
            return null;
        }
        return mb_substr($value, 0, 150);
    }

    private function cleanupInvalidStudyFields(int $tenantId): void
    {
        if (!Schema::hasTable('study_fields')) {
            return;
        }
        $rows = DB::table('study_fields')->where('tenant_id', $tenantId)->get(['id', 'name']);
        $seen = [];
        foreach ($rows as $row) {
            $clean = $this->sanitizeStudyFieldValue((string) $row->name);
            if ($clean === null) {
                DB::table('study_fields')->where('tenant_id', $tenantId)->where('id', $row->id)->delete();
                continue;
            }
            $normalizedKey = mb_strtolower($clean);
            if (isset($seen[$normalizedKey])) {
                DB::table('study_fields')->where('tenant_id', $tenantId)->where('id', $row->id)->delete();
                continue;
            }
            $existing = DB::table('study_fields')
                ->where('tenant_id', $tenantId)
                ->where('id', '!=', $row->id)
                ->whereRaw('LOWER(name) = ?', [$normalizedKey])
                ->first();
            if ($existing) {
                DB::table('study_fields')->where('tenant_id', $tenantId)->where('id', $row->id)->delete();
                $seen[$normalizedKey] = true;
                continue;
            }
            $seen[$normalizedKey] = true;
            DB::table('study_fields')->where('tenant_id', $tenantId)->where('id', $row->id)->update([
                'name' => $clean,
                'updated_at' => now(),
            ]);
        }
    }

    private function studyFieldSources(int $tenantId, array $fieldNames): array
    {
        $sources = [];
        foreach ($fieldNames as $name) {
            $sources[$name] = [];
        }

        if (Schema::hasTable('university_programs')) {
            $programNames = DB::table('university_programs')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('program_name')
                ->where('program_name', '!=', '')
                ->pluck('program_name');
            $programKeys = [];
            foreach ($programNames as $name) {
                $parts = preg_split('/[-,(|]/', (string) $name);
                $clean = $this->sanitizeStudyFieldValue(trim((string) ($parts[0] ?? $name)));
                if ($clean !== null) {
                    $programKeys[mb_strtolower($clean)] = true;
                }
            }
            foreach ($fieldNames as $name) {
                if (isset($programKeys[mb_strtolower((string) $name)])) {
                    $sources[$name][] = 'Programs';
                }
            }
        }

        if (Schema::hasTable('students')) {
            $studentFields = DB::table('students')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->whereNotNull('field_of_study')
                ->where('field_of_study', '!=', '')
                ->pluck('field_of_study');
            $studentKeys = [];
            foreach ($studentFields as $field) {
                $clean = $this->sanitizeStudyFieldValue((string) $field);
                if ($clean !== null) {
                    $studentKeys[mb_strtolower($clean)] = true;
                }
            }
            foreach ($fieldNames as $name) {
                if (isset($studentKeys[mb_strtolower((string) $name)])) {
                    $sources[$name][] = 'Students';
                }
            }
        }

        foreach ($sources as $name => $list) {
            $sources[$name] = empty($list) ? 'Manual / Unknown' : implode(', ', array_unique($list));
        }
        return $sources;
    }

    private function normalizeKnownCatalogValue(string $value): string
    {
        $needle = mb_strtolower(trim($value));
        if ($needle === '') {
            return '';
        }
        if (str_contains($needle, 'associate degree') || str_contains($needle, 'önlisans') || str_contains($needle, 'onlisans')) {
            return 'Associate Degree';
        }
        if (in_array($needle, ['lisans', 'bachelor', 'bachelor degree'], true)) {
            return 'Bachelor Degree';
        }
        return $value;
    }

    private function fixMojibake(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $replacements = [
            'Ã–' => 'Ö',
            'Ãœ' => 'Ü',
            'Ã‡' => 'Ç',
            'Ä°' => 'İ',
            'Ä±' => 'ı',
            'ÅŸ' => 'ş',
            'Åž' => 'Ş',
            'ÄŸ' => 'ğ',
            'Äž' => 'Ğ',
            'Ã¼' => 'ü',
            'Ã¶' => 'ö',
            'Ã§' => 'ç',
            'â€™' => "'",
            'â€œ' => '"',
            'â€' => '"',
        ];
        return strtr($value, $replacements);
    }
}
