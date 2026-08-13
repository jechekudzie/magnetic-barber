<?php

use App\Support\Phone;

it('normalises every way a client writes one Zimbabwean number', function (string $input) {
    expect(Phone::normalise($input))->toBe('+263781879820');
})->with([
    '0781879820',
    '+263781879820',
    '263781879820',
    '078 187 9820',
    '+263 78 187 9820',
    '(078) 187-9820',
]);

it('returns null for blank input', function () {
    expect(Phone::normalise(null))->toBeNull()
        ->and(Phone::normalise(''))->toBeNull();
});

it('hands back an unparseable value so validation can report it', function () {
    expect(Phone::normalise('not a phone'))->toBe('not a phone');
});

it('formats for display without changing what is stored', function () {
    expect(Phone::forDisplay('+263781879820'))->toBe('078 187 9820');
});

it('masks the middle digits for logs', function () {
    expect(Phone::mask('+263781879820'))->toBe('+2637****820');
});

it('strips the plus for a wa.me link', function () {
    expect(Phone::forWhatsAppLink('0781879820'))->toBe('263781879820');
});
