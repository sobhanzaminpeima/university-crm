<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('saas_packages')) {
            Schema::create('saas_packages', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->decimal('price', 10, 2)->default(0);
                $table->string('currency', 10)->default('USD');
                $table->unsignedSmallInteger('duration_months')->default(1);
                $table->json('features_json')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tenant_subscriptions')) {
            Schema::create('tenant_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('saas_package_id')->nullable();
                $table->string('plan_type', 60)->default('monthly');
                $table->string('status', 40)->default('active');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('currency', 10)->default('USD');
                $table->timestamps();

                $table->index(['tenant_id', 'status'], 'tenant_subscriptions_tenant_status_idx');
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('saas_package_id')->references('id')->on('saas_packages')->nullOnDelete();
            });
        }

        $defaults = [
            [
                'name' => 'Starter Monthly',
                'slug' => 'starter-monthly',
                'price' => 59,
                'currency' => 'USD',
                'duration_months' => 1,
                'features_json' => json_encode(['Students', 'Applications', 'Universities', 'Documents', 'Portal']),
                'is_active' => 1,
                'sort_order' => 1,
            ],
            [
                'name' => 'Growth Quarterly',
                'slug' => 'growth-3-months',
                'price' => 159,
                'currency' => 'USD',
                'duration_months' => 3,
                'features_json' => json_encode(['Starter', 'Messaging', 'Reports PDF/Excel', 'Tasks', 'Sub-Agents']),
                'is_active' => 1,
                'sort_order' => 2,
            ],
            [
                'name' => 'Scale Semiannual',
                'slug' => 'scale-6-months',
                'price' => 299,
                'currency' => 'USD',
                'duration_months' => 6,
                'features_json' => json_encode(['Growth', 'Advanced Search', 'API Tokens', 'Automation Rules']),
                'is_active' => 1,
                'sort_order' => 3,
            ],
            [
                'name' => 'Enterprise Annual',
                'slug' => 'enterprise-1-year',
                'price' => 549,
                'currency' => 'USD',
                'duration_months' => 12,
                'features_json' => json_encode(['Scale', 'Priority Support', 'White Label Branding']),
                'is_active' => 1,
                'sort_order' => 4,
            ],
        ];
        foreach ($defaults as $package) {
            DB::table('saas_packages')->updateOrInsert(
                ['slug' => $package['slug']],
                array_merge($package, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_subscriptions')) {
            Schema::dropIfExists('tenant_subscriptions');
        }
        if (Schema::hasTable('saas_packages')) {
            Schema::dropIfExists('saas_packages');
        }
    }
};

