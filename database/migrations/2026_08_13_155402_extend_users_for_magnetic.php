<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff and clients share the users table, separated by role. Clients never set
 * a password: the phone number is the identity key and OTP is the credential.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid()->nullable()->unique()->after('id');
            $table->string('phone')->nullable()->unique()->after('email')->comment('E.164 only');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('avatar_path')->nullable()->after('phone_verified_at');
            $table->string('locale', 5)->default('en')->after('avatar_path');
            $table->boolean('is_active')->default(true)->after('locale');
            $table->timestamp('last_seen_at')->nullable()->after('is_active');
            $table->softDeletes();
        });

        // Clients arrive with a phone and no email, and never set a password.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'ulid', 'phone', 'phone_verified_at', 'avatar_path',
                'locale', 'is_active', 'last_seen_at', 'deleted_at',
            ]);
        });
    }
};
