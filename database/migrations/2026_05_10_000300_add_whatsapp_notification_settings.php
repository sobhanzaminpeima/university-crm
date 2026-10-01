<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tenant_notification_settings')) {
            Schema::create('tenant_notification_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->boolean('whatsapp_enabled')->default(false);
                $table->string('whatsapp_number', 30)->nullable();
                $table->string('provider', 30)->default('custom');
                $table->string('api_url')->nullable();
                $table->string('api_token')->nullable();
                $table->boolean('notify_new_student')->default(false);
                $table->boolean('notify_application_update')->default(false);
                $table->boolean('notify_document_update')->default(false);
                $table->timestamps();
                $table->unique('tenant_id', 'tenant_notification_settings_tenant_unique');
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('features')) {
            DB::table('features')->updateOrInsert(
                ['key' => 'whatsapp_notifications'],
                ['name' => 'WhatsApp Notifications', 'updated_at' => now(), 'created_at' => now()]
            );
        }

        if (Schema::hasTable('features') && Schema::hasTable('tenant_features') && Schema::hasTable('tenants')) {
            $featureId = DB::table('features')->where('key', 'whatsapp_notifications')->value('id');
            if ($featureId) {
                $tenantIds = DB::table('tenants')->pluck('id');
                foreach ($tenantIds as $tenantId) {
                    DB::table('tenant_features')->updateOrInsert(
                        ['tenant_id' => $tenantId, 'feature_id' => $featureId],
                        ['is_enabled' => 0, 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_features') && Schema::hasTable('features')) {
            $featureId = DB::table('features')->where('key', 'whatsapp_notifications')->value('id');
            if ($featureId) {
                DB::table('tenant_features')->where('feature_id', $featureId)->delete();
            }
        }
        if (Schema::hasTable('features')) {
            DB::table('features')->where('key', 'whatsapp_notifications')->delete();
        }
        if (Schema::hasTable('tenant_notification_settings')) {
            Schema::dropIfExists('tenant_notification_settings');
        }
    }
};

