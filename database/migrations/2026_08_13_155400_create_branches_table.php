<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('slug')->unique();
            $table->string('code', 4)->unique()->comment('Account number prefix, e.g. MB');
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();

            $table->string('address_line')->nullable();
            $table->string('area')->nullable();
            $table->string('city')->default('Harare');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('directions_note')->nullable();

            $table->string('timezone')->default('Africa/Harare');
            $table->time('opens_at')->default('08:00');
            $table->time('closes_at')->default('19:00');
            $table->json('days_open')->nullable()->comment('Weekday numbers 0-6 the branch trades');

            $table->unsignedSmallInteger('chair_count')->default(0);
            $table->boolean('house_call_enabled')->default(false);
            $table->unsignedSmallInteger('house_call_radius_km')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
