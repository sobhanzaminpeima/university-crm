<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('university_programs')) {
            return;
        }

        $hasThesisColumn = Schema::hasColumn('university_programs', 'thesis_type');
        $select = $hasThesisColumn
            ? "tenant_id, university_id, LOWER(TRIM(program_name)) as n_program_name, LOWER(TRIM(degree_level)) as n_degree_level, LOWER(TRIM(language)) as n_language, LOWER(TRIM(thesis_type)) as n_thesis_type, MIN(id) as keep_id, COUNT(*) as total"
            : "tenant_id, university_id, LOWER(TRIM(program_name)) as n_program_name, LOWER(TRIM(degree_level)) as n_degree_level, LOWER(TRIM(language)) as n_language, MIN(id) as keep_id, COUNT(*) as total";
        $groupBy = $hasThesisColumn
            ? "tenant_id, university_id, LOWER(TRIM(program_name)), LOWER(TRIM(degree_level)), LOWER(TRIM(language)), LOWER(TRIM(thesis_type))"
            : "tenant_id, university_id, LOWER(TRIM(program_name)), LOWER(TRIM(degree_level)), LOWER(TRIM(language))";

        $groups = DB::table('university_programs')
            ->whereNotNull('program_name')
            ->where('program_name', '!=', '')
            ->selectRaw($select)
            ->groupByRaw($groupBy)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $query = DB::table('university_programs')
                ->where('tenant_id', $group->tenant_id)
                ->where('university_id', $group->university_id)
                ->whereRaw('LOWER(TRIM(program_name)) = ?', [$group->n_program_name])
                ->whereRaw('LOWER(TRIM(degree_level)) = ?', [$group->n_degree_level])
                ->whereRaw('LOWER(TRIM(language)) = ?', [$group->n_language])
                ->where('id', '!=', $group->keep_id);

            if ($hasThesisColumn) {
                $query->whereRaw('LOWER(TRIM(thesis_type)) = ?', [$group->n_thesis_type]);
            }

            $query->delete();
        }
    }

    public function down(): void
    {
        //
    }
};
