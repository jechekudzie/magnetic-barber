<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Append only. A balance is always SUM(points), never a column that is
         * updated in place: a mutable balance drifts the first time two writes
         * race, and then nobody can prove what it should have been.
         */
        Schema::create('loyalty_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type')->comment('earn|redeem|adjust|expire|referral_bonus|signup_bonus');
            $table->integer('points')->comment('Signed: earns are positive, redemptions negative');
            $table->integer('balance_after')->comment('Denormalised so a statement reads without recomputing');
            $table->string('description')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            // One earn per appointment, so completing a visit twice cannot
            // hand out the points twice.
            $table->unique(['appointment_id', 'type']);
        });

        Schema::create('loyalty_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('points_per_visit')->default(5);
            $table->decimal('points_per_currency_unit', 8, 2)->default(0);
            $table->string('applies_to')->default('all')->comment('all|services|products|skin');
            $table->unsignedInteger('min_spend_cents')->default(0);

            $table->unsignedSmallInteger('redemption_threshold')->default(50)
                ->comment('Points needed before anything can be redeemed');
            $table->unsignedInteger('redemption_value_cents')->default(500)
                ->comment('What the threshold is worth when redeemed');
            $table->unsignedSmallInteger('points_expiry_months')->nullable();

            $table->char('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_ledger');
        Schema::dropIfExists('loyalty_rules');
    }
};
