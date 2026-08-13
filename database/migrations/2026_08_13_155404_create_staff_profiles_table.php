<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('display_name')->nullable();
            $table->string('title')->nullable()->comment('Shown on the site, e.g. Master Barber');
            $table->text('bio')->nullable();
            $table->json('specialities')->nullable();
            $table->string('instagram_handle')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('accepts_house_calls')->default(false);
            $table->boolean('is_bookable')->default(true);
            $table->boolean('show_on_site')->default(true);
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
