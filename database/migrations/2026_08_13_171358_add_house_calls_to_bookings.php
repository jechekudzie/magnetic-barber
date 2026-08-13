<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * House call bookings. The address is snapshotted onto the appointment rather
 * than only referenced, so a client editing their saved address later cannot
 * change where a barber was already sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedInteger('house_call_fee_cents')->default(0)->after('house_call_radius_km');
        });

        Schema::create('house_call_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('address_line');
            $table->string('area')->nullable();
            $table->string('city')->nullable();
            $table->text('directions_note')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->unsignedInteger('travel_fee_cents')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->decimal('distance_km', 6, 2)->nullable();

            $table->timestamp('departed_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('house_call_details');

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('house_call_fee_cents');
        });
    }
};
