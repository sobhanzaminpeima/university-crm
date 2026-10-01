<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table): void {
                if (!Schema::hasColumn('students', 'lead_source')) {
                    $table->string('lead_source', 60)->nullable()->after('target_country');
                    $table->index('lead_source', 'students_lead_source_idx');
                }
                if (!Schema::hasColumn('students', 'lifecycle_stage')) {
                    $table->string('lifecycle_stage', 40)->nullable()->after('stage');
                    $table->index('lifecycle_stage', 'students_lifecycle_stage_idx');
                }
            });
        }

        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table): void {
                if (!Schema::hasColumn('applications', 'next_followup_at')) {
                    $table->dateTime('next_followup_at')->nullable()->after('deadline');
                    $table->index('next_followup_at', 'applications_next_followup_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table): void {
                if (Schema::hasColumn('applications', 'next_followup_at')) {
                    $table->dropIndex('applications_next_followup_idx');
                    $table->dropColumn('next_followup_at');
                }
            });
        }
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table): void {
                if (Schema::hasColumn('students', 'lifecycle_stage')) {
                    $table->dropIndex('students_lifecycle_stage_idx');
                    $table->dropColumn('lifecycle_stage');
                }
                if (Schema::hasColumn('students', 'lead_source')) {
                    $table->dropIndex('students_lead_source_idx');
                    $table->dropColumn('lead_source');
                }
            });
        }
    }
};

