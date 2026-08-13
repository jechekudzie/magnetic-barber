<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The gallery. Numbered so a client can ask for "number 03" over WhatsApp and
 * every barber cuts the same thing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('styles', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('slug')->unique();
            $table->string('code', 4)->unique()->comment('Spoken reference, e.g. 01');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();

            $table->string('gender_tag')->nullable()->comment('men|women|unisex|kids');
            $table->json('hair_type_tag')->nullable();
            $table->unsignedSmallInteger('typical_duration_minutes')->nullable();

            $table->string('image_path')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('styles');
    }
};
