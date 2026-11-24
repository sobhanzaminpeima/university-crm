<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fee type columns were only ever added by docs/sql/2026_05_17_university_fee_type_and_cleanup_patch.sql,
 * so fresh installs crashed on the Universities page ("Unknown column 'fee_type'").
 * Idempotent: does nothing where the SQL patch was already applied.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('universities') && ! Schema::hasColumn('universities', 'tuition_fee_type')) {
            Schema::table('universities', function (Blueprint $table): void {
                $table->string('tuition_fee_type', 30)->nullable()->after('tuition_range');
            });
        }
        if (Schema::hasTable('university_programs') && ! Schema::hasColumn('university_programs', 'fee_type')) {
            Schema::table('university_programs', function (Blueprint $table): void {
                $table->string('fee_type', 30)->nullable()->after('fee');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: these columns may pre-date this migration (SQL patch) and hold data.
    }
};
