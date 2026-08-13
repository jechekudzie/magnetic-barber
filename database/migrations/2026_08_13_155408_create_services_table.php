<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The catalog definition only. Price and duration are per branch and live on
 * branch_service, never here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('slug')->unique();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();

            $table->unsignedSmallInteger('default_duration_minutes')->default(30);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);

            $table->boolean('requires_patch_test')->default(false);
            $table->unsignedSmallInteger('patch_test_lead_hours')->nullable();
            $table->boolean('is_skin_service')->default(false);
            $table->boolean('is_house_call_eligible')->default(true);
            $table->boolean('requires_room')->default(false);
            $table->boolean('is_featured')->default(false);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
