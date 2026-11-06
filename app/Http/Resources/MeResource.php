<?php

namespace App\Http\Resources;

use App\Models\Branch;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * The signed in user, as the app needs them.
 *
 * One resource for both surfaces, because one table holds both: what makes
 * somebody staff is a branch assignment and a role, not a different record.
 * The staff and client blocks are each present only when they apply, so the
 * app can decide which navigation to show from the response alone.
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isStaff = $this->relationLoaded('staffProfile')
            ? $this->staffProfile !== null
            : $this->isStaff();

        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_display' => Phone::forDisplay($this->phone),
            'avatar_url' => $this->avatar_path ? Storage::url($this->avatar_path) : null,
            'locale' => $this->locale,
            'is_staff' => $isStaff,

            /*
             * Roles and permissions are per branch, so they are reported for
             * the branch this request is acting in. The app uses them to
             * decide what to render; the server never trusts them back.
             */
            'roles' => $isStaff ? $this->rolesForCurrentBranch() : ['client'],
            'permissions' => $isStaff ? $this->permissionNames() : [],

            'branches' => $isStaff
                ? $this->branches->map(fn (Branch $branch): array => [
                    'id' => $branch->ulid,
                    'slug' => $branch->slug,
                    'name' => $branch->name,
                    'timezone' => $branch->timezone,
                    'is_primary' => $this->isPrimary($branch),
                ])->values()->all()
                : [],

            'staff' => $this->when($isStaff, fn (): ?array => $this->staffProfile === null ? null : [
                'display_name' => $this->staffProfile->name(),
                'title' => $this->staffProfile->title,
                'is_bookable' => $this->staffProfile->is_bookable,
                'accepts_house_calls' => $this->staffProfile->accepts_house_calls,
                'photo_url' => $this->staffProfile->photo_path
                    ? Storage::url($this->staffProfile->photo_path)
                    : null,
            ]),

            'client' => $this->when(! $isStaff, fn (): ?array => $this->clientProfile === null ? null : [
                'account_number' => $this->clientProfile->account_number,
                'visit_count' => $this->clientProfile->visit_count,
                'last_visit_at' => $this->clientProfile->last_visit_at?->toIso8601String(),
                'referral_code' => $this->clientProfile->referral_code,
                'points' => app(LoyaltyService::class)->balanceFor($this->resource),
            ]),
        ];
    }

    /**
     * The pivot arrives on the branch when it was read through the user's
     * branches relation, which is the only path that loads it here.
     */
    private function isPrimary(Branch $branch): bool
    {
        $pivot = $branch->getAttribute('pivot');

        return $pivot instanceof Pivot && (bool) $pivot->getAttribute('is_primary');
    }

    /**
     * @return list<string>
     */
    private function rolesForCurrentBranch(): array
    {
        return array_values(array_map(
            static fn (mixed $name): string => (string) $name,
            $this->getRoleNames()->all(),
        ));
    }

    /**
     * @return list<string>
     */
    private function permissionNames(): array
    {
        return array_values(array_map(
            static fn (mixed $name): string => (string) $name,
            $this->getAllPermissions()->pluck('name')->all(),
        ));
    }
}
