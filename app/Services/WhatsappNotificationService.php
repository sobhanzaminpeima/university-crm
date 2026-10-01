<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappNotificationService
{
    public function notifyTenant(int $tenantId, string $eventKey, string $message): void
    {
        if (!$this->isEventEnabled($tenantId, $eventKey)) {
            return;
        }

        $settings = DB::table('tenant_notification_settings')->where('tenant_id', $tenantId)->first();
        if (!$settings || (int) ($settings->whatsapp_enabled ?? 0) !== 1) {
            return;
        }

        $apiUrl = trim((string) ($settings->api_url ?? ''));
        $apiToken = trim((string) ($settings->api_token ?? ''));
        if ($apiUrl === '' || $apiToken === '') {
            return;
        }

        $recipients = User::query()
            ->where('tenant_id', $tenantId)
            ->where('role_slug', '!=', 'student')
            ->where('is_active', 1)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->pluck('phone')
            ->unique()
            ->values()
            ->all();

        foreach ($recipients as $phone) {
            try {
                Http::timeout(12)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$apiToken,
                        'Accept' => 'application/json',
                    ])
                    ->post($apiUrl, [
                        'to' => $phone,
                        'message' => $message,
                        'channel' => 'whatsapp',
                    ]);
            } catch (\Throwable $e) {
                Log::warning('WhatsApp notification failed', [
                    'tenant_id' => $tenantId,
                    'phone' => $phone,
                    'event' => $eventKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function isEventEnabled(int $tenantId, string $eventKey): bool
    {
        if (!DB::getSchemaBuilder()->hasTable('tenant_notification_settings')) {
            return false;
        }

        $settings = DB::table('tenant_notification_settings')->where('tenant_id', $tenantId)->first();
        if (!$settings) {
            return false;
        }

        if (!$this->isFeatureEnabledForTenant($tenantId, 'whatsapp_notifications')) {
            return false;
        }

        return match ($eventKey) {
            'new_student' => (int) ($settings->notify_new_student ?? 0) === 1,
            'application_update' => (int) ($settings->notify_application_update ?? 0) === 1,
            'document_update' => (int) ($settings->notify_document_update ?? 0) === 1,
            default => false,
        };
    }

    private function isFeatureEnabledForTenant(int $tenantId, string $featureKey): bool
    {
        if (!DB::getSchemaBuilder()->hasTable('features') || !DB::getSchemaBuilder()->hasTable('tenant_features')) {
            return true;
        }

        $featureId = DB::table('features')->where('key', $featureKey)->value('id');
        if (!$featureId) {
            return true;
        }

        return (bool) DB::table('tenant_features')
            ->where('tenant_id', $tenantId)
            ->where('feature_id', $featureId)
            ->value('is_enabled');
    }
}

