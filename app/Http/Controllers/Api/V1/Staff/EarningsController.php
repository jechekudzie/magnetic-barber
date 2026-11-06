<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\CommissionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What a barber has earned.
 *
 * Two audiences with different rights. A barber reads their own figures with
 * report.view.own, which every barber holds. Reading the whole team's needs
 * staff.commission.view, which only the owner and branch manager hold — one
 * barber should not be able to price another's day.
 */
class EarningsController extends Controller
{
    /**
     * Today, for the barber asking. The number on their home screen.
     */
    public function today(Request $request, CommissionService $commission): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('report.view.own'), 403);

        $branch = $this->branchFor($request);

        return response()->json([
            'data' => $commission->dayFor($user, $branch),
            'rate' => $commission->rateFor($user, $branch),
        ]);
    }

    /**
     * A run of days, for the earnings screen. Defaults to the last fortnight.
     */
    public function run(Request $request, CommissionService $commission): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('report.view.own'), 403);

        $branch = $this->branchFor($request);
        $timezone = $branch->timezone ?: config('magnetic.timezone');

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $to = isset($validated['to'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['to'], $timezone)
            : CarbonImmutable::now($timezone);

        $from = isset($validated['from'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['from'], $timezone)
            : $to->subDays(13);

        // An open ended range would let one request scan the whole table.
        abort_if($from->diffInDays($to) > 92, 422, 'That is too long a range. Ask for 90 days or less.');
        abort_if($from->isAfter($to), 422, 'Those dates are the wrong way round.');

        $days = $commission->runFor($user, $branch, $from, $to);

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rate' => $commission->rateFor($user, $branch),
            'total_cents' => array_sum(array_column(array_column($days, 'earned'), 'cents')),
            'data' => $days,
        ]);
    }

    /**
     * Everybody's day, ranked. The owner's view of the floor.
     */
    public function branch(Request $request, CommissionService $commission): JsonResponse
    {
        abort_unless($request->user()->can('staff.commission.view'), 403);

        $branch = $this->branchFor($request);
        $timezone = $branch->timezone ?: config('magnetic.timezone');

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $day = isset($validated['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['date'], $timezone)
            : CarbonImmutable::now($timezone);

        $rows = $commission->dailyEarnings($branch, $day);

        return response()->json([
            'date' => $day->toDateString(),
            'branch' => ['slug' => $branch->slug, 'name' => $branch->name],
            'total_earned_cents' => array_sum(array_column(array_column($rows, 'earned'), 'cents')),
            'total_taken_cents' => array_sum(array_column(array_column($rows, 'took'), 'cents')),
            'data' => $rows,
        ]);
    }

    private function branchFor(Request $request): Branch
    {
        abort_unless(app()->bound('currentBranch'), 403, 'You are not assigned to a branch.');

        return app('currentBranch');
    }
}
