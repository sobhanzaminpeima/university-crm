<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('universities')) {
            Schema::table('universities', function (Blueprint $table): void {
                if (!Schema::hasColumn('universities', 'logo_url')) {
                    $table->string('logo_url', 255)->nullable()->after('website');
                }
                if (!Schema::hasColumn('universities', 'image_url')) {
                    $table->string('image_url', 255)->nullable()->after('logo_url');
                }
                if (!Schema::hasColumn('universities', 'created_by_user_id')) {
                    $table->unsignedBigInteger('created_by_user_id')->nullable()->after('tenant_id');
                    $table->index('created_by_user_id', 'universities_created_by_idx');
                }
            });
        }

        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table): void {
                if (!Schema::hasColumn('students', 'preferred_university_language')) {
                    $table->string('preferred_university_language', 12)->nullable()->after('field_of_study');
                }
            });
        }

        if (Schema::hasTable('student_messages')) {
            Schema::table('student_messages', function (Blueprint $table): void {
                if (!Schema::hasColumn('student_messages', 'attachment_url')) {
                    $table->string('attachment_url', 255)->nullable()->after('body');
                }
                if (!Schema::hasColumn('student_messages', 'attachment_name')) {
                    $table->string('attachment_name', 255)->nullable()->after('attachment_url');
                }
            });
        }

        if (!Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('code', 10);
                $table->string('name', 80);
                $table->string('symbol', 12)->nullable();
                $table->tinyInteger('is_default')->default(0);
                $table->tinyInteger('is_active')->default(1);
                $table->timestamps();
                $table->unique(['tenant_id', 'code'], 'currencies_tenant_code_uq');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('currencies')) {
            Schema::dropIfExists('currencies');
        }
        if (Schema::hasTable('student_messages')) {
            Schema::table('student_messages', function (Blueprint $table): void {
                if (Schema::hasColumn('student_messages', 'attachment_name')) {
                    $table->dropColumn('attachment_name');
                }
                if (Schema::hasColumn('student_messages', 'attachment_url')) {
                    $table->dropColumn('attachment_url');
                }
            });
        }
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table): void {
                if (Schema::hasColumn('students', 'preferred_university_language')) {
                    $table->dropColumn('preferred_university_language');
                }
            });
        }
        if (Schema::hasTable('universities')) {
            Schema::table('universities', function (Blueprint $table): void {
                if (Schema::hasColumn('universities', 'created_by_user_id')) {
                    $table->dropIndex('universities_created_by_idx');
                    $table->dropColumn('created_by_user_id');
                }
                if (Schema::hasColumn('universities', 'image_url')) {
                    $table->dropColumn('image_url');
                }
                if (Schema::hasColumn('universities', 'logo_url')) {
                    $table->dropColumn('logo_url');
                }
            });
        }
    }
};

