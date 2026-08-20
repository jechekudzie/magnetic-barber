<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Services\ReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    /**
     * The sidebar badge. Cached briefly because it renders on every admin
     * page and the answer only moves when somebody books or a day passes.
     */
    private function overdueClients(Request $request): int
    {
        if ($request->user()?->can('client.view') !== true) {
            return 0;
        }

        // The container binding is untyped, so check rather than assume.
        $branch = app()->bound('currentBranch') ? app('currentBranch') : null;
        $branch = $branch instanceof Branch ? $branch : null;

        return Cache::remember(
            'reminders.due.'.($branch === null ? 'all' : $branch->id),
            now()->addMinutes(5),
            fn (): int => app(ReminderService::class)->dueCount($branch),
        );
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'counts' => [
                'reminders' => $this->overdueClients($request),
            ],
        ];
    }
}
