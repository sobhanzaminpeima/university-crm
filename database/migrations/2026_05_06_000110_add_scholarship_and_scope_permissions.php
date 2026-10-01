<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('role_permissions')) {
            return;
        }

        $permissions = [
            ['key' => 'scholarships.view', 'name' => 'View scholarships', 'group_key' => 'scholarships'],
            ['key' => 'scholarships.update', 'name' => 'Manage scholarships', 'group_key' => 'scholarships'],
            ['key' => 'students.view_all', 'name' => 'View all students', 'group_key' => 'scope'],
            ['key' => 'universities.view_all', 'name' => 'View all universities', 'group_key' => 'scope'],
            ['key' => 'applications.view_all', 'name' => 'View all applications', 'group_key' => 'scope'],
            ['key' => 'tasks.view_all', 'name' => 'View all tasks', 'group_key' => 'scope'],
        ];

        foreach ($permissions as $permission) {
            $exists = DB::table('permissions')->where('key', $permission['key'])->exists();
            if (!$exists) {
                DB::table('permissions')->insert([
                    'key' => $permission['key'],
                    'name' => $permission['name'],
                    'group_key' => $permission['group_key'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        if ($adminRoleId) {
            $permissionIds = DB::table('permissions')
                ->whereIn('key', ['scholarships.view', 'scholarships.update', 'students.view_all', 'universities.view_all', 'applications.view_all', 'tasks.view_all'])
                ->pluck('id')
                ->all();
            foreach ($permissionIds as $permissionId) {
                $rowExists = DB::table('role_permissions')->where('role_id', $adminRoleId)->where('permission_id', $permissionId)->exists();
                if (!$rowExists) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $adminRoleId,
                        'permission_id' => $permissionId,
                        'created_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
    }
};

