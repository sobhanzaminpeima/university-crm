<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('telegram_states')) {
            Schema::create('telegram_states', function (Blueprint $table) {
                $table->id();
                $table->string('chat_id', 40)->unique();
                $table->string('flow', 60);
                $table->string('step', 60);
                $table->text('data_json')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_states');
    }
};
