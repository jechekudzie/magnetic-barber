<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Price lives here, never on services. A fade in Avenues and a fade in
 * Borrowdale are the same service at two different prices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('price_cents');
            $table->char('currency', 3)->default('USD');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->comment('Overrides the service default');
            $table->unsignedInteger('house_call_surcharge_cents')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_service');
    }
};
