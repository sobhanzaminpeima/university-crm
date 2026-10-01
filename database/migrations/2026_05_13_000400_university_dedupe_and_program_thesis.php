<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('universities')) {
            return;
        }

        $this->mergeDuplicateUniversities();

        DB::table('universities')->whereNull('city')->update(['city' => '']);

        Schema::table('universities', function (Blueprint $table): void {
            if (Schema::hasColumn('universities', 'city')) {
                $table->string('city', 120)->default('')->change();
            }
        });

        try {
            Schema::table('universities', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'name', 'country', 'city'], 'universities_tenant_name_country_city_uq');
            });
        } catch (\Throwable) {
            // index may already exist
        }

        if (Schema::hasTable('university_programs')) {
            Schema::table('university_programs', function (Blueprint $table): void {
                if (!Schema::hasColumn('university_programs', 'thesis_type')) {
                    $table->string('thesis_type', 20)->nullable()->after('degree_level');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('university_programs')) {
            Schema::table('university_programs', function (Blueprint $table): void {
                if (Schema::hasColumn('university_programs', 'thesis_type')) {
                    $table->dropColumn('thesis_type');
                }
            });
        }

        if (Schema::hasTable('universities')) {
            try {
                Schema::table('universities', function (Blueprint $table): void {
                    $table->dropUnique('universities_tenant_name_country_city_uq');
                });
            } catch (\Throwable) {
            }
        }
    }

    private function mergeDuplicateUniversities(): void
    {
        $rows = DB::table('universities')
            ->selectRaw("tenant_id, LOWER(name) as lname, LOWER(country) as lcountry, LOWER(COALESCE(city,'')) as lcity, MIN(id) as keep_id, COUNT(*) as cnt")
            ->groupByRaw("tenant_id, LOWER(name), LOWER(country), LOWER(COALESCE(city,''))")
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($rows as $row) {
            $dupeIds = DB::table('universities')
                ->where('tenant_id', $row->tenant_id)
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
            }
            if (Schema::hasTable('applications')) {
                DB::table('applications')->whereIn('university_id', $dupeIds)->update(['university_id' => $row->keep_id]);
            }
            if (Schema::hasTable('scholarships')) {
                DB::table('scholarships')->whereIn('university_id', $dupeIds)->update(['university_id' => $row->keep_id]);
            }

            DB::table('universities')->whereIn('id', $dupeIds)->delete();
        }
    }
};

