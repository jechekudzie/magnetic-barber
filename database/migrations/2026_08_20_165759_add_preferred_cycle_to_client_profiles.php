<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What this client asked for.
 *
 * A regular who says "every week" should be chased on their word, not on the
 * shop's default or on what their last few visits happened to look like.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->unsignedSmallInteger('preferred_cycle_days')
                ->nullable()
                ->after('average_cycle_days')
                ->comment('Set when the client tells us how often they want a cut');

            $table->boolean('reminders_enabled')->default(true)->after('preferred_cycle_days');
        });
    }

    public function down(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->dropColumn(['preferred_cycle_days', 'reminders_enabled']);
        });
    }
};
