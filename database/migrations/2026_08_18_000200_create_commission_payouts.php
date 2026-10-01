<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('commission_payouts')) {
            Schema::create('commission_payouts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('agent_id');
                $table->string('currency', 8);
                $table->decimal('amount', 12, 2);
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'agent_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payouts');
    }
};
