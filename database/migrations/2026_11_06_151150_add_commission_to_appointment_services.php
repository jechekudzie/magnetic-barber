<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the barber earns on each line they rendered.
 *
 * Both columns are snapshots, for the same reason price_cents and
 * name_snapshot beside them are: a barber's rate changing next month must not
 * rewrite what they already earned. Earnings are read from these columns, not
 * recomputed from the live rate on branch_user.
 *
 * The rate is stored alongside the money so a payslip can show its own
 * arithmetic without anyone having to guess which rate was in force.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_services', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)
                ->nullable()
                ->after('staff_id')
                ->comment('Percent, as it stood when this line was attributed');

            $table->unsignedInteger('commission_cents')
                ->default(0)
                ->after('commission_rate');

            // The earnings read: one barber, one day.
            $table->index(['staff_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('appointment_services', function (Blueprint $table) {
            $table->dropIndex(['staff_id', 'created_at']);
            $table->dropColumn(['commission_rate', 'commission_cents']);
        });
    }
};
