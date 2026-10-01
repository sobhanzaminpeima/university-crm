<?php

use App\Support\SecretValue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->expandColumn('tenant_notification_settings', 'api_token');
        $this->expandColumn('tenant_integration_settings', 'sms_api_token');
        $this->expandColumn('tenant_integration_settings', 'ai_api_key');
        $this->encryptColumn('tenant_notification_settings', 'api_token');
        $this->encryptColumn('tenant_integration_settings', 'sms_api_token');
        $this->encryptColumn('tenant_integration_settings', 'ai_api_key');
    }

    private function expandColumn(string $table, string $column): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TEXT NULL");
        }
    }

    public function down(): void
    {
        // Secrets intentionally remain encrypted to avoid accidental plaintext exposure.
    }

    private function encryptColumn(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }
        DB::table($table)->whereNotNull($column)->orderBy('id')->chunkById(100, function ($rows) use ($table, $column): void {
            foreach ($rows as $row) {
                $value = (string) $row->{$column};
                if ($value !== '' && !str_starts_with($value, 'enc:')) {
                    DB::table($table)->where('id', $row->id)->update([$column => SecretValue::encrypt($value)]);
                }
            }
        });
    }
};
