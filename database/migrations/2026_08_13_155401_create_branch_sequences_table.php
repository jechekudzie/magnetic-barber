<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per branch counters. Read these under a row lock so two receptionists
 * creating a client at the same moment never land on the same number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_account_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_sequences');
    }
};
