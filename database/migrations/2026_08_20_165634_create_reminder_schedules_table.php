<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is due back, and who has already been chased.
 *
 * A row per client per reason, so the shop can see the queue before anything
 * is sent, and so nobody is messaged twice for the same lapse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type')->comment('cut_due|winback|appointment_24h|appointment_2h|birthday');
            $table->timestamp('due_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_reason')->nullable();

            $table->unsignedSmallInteger('days_since_visit')->nullable();
            $table->timestamps();

            $table->index(['due_at', 'sent_at']);
            $table->index(['client_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_schedules');
    }
};
