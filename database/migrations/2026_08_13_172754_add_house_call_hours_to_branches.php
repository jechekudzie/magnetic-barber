<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * House calls usually run a narrower window than the shop floor: a barber has
 * to travel there and back, so the last job cannot start at closing time.
 * Null means "same as the shop".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->time('house_call_opens_at')->nullable()->after('house_call_fee_cents');
            $table->time('house_call_closes_at')->nullable()->after('house_call_opens_at');
            $table->json('house_call_days_open')->nullable()->after('house_call_closes_at');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'house_call_opens_at',
                'house_call_closes_at',
                'house_call_days_open',
            ]);
        });
    }
};
