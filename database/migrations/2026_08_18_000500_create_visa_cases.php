<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('visa_cases')) {
            Schema::create('visa_cases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('application_id')->unique();
                $table->unsignedBigInteger('student_id');
                $table->string('visa_type', 60)->nullable();
                $table->date('submission_date')->nullable();
                $table->date('embassy_appointment_date')->nullable();
                $table->date('interview_date')->nullable();
                $table->string('decision', 30)->default('pending');
                $table->date('decision_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'student_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_cases');
    }
};
