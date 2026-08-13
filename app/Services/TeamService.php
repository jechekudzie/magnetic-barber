<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The barbers as the public sees them. Never exposes a staff phone number or
 * email, only what belongs on a team page.
 */
final class TeamService
{
    /**
     * @return Collection<int, StaffProfile>
     */
    public function onSite(?Branch $branch = null): Collection
    {
        return StaffProfile::query()
            ->onSite()
            ->ordered()
            // Written out rather than reusing User's active scope: inside a
            // whereHas closure the builder is not typed to User.
            ->whereHas('user', fn (Builder $query) => $query
                ->where('users.is_active', true)
                ->when($branch !== null, fn (Builder $q) => $q->whereHas(
                    'branches',
                    fn (Builder $b) => $b->whereKey($branch->id)
                )))
            ->with('user:id,ulid,name,avatar_path')
            ->get();
    }

    public function findBySlug(string $slug): ?StaffProfile
    {
        return $this->onSite()->firstWhere('slug', $slug);
    }
}
