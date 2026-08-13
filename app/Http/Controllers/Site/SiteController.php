<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\BranchService;
use App\Services\CatalogPayload;
use App\Support\Phone;
use Illuminate\Http\Request;

/**
 * Shared behaviour for the public site. Every page needs the branch list and
 * the chosen branch, because prices are per branch and nothing can be priced
 * until one is selected.
 */
abstract class SiteController extends Controller
{
    public function __construct(
        protected readonly BranchService $branches,
        protected readonly CatalogPayload $payload,
    ) {}

    /**
     * A visitor's branch choice sticks for the session, so they do not have to
     * reselect it on every page.
     */
    protected function currentBranch(Request $request): ?Branch
    {
        $slug = $request->query('branch') ?? $request->session()->get('branch_slug');

        $branch = $this->branches->resolve(is_string($slug) ? $slug : null);

        if ($branch !== null) {
            $request->session()->put('branch_slug', $branch->slug);
        }

        return $branch;
    }

    /**
     * @return array<string, mixed>
     */
    protected function shared(?Branch $branch): array
    {
        $whatsapp = $branch === null || blank($branch->whatsapp)
            ? config('magnetic.whatsapp')
            : $branch->whatsapp;

        $instagram = config('magnetic.instagram');

        return [
            'branches' => $this->payload->branches(),
            'branch' => $branch === null ? null : $this->payload->branch($branch),
            'whatsapp_link' => $whatsapp ? 'https://wa.me/'.Phone::forWhatsAppLink($whatsapp) : null,
            'instagram_url' => $instagram ? "https://instagram.com/{$instagram}" : null,
        ];
    }
}
