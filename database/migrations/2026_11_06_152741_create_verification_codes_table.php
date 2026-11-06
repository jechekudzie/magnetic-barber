<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One time codes for signing a client in on their phone number.
 *
 * Clients have no password: the phone number is the identity, and proving you
 * hold that number is the whole of the login. Staff do not use this table,
 * they sign in with a password.
 *
 * The code is stored hashed. A leaked database table should not be a list of
 * live login codes, and nothing ever needs to read one back — verification
 * only ever compares.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->comment('E.164, as stored on users');
            $table->string('code_hash');
            $table->string('purpose')->default('login');

            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();

            // Guessing a six digit code is cheap if you are allowed to keep
            // trying, so each code carries its own attempt count.
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->string('request_ip', 45)->nullable();
            $table->timestamps();

            // The verify read: the newest live code for this number.
            $table->index(['phone', 'purpose', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};
