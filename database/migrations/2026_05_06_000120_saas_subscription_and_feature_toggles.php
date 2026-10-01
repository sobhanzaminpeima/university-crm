<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table): void {
                if (!Schema::hasColumn('tenants', 'owner_name')) {
                    $table->string('owner_name', 190)->nullable()->after('name');
                }
                if (!Schema::hasColumn('tenants', 'email')) {
                    $table->string('email', 190)->nullable()->after('owner_name');
                }
                if (!Schema::hasColumn('tenants', 'phone')) {
                    $table->string('phone', 60)->nullable()->after('email');
                }
                if (!Schema::hasColumn('tenants', 'plan_type')) {
                    $table->string('plan_type', 60)->default('monthly')->after('currency');
                }
                if (!Schema::hasColumn('tenants', 'billing_cycle')) {
                    $table->string('billing_cycle', 60)->default('1_month')->after('plan_type');
                }
                if (!Schema::hasColumn('tenants', 'subscription_start_date')) {
                    $table->date('subscription_start_date')->nullable()->after('billing_cycle');
                }
                if (!Schema::hasColumn('tenants', 'subscription_end_date')) {
                    $table->date('subscription_end_date')->nullable()->after('subscription_start_date');
                }
                if (!Schema::hasColumn('tenants', 'subscription_status')) {
                    $table->string('subscription_status', 40)->default('active')->after('subscription_end_date');
                }
            });
        }

        if (!Schema::hasTable('features')) {
            Schema::create('features', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 120)->unique();
                $table->string('name', 190);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tenant_features')) {
            Schema::create('tenant_features', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('feature_id');
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
                $table->unique(['tenant_id', 'feature_id'], 'tenant_features_tenant_feature_uq');
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('feature_id')->references('id')->on('features')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('permissions')) {
            $scopePermissions = [
                ['key' => 'universities.view_all', 'name' => 'View All Universities', 'group_key' => 'universities'],
                ['key' => 'students.view_all', 'name' => 'View All Students', 'group_key' => 'students'],
                ['key' => 'applications.view_all', 'name' => 'View All Applications', 'group_key' => 'applications'],
                ['key' => 'tasks.view_all', 'name' => 'View All Tasks', 'group_key' => 'tasks'],
                ['key' => 'messages.view_all', 'name' => 'View All Messages', 'group_key' => 'messages'],
                ['key' => 'finance.view_all', 'name' => 'View All Finance', 'group_key' => 'finance'],
                ['key' => 'student_requests.view_all', 'name' => 'View All Student Requests', 'group_key' => 'student_requests'],
                ['key' => 'scholarships.view_all', 'name' => 'View All Scholarships', 'group_key' => 'scholarships'],
            ];
            foreach ($scopePermissions as $permission) {
                DB::table('permissions')->updateOrInsert(
                    ['key' => $permission['key']],
                    [
                        'name' => $permission['name'],
                        'group_key' => $permission['group_key'],
                        'created_at' => now(),
                    ]
                );
            }
        }

        if (Schema::hasTable('features')) {
            $features = [
                ['key' => 'applications', 'name' => 'Applications Module'],
                ['key' => 'messaging', 'name' => 'Messaging Module'],
                ['key' => 'reports_export', 'name' => 'Reports Export'],
                ['key' => 'task_management', 'name' => 'Task Management'],
                ['key' => 'multi_currency', 'name' => 'Multi Currency'],
                ['key' => 'file_upload', 'name' => 'File Upload'],
                ['key' => 'sub_agent_creation', 'name' => 'Sub-Agent Creation'],
            ];
            foreach ($features as $feature) {
                DB::table('features')->updateOrInsert(['key' => $feature['key']], [
                    'name' => $feature['name'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_features')) {
            Schema::dropIfExists('tenant_features');
        }
        if (Schema::hasTable('features')) {
            Schema::dropIfExists('features');
        }
    }
};

