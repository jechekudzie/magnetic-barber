<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When each barber is on the floor. A staff member with no rows here is
 * assumed to work the branch's own opening hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday')->comment('0 Sunday through 6 Saturday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->unique(['branch_id', 'user_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('working_hours');
    }
};
