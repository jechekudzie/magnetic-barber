<?php

namespace App\Http\Controllers\Admin;

use App\Models\LoyaltyLedger;
use App\Models\LoyaltyRule;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class LoyaltyController extends AdminController
{
    public function index(Request $request, LoyaltyService $loyalty): Response
    {
        $rule = LoyaltyRule::current();

        $topClients = User::query()
            ->whereHas('clientProfile')
            ->with('clientProfile')
            ->withSum('loyaltyLedger as points_balance', 'points')
            ->orderByDesc('points_balance')
            ->limit(15)
            ->get()
            ->map(fn (User $client): array => [
                'id' => $client->ulid,
                'name' => $client->name,
                'account_number' => $client->clientProfile()->value('account_number'),
                'visit_count' => $client->clientProfile()->value('visit_count') ?? 0,
                'points' => (int) ($client->points_balance ?? 0),
                'redeemable' => (int) ($client->points_balance ?? 0) >= $rule->redemption_threshold,
                'worth' => Money::ofCents(
                    $rule->valueOfCents((int) ($client->points_balance ?? 0)),
                    $rule->currency,
                )->toArray(),
            ])
            ->all();

        return inertia('admin/loyalty', [
            'branchContext' => $this->branchContext($request),
            'rule' => [
                'id' => $rule->id,
                'name' => $rule->name,
                'points_per_visit' => $rule->points_per_visit,
                'points_per_currency_unit' => $rule->points_per_currency_unit,
                'redemption_threshold' => $rule->redemption_threshold,
                'redemption_value' => $rule->redemption_value_cents / 100,
                'points_expiry_months' => $rule->points_expiry_months,
                'is_active' => $rule->is_active,
            ],
            'clients' => $topClients,
            'recent' => LoyaltyLedger::query()
                ->latest('id')
                ->with('client')
                ->limit(20)
                ->get()
                ->map(fn (LoyaltyLedger $row): array => [
                    'id' => $row->id,
                    'client' => $row->client?->name,
                    'type' => $row->type,
                    'points' => $row->points,
                    'balance_after' => $row->balance_after,
                    'description' => $row->description,
                    'at' => $row->created_at?->toDateTimeString(),
                ])
                ->all(),
            'totals' => [
                'issued' => (int) LoyaltyLedger::query()->where('points', '>', 0)->sum('points'),
                'redeemed' => abs((int) LoyaltyLedger::query()->where('points', '<', 0)->sum('points')),
                'outstanding' => (int) LoyaltyLedger::query()->sum('points'),
            ],
        ]);
    }

    /**
     * There is only ever one rule in force. Saving replaces it rather than
     * adding a second, because two overlapping earn rates produce a balance
     * nobody can explain to the client holding it.
     */
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('loyalty.adjust'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'points_per_visit' => ['required', 'integer', 'min:0', 'max:500'],
            'points_per_currency_unit' => ['required', 'numeric', 'min:0', 'max:100'],
            'redemption_threshold' => ['required', 'integer', 'min:1', 'max:10000'],
            'redemption_value' => ['required', 'numeric', 'min:0', 'max:1000'],
            'points_expiry_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $rule = LoyaltyRule::query()->where('is_active', true)->latest('id')->first()
            ?? new LoyaltyRule;

        $rule->fill([
            'name' => $validated['name'],
            'points_per_visit' => $validated['points_per_visit'],
            'points_per_currency_unit' => $validated['points_per_currency_unit'],
            'redemption_threshold' => $validated['redemption_threshold'],
            'redemption_value_cents' => Money::of($validated['redemption_value'])->cents,
            'points_expiry_months' => $validated['points_expiry_months'],
            'currency' => config('magnetic.default_currency'),
            'is_active' => true,
        ])->save();

        return back()->with('success', 'Loyalty rules updated.');
    }

    /**
     * A manual correction, for the times the shop owes somebody points that
     * the system has no way of knowing about.
     */
    public function adjust(Request $request, LoyaltyService $loyalty): RedirectResponse
    {
        abort_unless($request->user()?->can('loyalty.adjust'), 403);

        $validated = $request->validate([
            'client' => ['required', 'string', 'exists:users,ulid'],
            'points' => ['required', 'integer', 'min:-10000', 'max:10000', 'not_in:0'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ]);

        $client = User::query()->where('ulid', $validated['client'])->firstOrFail();

        $loyalty->adjust(
            $client,
            $validated['points'],
            $validated['reason'],
            $request->user()->id,
        );

        return back()->with('success', "Adjusted {$client->name} by {$validated['points']} points.");
    }
}
