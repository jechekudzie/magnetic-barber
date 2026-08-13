<?php

use App\Enums\ClientSource;
use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\User;
use App\Services\ClientAccountService;

beforeEach(function () {
    $this->accounts = app(ClientAccountService::class);
    $this->branch = Branch::factory()->create(['code' => 'MB']);
    BranchSequence::create(['branch_id' => $this->branch->id, 'last_account_number' => 0]);
});

it('issues account numbers in the MB-0001 format', function () {
    expect($this->accounts->nextAccountNumber($this->branch))->toBe('MB-0001')
        ->and($this->accounts->nextAccountNumber($this->branch))->toBe('MB-0002');
});

it('never hands the same number to two clients', function () {
    $numbers = collect(range(1, 25))
        ->map(fn (): string => $this->accounts->nextAccountNumber($this->branch));

    expect($numbers->unique())->toHaveCount(25);
});

it('keeps each branch on its own counter', function () {
    $borrowdale = Branch::factory()->create(['code' => 'BD']);
    BranchSequence::create(['branch_id' => $borrowdale->id, 'last_account_number' => 0]);

    $this->accounts->nextAccountNumber($this->branch);

    expect($this->accounts->nextAccountNumber($borrowdale))->toBe('BD-0001');
});

it('starts a counter for a branch that has none yet', function () {
    $fresh = Branch::factory()->create(['code' => 'NW']);

    expect($this->accounts->nextAccountNumber($fresh))->toBe('NW-0001');
});

it('registers a new client with an account number and a referral code', function () {
    $user = $this->accounts->register('Tendai M', '0781879820', $this->branch, ClientSource::Walkin);

    expect($user->phone)->toBe('+263781879820')
        ->and($user->password)->toBeNull()
        ->and($user->clientProfile->account_number)->toBe('MB-0001')
        ->and($user->clientProfile->referral_code)->toHaveLength(6)
        ->and($user->clientProfile->source)->toBe(ClientSource::Walkin);
});

it('returns the existing client instead of creating a duplicate from a different phone format', function () {
    $first = $this->accounts->register('Tendai M', '0781879820', $this->branch);
    $second = $this->accounts->register('Tendai Moyo', '+263 78 187 9820', $this->branch);

    expect($second->id)->toBe($first->id)
        ->and(User::count())->toBe(1)
        ->and($second->clientProfile->account_number)->toBe('MB-0001');
});

it('finds a client by any format of their number', function () {
    $this->accounts->register('Tendai M', '0781879820', $this->branch);

    expect($this->accounts->findByPhone('263781879820'))->not->toBeNull()
        ->and($this->accounts->findByPhone('078 187 9820'))->not->toBeNull()
        ->and($this->accounts->findByPhone('0712000000'))->toBeNull();
});
