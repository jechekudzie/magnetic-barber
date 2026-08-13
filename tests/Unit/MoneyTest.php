<?php

use App\Support\Money;

it('builds from a decimal amount without floating point drift', function () {
    expect(Money::of(12.35, 'USD')->cents)->toBe(1235)
        ->and(Money::of(0.1, 'USD')->cents)->toBe(10)
        ->and(Money::of(19.99, 'USD')->cents)->toBe(1999);
});

it('drops the decimals on whole amounts and keeps them otherwise', function () {
    expect(Money::ofCents(1200, 'USD')->format())->toBe('$12')
        ->and(Money::ofCents(1250, 'USD')->format())->toBe('$12.50')
        ->and(Money::ofCents(0, 'USD')->format())->toBe('$0');
});

it('formats ZiG and unknown currencies distinctly', function () {
    expect(Money::ofCents(4500, 'ZWG')->format())->toBe('ZiG 45')
        ->and(Money::ofCents(4500, 'ZAR')->format())->toBe('ZAR 45');
});

it('adds and subtracts within one currency', function () {
    $total = Money::of(12, 'USD')->plus(Money::of(8, 'USD'));

    expect($total->cents)->toBe(2000)
        ->and($total->minus(Money::of(5, 'USD'))->cents)->toBe(1500)
        ->and(Money::of(12, 'USD')->times(3)->cents)->toBe(3600);
});

it('refuses to mix currencies rather than quietly guessing a rate', function () {
    expect(fn () => Money::of(10, 'USD')->plus(Money::of(10, 'ZWG')))
        ->toThrow(InvalidArgumentException::class);
});

it('exposes one array shape for the website and the mobile app', function () {
    expect(Money::ofCents(1500, 'USD')->toArray())->toBe([
        'cents' => 1500,
        'currency' => 'USD',
        'amount' => 15.0,
        'formatted' => '$15',
    ]);
});

it('treats a missing amount as zero rather than null', function () {
    expect(Money::ofCents(null, null)->cents)->toBe(0)
        ->and(Money::ofCents(null, null)->currency)->toBe('USD')
        ->and(Money::ofCents(0, 'USD')->isZero())->toBeTrue();
});
