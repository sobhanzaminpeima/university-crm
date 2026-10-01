<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tenant_notification_settings')) {
            Schema::table('tenant_notification_settings', function (Blueprint $table): void {
                if (!Schema::hasColumn('tenant_notification_settings', 'docs_pending_days')) {
                    $table->unsignedSmallInteger('docs_pending_days')->default(3)->after('notify_document_update');
                }
                if (!Schema::hasColumn('tenant_notification_settings', 'docs_pending_task_enabled')) {
                    $table->boolean('docs_pending_task_enabled')->default(true)->after('docs_pending_days');
                }
                if (!Schema::hasColumn('tenant_notification_settings', 'docs_pending_email_enabled')) {
                    $table->boolean('docs_pending_email_enabled')->default(false)->after('docs_pending_task_enabled');
                }
                if (!Schema::hasColumn('tenant_notification_settings', 'docs_pending_whatsapp_enabled')) {
                    $table->boolean('docs_pending_whatsapp_enabled')->default(true)->after('docs_pending_email_enabled');
                }
                if (!Schema::hasColumn('tenant_notification_settings', 'docs_pending_sms_enabled')) {
                    $table->boolean('docs_pending_sms_enabled')->default(false)->after('docs_pending_whatsapp_enabled');
                }
            });
        }

        if (!Schema::hasTable('tenant_integration_settings')) {
            Schema::create('tenant_integration_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->unique();
                $table->boolean('email_enabled')->default(false);
                $table->string('email_from_address', 190)->nullable();
                $table->string('email_from_name', 120)->nullable();
                $table->boolean('sms_enabled')->default(false);
                $table->string('sms_api_url', 255)->nullable();
                $table->string('sms_api_token', 255)->nullable();
                $table->boolean('ai_enabled')->default(false);
                $table->string('ai_provider', 40)->default('openai');
                $table->string('ai_model', 80)->nullable();
                $table->string('ai_api_key', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('lead_source_costs')) {
            Schema::create('lead_source_costs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('source_key', 60);
                $table->decimal('monthly_cost', 12, 2)->default(0);
                $table->timestamps();
                $table->unique(['tenant_id', 'source_key'], 'lead_source_costs_tenant_source_uq');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lead_source_costs')) {
            Schema::dropIfExists('lead_source_costs');
        }
        if (Schema::hasTable('tenant_integration_settings')) {
            Schema::dropIfExists('tenant_integration_settings');
        }
        if (Schema::hasTable('tenant_notification_settings')) {
            Schema::table('tenant_notification_settings', function (Blueprint $table): void {
                foreach ([
                    'docs_pending_sms_enabled',
                    'docs_pending_whatsapp_enabled',
                    'docs_pending_email_enabled',
                    'docs_pending_task_enabled',
                    'docs_pending_days',
                ] as $col) {
                    if (Schema::hasColumn('tenant_notification_settings', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

