<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which printed price card a service appears on.
 *
 * The shop hands out two cards, men's and ladies', and they charge
 * differently for the same job: Haircut + Dye is $10 on one and $15 on the
 * other. The catalog already carries those as separate services, but nothing
 * recorded which card each belonged to, so anything wanting to show the cards
 * had to read it out of the name. "Ladies Haircut and Dye" happens to start
 * with the word, and the moment somebody renames a service that breaks
 * silently.
 *
 * Values are the existing GenderTag enum, already used by styles. Unisex
 * means it is on both cards at the same price, which today is the Deep Clean
 * and LED Therapy Facial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('audience')
                ->default('unisex')
                ->after('description')
                ->comment('men|women|unisex|kids — which price card this is on');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
