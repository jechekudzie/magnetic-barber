<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('reference')->unique()->comment('Spoken code, e.g. MB-A7K2Q');

            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type')->default('scheduled')->comment('walkin|scheduled|house_call');
            $table->string('status')->default('pending')->comment('pending|confirmed|checked_in|in_progress|completed|cancelled|no_show');
            $table->string('source')->default('web')->comment('app|web|whatsapp|reception|qr|phone');

            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->unsignedSmallInteger('queue_position')->nullable();
            $table->unsignedSmallInteger('estimated_wait_minutes')->nullable();

            $table->foreignId('style_id')->nullable()->constrained('styles')->nullOnDelete();
            $table->text('client_note')->nullable();
            $table->text('staff_note')->nullable();

            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('travel_fee_cents')->default(0);
            $table->unsignedInteger('discount_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->unsignedSmallInteger('duration_minutes')->default(0);

            $table->string('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            /*
             * The database level guard against a double booking.
             *
             * It cannot be a plain unique on (staff_id, scheduled_start_at):
             * that would let a cancelled 10:00 hold the slot for ever, so
             * nobody could ever rebook it. Instead the model keeps this column
             * filled only while the appointment actually holds the slot, and
             * NULLs it the moment it is cancelled. NULLs do not collide in a
             * unique index, on MySQL, PostgreSQL and SQLite alike.
             */
            $table->string('slot_key')->nullable()->unique();

            $table->timestamps();

            // The reads the availability grid and the conflict check make.
            $table->index(['branch_id', 'scheduled_start_at']);
            $table->index(['staff_id', 'scheduled_start_at']);
            $table->index(['client_id', 'scheduled_start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
