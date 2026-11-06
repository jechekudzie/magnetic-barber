<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a booking has been paid, and how long it actually took.
 *
 * amount_paid_cents is a running total maintained by PaymentService rather
 * than a sum taken on every read: the diary shows payment state on every row,
 * and a subquery per row is the kind of thing that only hurts once the shop
 * is busy.
 *
 * secured_at is set when money is first recorded against the booking, before
 * anyone has checked it. It answers "is this chair spoken for", which is a
 * different question from "has the shop been paid".
 *
 * actual_minutes is what the barber's own clock said, as opposed to what the
 * catalog guessed. Stored rather than derived from started_at and completed_at
 * so that correcting a forgotten tap later cannot quietly rewrite history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedInteger('amount_paid_cents')->default(0)->after('total_cents');
            $table->timestamp('secured_at')->nullable()->after('amount_paid_cents');
            $table->unsignedSmallInteger('actual_minutes')->nullable()->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['amount_paid_cents', 'secured_at', 'actual_minutes']);
        });
    }
};
