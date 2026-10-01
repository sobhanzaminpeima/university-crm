<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('telegram_links')) {
            Schema::create('telegram_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->string('chat_id', 40)->unique();
                $table->string('telegram_username', 120)->nullable();
                $table->timestamp('linked_at')->nullable();
                $table->timestamps();

                $table->unique('user_id');
                $table->index(['tenant_id']);
            });
        }

        if (!Schema::hasTable('telegram_link_codes')) {
            Schema::create('telegram_link_codes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('code', 10);
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();

                $table->index(['code']);
                $table->index(['user_id']);
            });
        }

        if (!Schema::hasTable('telegram_updates_offset')) {
            Schema::create('telegram_updates_offset', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('last_update_id')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_updates_offset');
        Schema::dropIfExists('telegram_link_codes');
        Schema::dropIfExists('telegram_links');
    }
};
