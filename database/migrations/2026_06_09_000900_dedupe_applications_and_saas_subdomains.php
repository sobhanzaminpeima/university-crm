<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('applications')) {
            $groups = DB::table('applications')
                ->selectRaw("tenant_id, student_id, university_id, LOWER(TRIM(program)) as normalized_program, LOWER(TRIM(intake)) as normalized_intake, MIN(id) as keep_id, COUNT(*) as total")
                ->groupByRaw("tenant_id, student_id, university_id, LOWER(TRIM(program)), LOWER(TRIM(intake))")
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($groups as $group) {
                $deleteIds = DB::table('applications')
                    ->where('tenant_id', $group->tenant_id)
                    ->where('student_id', $group->student_id)
                    ->where('university_id', $group->university_id)
                    ->whereRaw('LOWER(TRIM(program)) = ?', [$group->normalized_program])
                    ->whereRaw('LOWER(TRIM(intake)) = ?', [$group->normalized_intake])
                    ->where('id', '!=', $group->keep_id)
                    ->pluck('id')
                    ->all();

                if (!empty($deleteIds)) {
                    DB::table('applications')->whereIn('id', $deleteIds)->delete();
                }
            }
        }

        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table): void {
                if (!Schema::hasColumn('tenants', 'subdomain')) {
                    $table->string('subdomain', 80)->nullable()->unique()->after('slug');
                }
                if (!Schema::hasColumn('tenants', 'custom_domain')) {
                    $table->string('custom_domain', 190)->nullable()->after('subdomain');
                }
                if (!Schema::hasColumn('tenants', 'access_url')) {
                    $table->string('access_url', 255)->nullable()->after('custom_domain');
                }
            });

            $tenants = DB::table('tenants')->get(['id', 'name', 'slug']);
            foreach ($tenants as $tenant) {
                $base = Str::slug((string) ($tenant->slug ?: $tenant->name)) ?: 'tenant-'.$tenant->id;
                $subdomain = $base;
                $i = 2;
                while (DB::table('tenants')->where('subdomain', $subdomain)->where('id', '!=', $tenant->id)->exists()) {
                    $subdomain = $base.'-'.$i++;
                }
                DB::table('tenants')->where('id', $tenant->id)->whereNull('subdomain')->update([
                    'subdomain' => $subdomain,
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('features')) {
            $features = [
                ['key' => 'advanced_search', 'name' => 'Advanced Search'],
                ['key' => 'mobile_bottom_nav', 'name' => 'Mobile Bottom Navigation'],
                ['key' => 'tenant_backup', 'name' => 'Tenant Backup'],
                ['key' => 'university_program_dedupe', 'name' => 'University Program Deduplication'],
                ['key' => 'application_dedupe', 'name' => 'Application Deduplication'],
                ['key' => 'study_catalog_cleanup', 'name' => 'Study Catalog Cleanup'],
                ['key' => 'whatsapp_notifications', 'name' => 'WhatsApp Notifications'],
                ['key' => 'api_tokens', 'name' => 'API Tokens'],
                ['key' => 'automation_rules', 'name' => 'Automation Rules'],
            ];
            foreach ($features as $feature) {
                DB::table('features')->updateOrInsert(['key' => $feature['key']], [
                    'name' => $feature['name'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }

            if (Schema::hasTable('tenant_features') && Schema::hasTable('tenants')) {
                $featureIds = DB::table('features')
                    ->whereIn('key', array_column($features, 'key'))
                    ->pluck('id');
                $tenantIds = DB::table('tenants')->pluck('id');
                foreach ($tenantIds as $tenantId) {
                    foreach ($featureIds as $featureId) {
                        DB::table('tenant_features')->updateOrInsert(
                            ['tenant_id' => $tenantId, 'feature_id' => $featureId],
                            ['is_enabled' => 1, 'updated_at' => now(), 'created_at' => now()]
                        );
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table): void {
                if (Schema::hasColumn('tenants', 'access_url')) {
                    $table->dropColumn('access_url');
                }
                if (Schema::hasColumn('tenants', 'custom_domain')) {
                    $table->dropColumn('custom_domain');
                }
                if (Schema::hasColumn('tenants', 'subdomain')) {
                    $table->dropUnique(['subdomain']);
                    $table->dropColumn('subdomain');
                }
            });
        }
    }
};
