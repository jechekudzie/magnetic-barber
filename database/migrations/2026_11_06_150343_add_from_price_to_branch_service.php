<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some services are quoted from a starting figure rather than at a fixed one.
 * Haircut + Tint is the live example: the price list prints "from $20,
 * depending with colour", and the final figure is settled in the chair once
 * the colour is chosen.
 *
 * The flag sits on branch_service rather than services because, like the price
 * it qualifies, whether a service is open ended is a property of the branch
 * and the service together. One branch can sell a fixed price tint while
 * another quotes from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_service', function (Blueprint $table) {
            $table->boolean('is_from_price')
                ->default(false)
                ->after('currency')
                ->comment('price_cents is a starting figure, not the final price');
        });
    }

    public function down(): void
    {
        Schema::table('branch_service', function (Blueprint $table) {
            $table->dropColumn('is_from_price');
        });
    }
};
