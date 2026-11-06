<?php

use App\Models\Branch;
use App\Models\Service;
use App\Services\CatalogService;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogSeeder;

/**
 * The seeded menu is checked against the printed men's and ladies' price
 * cards, line for line. If someone edits a price in the seeder, this is what
 * tells them the shop's cards now disagree with the app.
 */
beforeEach(function () {
    $this->seed(BranchSeeder::class);
    $this->seed(CatalogSeeder::class);

    $this->branch = Branch::where('slug', 'harare-avenues')->sole();
});

/**
 * Every line on both printed cards, as a single dataset.
 *
 * One dataset rather than two: chaining ->with() twice builds a cross product
 * and passes the second set as extra arguments the closure quietly ignores,
 * so half the cards would never actually be asserted.
 */
dataset('price cards', [
    // Men's card.
    'men: Standard Haircut $10' => ['standard-haircut', 1000, false],
    'men: Haircut + Dye $10' => ['haircut-and-dye', 1000, false],
    'men: Haircut + Curls $15' => ['haircut-and-curls', 1500, false],
    'men: Haircut + Mini Facial $15' => ['haircut-and-mini-facial', 1500, false],
    'men: Haircut + Tint from $20' => ['haircut-and-tint', 2000, true],
    'men: Kids Haircut $10' => ['kids-haircut', 1000, false],
    'men: Deep Clean & LED Therapy Facial $10' => ['the-deep-clean-and-led-therapy-facial', 1000, false],

    // Ladies' card. The facial is on both at $10, so it is one service.
    'ladies: Ladies Cut $10' => ['ladies-cut', 1000, false],
    'ladies: Deep Clean & LED Therapy Facial $10' => ['the-deep-clean-and-led-therapy-facial', 1000, false],
    'ladies: Haircut + Dye $15' => ['ladies-haircut-and-dye', 1500, false],
    'ladies: Haircut + Xcurl $15' => ['ladies-haircut-and-xcurl', 1500, false],
    'ladies: Haircut + Tint from $20' => ['ladies-haircut-and-tint', 2000, true],
    'ladies: Eyebrow Shaping + Mini Facial $10' => ['eyebrow-shaping-and-mini-facial', 1000, false],
]);

it('sells every line on the price cards at the printed price', function (string $slug, int $cents, bool $isFrom) {
    $service = $this->branch->services()
        ->where('services.slug', $slug)
        ->wherePivot('is_active', true)
        ->first();

    expect($service)->not->toBeNull("{$slug} is not on the menu")
        ->and($service->priceForLoadedBranch()->cents)->toBe($cents)
        ->and($service->priceIsFromForLoadedBranch())->toBe($isFrom);
})->with('price cards');

it('charges men and ladies differently for a dye, which is why they are two services', function () {
    $mens = $this->branch->services()->where('services.slug', 'haircut-and-dye')->first();
    $ladies = $this->branch->services()->where('services.slug', 'ladies-haircut-and-dye')->first();

    expect($mens->priceForLoadedBranch()->cents)->toBe(1000)
        ->and($ladies->priceForLoadedBranch()->cents)->toBe(1500);
});

it('quotes the tint as a starting figure and everything else as final', function () {
    $fromPriced = $this->branch->services()
        ->wherePivot('is_from_price', true)
        ->pluck('services.slug')
        ->sort()
        ->values()
        ->all();

    expect($fromPriced)->toBe(['haircut-and-tint', 'ladies-haircut-and-tint']);
});

it('sells nothing that is not on a price card', function () {
    $onTheMenu = app(CatalogService::class)
        ->priceListFor($this->branch)
        ->flatMap(fn ($category) => $category->services->pluck('slug'))
        ->sort()
        ->values()
        ->all();

    expect($onTheMenu)->toBe([
        'eyebrow-shaping-and-mini-facial',
        'haircut-and-curls',
        'haircut-and-dye',
        'haircut-and-mini-facial',
        'haircut-and-tint',
        'kids-haircut',
        'ladies-cut',
        'ladies-haircut-and-dye',
        'ladies-haircut-and-tint',
        'ladies-haircut-and-xcurl',
        'standard-haircut',
        'the-deep-clean-and-led-therapy-facial',
    ]);
});

it('retires an off card service rather than deleting it, so old bookings can still name it', function () {
    $beardTrim = Service::factory()->create(['slug' => 'beard-trim-and-shape', 'name' => 'Beard Trim and Shape']);
    $this->branch->services()->attach($beardTrim, ['price_cents' => 1000, 'is_active' => true]);

    $this->seed(CatalogSeeder::class);

    $beardTrim->refresh();

    expect($beardTrim->trashed())->toBeTrue()
        ->and($beardTrim->is_active)->toBeFalse()
        ->and(Service::withTrashed()->where('slug', 'beard-trim-and-shape')->exists())->toBeTrue();
});

it('runs twice without duplicating or losing the menu', function () {
    $this->seed(CatalogSeeder::class);

    expect(Service::count())->toBe(12)
        ->and($this->branch->services()->wherePivot('is_active', true)->count())->toBe(12);
});

it('tags every line with the card it is printed on', function () {
    $byAudience = Service::query()
        ->get()
        ->groupBy(fn (Service $service): string => $service->audience->value)
        ->map(fn ($group) => $group->pluck('slug')->sort()->values()->all());

    expect($byAudience['men'])->toBe([
        'haircut-and-curls',
        'haircut-and-dye',
        'haircut-and-mini-facial',
        'haircut-and-tint',
        'standard-haircut',
    ])->and($byAudience['women'])->toBe([
        'eyebrow-shaping-and-mini-facial',
        'ladies-cut',
        'ladies-haircut-and-dye',
        'ladies-haircut-and-tint',
        'ladies-haircut-and-xcurl',
    ])
        // On both cards at $10, so one service rather than two.
        ->and($byAudience['unisex'])->toBe(['the-deep-clean-and-led-therapy-facial'])
        ->and($byAudience['kids'])->toBe(['kids-haircut']);
});

it('puts seven lines on the mens card and six on the ladies, as printed', function () {
    $onCard = fn (string $card): int => Service::query()
        ->whereIn('audience', [$card, 'unisex'])
        ->count();

    // The men's card also carries the kids cut.
    expect($onCard('men') + Service::where('audience', 'kids')->count())->toBe(7)
        ->and($onCard('women'))->toBe(6);
});

it('leaves one branch trading, with the test branch retired', function () {
    expect(Branch::where('is_active', true)->pluck('slug')->all())->toBe(['harare-avenues']);
});

it('still prices per branch, so a second shop can charge its own rates', function () {
    $second = Branch::factory()->create();
    $cut = Service::where('slug', 'standard-haircut')->sole();

    $second->services()->attach($cut, ['price_cents' => 1400, 'currency' => 'USD', 'is_active' => true]);

    $here = $this->branch->services()->whereKey($cut->id)->first();
    $there = $second->services()->whereKey($cut->id)->first();

    expect($here->priceForLoadedBranch()->cents)->toBe(1000)
        ->and($there->priceForLoadedBranch()->cents)->toBe(1400);
});
