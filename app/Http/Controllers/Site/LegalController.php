<?php

namespace App\Http\Controllers\Site;

use App\Models\LoyaltyRule;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The two pages a client is entitled to read before they hand over a phone
 * number, and that the app stores both require before an app collecting
 * personal data may be distributed.
 *
 * The numbers these pages quote are read from the same config and the same
 * loyalty rule the shop actually enforces, rather than typed into the prose.
 * A terms page promising a window the booking service does not honour is
 * worse than no terms page at all.
 */
class LegalController extends SiteController
{
    public function privacy(Request $request): Response
    {
        return inertia('site/privacy', [
            'site' => $this->shared($this->currentBranch($request)),
        ]);
    }

    public function terms(Request $request): Response
    {
        $rule = LoyaltyRule::current();

        return inertia('site/terms', [
            'site' => $this->shared($this->currentBranch($request)),
            'cancellation_hours' => (int) config('magnetic.cancellation_hours'),
            'loyalty' => [
                'points_per_visit' => $rule->points_per_visit,
                'threshold' => $rule->redemption_threshold,
                'reward' => $rule->redemptionValue()->toArray(),
                'expiry_months' => $rule->points_expiry_months,
            ],
        ]);
    }
}
