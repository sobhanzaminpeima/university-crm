<?php

namespace App\Services;

use App\Support\SecretValue;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FollowupCommunicationService
{
    public function sendEmailToTenantStaff(int $tenantId, string $subject, string $message): void
    {
        $settings = DB::table('tenant_integration_settings')->where('tenant_id', $tenantId)->first();
        if (!$settings || (int) ($settings->email_enabled ?? 0) !== 1) {
            return;
        }

        $fromAddress = (string) ($settings->email_from_address ?? '');
        $fromName = (string) ($settings->email_from_name ?? 'CRM Automation');
        if ($fromAddress === '') {
            return;
        }

        $emails = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('role_slug', '!=', 'student')
            ->where('is_active', 1)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        foreach ($emails as $email) {
            try {
                Mail::raw($message, function ($mail) use ($email, $subject, $fromAddress, $fromName): void {
                    $mail->to($email)->subject($subject)->from($fromAddress, $fromName);
                });
            } catch (\Throwable $e) {
                Log::warning('Automation email send failed', ['tenant_id' => $tenantId, 'to' => $email, 'error' => $e->getMessage()]);
            }
        }
    }

    public function sendSmsToTenantStaff(int $tenantId, string $message): void
    {
        $settings = DB::table('tenant_integration_settings')->where('tenant_id', $tenantId)->first();
        if (!$settings || (int) ($settings->sms_enabled ?? 0) !== 1) {
            return;
        }
        $apiUrl = trim((string) ($settings->sms_api_url ?? ''));
        $token = SecretValue::decrypt($settings->sms_api_token ?? null);
        if ($apiUrl === '' || $token === '') {
            return;
        }

        $phones = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('role_slug', '!=', 'student')
            ->where('is_active', 1)
            ->whereNotNull('phone')
            ->pluck('phone')
            ->filter()
            ->unique()
            ->values()
            ->all();

        foreach ($phones as $phone) {
            try {
                Http::timeout(12)->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/json',
                ])->post($apiUrl, [
                    'to' => $phone,
                    'message' => $message,
                    'channel' => 'sms',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Automation SMS send failed', ['tenant_id' => $tenantId, 'to' => $phone, 'error' => $e->getMessage()]);
            }
        }
    }
}
