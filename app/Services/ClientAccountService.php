<?php

namespace App\Services;

use App\Enums\ClientSource;
use App\Models\Branch;
use App\Models\ClientProfile;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issuing an account number. The deck promises MB-0143 on the spot, and two
 * receptionists registering clients at the same moment must never collide, so
 * the counter is read under a row lock inside the transaction.
 */
final class ClientAccountService
{
    /**
     * Finds the client behind a phone number, whatever format it arrived in.
     */
    public function findByPhone(string $phone): ?User
    {
        $normalised = Phone::normalise($phone);

        if ($normalised === null) {
            return null;
        }

        return User::query()->where('phone', $normalised)->first();
    }

    /**
     * Find or create. Always search first: reception creating a duplicate is
     * the single most common data problem in a shop system.
     */
    public function register(
        string $name,
        string $phone,
        Branch $branch,
        ClientSource $source = ClientSource::Walkin,
    ): User {
        $existing = $this->findByPhone($phone);

        if ($existing !== null) {
            $existing->clientProfile()->firstOrCreate(
                [],
                $this->profileAttributes($branch, $source),
            );

            return $existing;
        }

        return DB::transaction(function () use ($name, $phone, $branch, $source): User {
            $user = User::create([
                'name' => $name,
                'phone' => $phone,
            ]);

            $user->clientProfile()->create($this->profileAttributes($branch, $source));

            return $user->fresh(['clientProfile']);
        });
    }

    /**
     * Format MB-0143. The lock is what makes this safe under concurrency; a
     * max(id)+1 outside a transaction hands two clients the same number.
     */
    public function nextAccountNumber(Branch $branch): string
    {
        return DB::transaction(function () use ($branch): string {
            $sequence = DB::table('branch_sequences')
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                DB::table('branch_sequences')->insert([
                    'branch_id' => $branch->id,
                    'last_account_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $next = 1;
            } else {
                $next = $sequence->last_account_number + 1;

                DB::table('branch_sequences')
                    ->where('branch_id', $branch->id)
                    ->update(['last_account_number' => $next, 'updated_at' => now()]);
            }

            return sprintf('%s-%04d', $branch->code, $next);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function profileAttributes(Branch $branch, ClientSource $source): array
    {
        return [
            'account_number' => $this->nextAccountNumber($branch),
            'home_branch_id' => $branch->id,
            'referral_code' => $this->uniqueReferralCode(),
            'source' => $source,
        ];
    }

    private function uniqueReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (ClientProfile::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
