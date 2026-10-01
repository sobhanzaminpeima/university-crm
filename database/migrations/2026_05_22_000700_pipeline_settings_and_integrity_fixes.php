<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('user_pipeline_settings')) {
            Schema::create('user_pipeline_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->longText('settings_json')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'user_id'], 'user_pipeline_settings_tenant_user_uq');
            });
        }

        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table): void {
                $table->index(['tenant_id', 'student_id'], 'applications_tenant_student_idx');
                $table->index(['tenant_id', 'university_id', 'program'], 'applications_tenant_uni_program_idx');
            });
        }

        if (Schema::hasTable('student_messages')) {
            Schema::table('student_messages', function (Blueprint $table): void {
                $table->index(['tenant_id', 'student_id'], 'student_messages_tenant_student_idx');
            });
        }

        $this->cleanupStudyFields();
        $this->cleanupProgramDuplicates();
    }

    public function down(): void
    {
        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table): void {
                $table->dropIndex('applications_tenant_student_idx');
                $table->dropIndex('applications_tenant_uni_program_idx');
            });
        }
        if (Schema::hasTable('student_messages')) {
            Schema::table('student_messages', function (Blueprint $table): void {
                $table->dropIndex('student_messages_tenant_student_idx');
            });
        }
        Schema::dropIfExists('user_pipeline_settings');
    }

    private function cleanupStudyFields(): void
    {
        if (!Schema::hasTable('study_fields')) {
            return;
        }
        $rows = DB::table('study_fields')->orderBy('id')->get(['id', 'tenant_id', 'name']);
        $seen = [];
        foreach ($rows as $row) {
            $clean = trim(preg_replace('/\s+/', ' ', (string) $row->name) ?? (string) $row->name);
            if ($clean === '' || mb_strlen($clean) < 2 || preg_match('/^[0-9\-\s]+$/', $clean) === 1) {
                DB::table('study_fields')->where('id', $row->id)->delete();
                continue;
            }
            $clean = mb_substr($clean, 0, 150);
            $key = $row->tenant_id.'::'.mb_strtolower($clean);
            if (isset($seen[$key])) {
                DB::table('study_fields')->where('id', $row->id)->delete();
                continue;
            }
            $seen[$key] = true;
            DB::table('study_fields')->where('id', $row->id)->update(['name' => $clean, 'updated_at' => now()]);
        }
    }

    private function cleanupProgramDuplicates(): void
    {
        if (!Schema::hasTable('university_programs')) {
            return;
        }
        $groups = DB::table('university_programs')
            ->selectRaw('tenant_id, university_id, LOWER(TRIM(program_name)) as pname, LOWER(TRIM(COALESCE(degree_level,""))) as dlevel, LOWER(TRIM(COALESCE(language,""))) as plang, MIN(id) as keep_id, COUNT(*) as cnt')
            ->groupByRaw('tenant_id, university_id, LOWER(TRIM(program_name)), LOWER(TRIM(COALESCE(degree_level,""))), LOWER(TRIM(COALESCE(language,"")))')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        foreach ($groups as $group) {
            DB::table('university_programs')
                ->where('tenant_id', $group->tenant_id)
                ->where('university_id', $group->university_id)
                ->whereRaw('LOWER(TRIM(program_name)) = ?', [$group->pname])
                ->whereRaw('LOWER(TRIM(COALESCE(degree_level,""))) = ?', [$group->dlevel])
                ->whereRaw('LOWER(TRIM(COALESCE(language,""))) = ?', [$group->plang])
                ->where('id', '!=', $group->keep_id)
                ->delete();
        }
    }
};
