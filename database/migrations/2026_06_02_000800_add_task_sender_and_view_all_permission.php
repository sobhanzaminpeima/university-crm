<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tasks') && !Schema::hasColumn('tasks', 'created_by_user_id')) {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('assigned_to');
                $table->index(['tenant_id', 'created_by_user_id'], 'tasks_tenant_created_by_idx');
            });
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(
                ['key' => 'tasks.view_all'],
                [
                    'name' => 'View All Tasks',
                    'group_key' => 'tasks',
                    'created_at' => now(),
                ]
            );

            if (Schema::hasTable('roles') && Schema::hasTable('role_permissions')) {
                $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
                $permissionId = DB::table('permissions')->where('key', 'tasks.view_all')->value('id');
                if ($adminRoleId && $permissionId) {
                    DB::table('role_permissions')->updateOrInsert(
                        ['role_id' => $adminRoleId, 'permission_id' => $permissionId],
                        ['created_at' => now()]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_permissions') && Schema::hasTable('permissions')) {
            $permissionId = DB::table('permissions')->where('key', 'tasks.view_all')->value('id');
            if ($permissionId) {
                DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            }
        }
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('key', 'tasks.view_all')->delete();
        }
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'created_by_user_id')) {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->dropIndex('tasks_tenant_created_by_idx');
                $table->dropColumn('created_by_user_id');
            });
        }
    }
};
