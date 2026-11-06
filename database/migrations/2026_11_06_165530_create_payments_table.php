<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money against a booking.
 *
 * EcoCash and InnBucks both settle somewhere else entirely, so there is no
 * gateway callback to believe. What the shop gets instead is a transaction
 * reference and a screenshot, which is why proof_path and reference sit
 * beside the amount rather than in some later reconciliation table.
 *
 * A booking is secured the moment proof is uploaded, and checked by the desk
 * afterwards. That is a deliberate trade: a client who pays at nine at night
 * has their chair immediately, and the shop carries the small risk of a bad
 * screenshot holding a slot until somebody looks at it.
 *
 * Rows are never edited into a different shape. A payment that turns out to
 * be wrong is rejected and a new one recorded, so the till has a history
 * rather than a current opinion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();

            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            $table->string('method')->comment('ecocash|innbucks|cash|card|bank_transfer|other');
            $table->string('status')->default('pending')->comment('pending|confirmed|rejected|refunded');

            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('USD');

            // The code the client reads off their phone after paying.
            $table->string('reference')->nullable()->comment('Provider transaction reference');
            $table->string('proof_path')->nullable()->comment('Screenshot of the transfer');

            $table->timestamp('paid_at')->nullable()->comment('When the client says they paid');

            /*
             * Who put this row here. Null when a client recorded it from the
             * app, which is exactly the case that needs checking.
             */
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->text('note')->nullable();
            $table->timestamps();

            // The till reads: one branch, one day. And one booking's history.
            $table->index(['branch_id', 'created_at']);
            $table->index(['appointment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
