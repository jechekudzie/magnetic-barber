<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('account_number')->unique()->comment('e.g. MB-0143');
            $table->foreignId('home_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('preferred_staff_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->text('notes')->nullable()->comment('Encrypted at rest');

            $table->string('source')->default('walkin')->comment('walkin|qr|app|web|whatsapp|referral');
            $table->foreignId('referred_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('referral_code')->unique();

            $table->boolean('whatsapp_opt_in')->default(true);
            $table->boolean('sms_opt_in')->default(true);
            $table->boolean('push_opt_in')->default(true);
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamp('marketing_opt_in_at')->nullable();

            $table->timestamp('first_visit_at')->nullable();
            $table->timestamp('last_visit_at')->nullable();
            $table->unsignedInteger('visit_count')->default(0);
            $table->unsignedBigInteger('lifetime_value_cents')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->unsignedSmallInteger('average_cycle_days')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_profiles');
    }
};
