<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('document_requirements')) {
            Schema::create('document_requirements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('university_id')->nullable();
                $table->string('doc_type', 60);
                $table->string('label', 190);
                $table->boolean('is_mandatory')->default(1);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'university_id']);
            });
        }

        if (!Schema::hasColumn('documents', 'review_note')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->text('review_note')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requirements');
        if (Schema::hasColumn('documents', 'review_note')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('review_note');
            });
        }
    }
};
