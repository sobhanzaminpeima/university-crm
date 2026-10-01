<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('study_fields')) {
            Schema::create('study_fields', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name', 150);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['tenant_id', 'name'], 'study_fields_tenant_name_uq');
            });
        }

        if (!Schema::hasTable('intake_terms')) {
            Schema::create('intake_terms', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name', 120);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['tenant_id', 'name'], 'intake_terms_tenant_name_uq');
            });
        }

        if (Schema::hasTable('permissions')) {
            $rows = [
                ['key' => 'reports.view', 'name' => 'View Reports', 'group_key' => 'reports'],
                ['key' => 'audit.view', 'name' => 'View Audit Logs', 'group_key' => 'users'],
                ['key' => 'agent_performance.view', 'name' => 'View Agent Performance', 'group_key' => 'users'],
                ['key' => 'study_fields.view', 'name' => 'View Study Fields', 'group_key' => 'settings'],
                ['key' => 'study_fields.update', 'name' => 'Manage Study Fields', 'group_key' => 'settings'],
            ];
            foreach ($rows as $row) {
                DB::table('permissions')->updateOrInsert(['key' => $row['key']], [
                    'name' => $row['name'],
                    'group_key' => $row['group_key'],
                    'created_at' => now(),
                ]);
            }

            if (Schema::hasTable('roles') && Schema::hasTable('role_permissions')) {
                $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
                if ($adminRoleId) {
                    $ids = DB::table('permissions')->whereIn('key', ['reports.view', 'audit.view', 'agent_performance.view', 'study_fields.view', 'study_fields.update'])->pluck('id');
                    foreach ($ids as $pid) {
                        DB::table('role_permissions')->updateOrInsert(
                            ['role_id' => $adminRoleId, 'permission_id' => $pid],
                            ['created_at' => now()]
                        );
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('study_fields');
        Schema::dropIfExists('intake_terms');
    }
};
