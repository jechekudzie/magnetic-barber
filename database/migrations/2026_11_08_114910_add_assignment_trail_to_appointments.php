<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who put this client in whose chair, and who started the clock.
 *
 * A booking is already stamped with the barber who will do it, but not with
 * how they got it. That matters the moment the desk moves a walk-in to a
 * different chair, and it matters more when the receptionist is out and a
 * barber takes the booking himself: the shop needs to be able to see that it
 * was the barber who did it, not the desk.
 *
 * started_at already says when. These say who, which is the question the
 * owner actually asks when two barbers disagree about whose cut it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreignId('assigned_by')
                ->nullable()
                ->after('staff_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at')->nullable()->after('assigned_by');

            $table->foreignId('started_by')
                ->nullable()
                ->after('started_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropConstrainedForeignId('started_by');
            $table->dropColumn('assigned_at');
        });
    }
};
