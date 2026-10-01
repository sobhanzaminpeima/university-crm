<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')->updateOrInsert(
            ['key' => 'telegram.use'],
            [
                'name' => 'Use Telegram Bot',
                'group_key' => 'integrations',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (!Schema::hasTable('roles') || !Schema::hasTable('role_permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('key', 'telegram.use')->value('id');
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        if ($permissionId && $adminRoleId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $adminRoleId, 'permission_id' => $permissionId],
                ['created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('key', 'telegram.use')->value('id');
        if ($permissionId && Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        }
        if ($permissionId && Schema::hasTable('user_permissions')) {
            DB::table('user_permissions')->where('permission_key', 'telegram.use')->delete();
        }
        DB::table('permissions')->where('key', 'telegram.use')->delete();
    }
};
