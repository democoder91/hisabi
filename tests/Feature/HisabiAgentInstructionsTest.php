<?php

use App\Ai\Agents\HisabiAgent;
use App\Services\AI\FinancialAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('includes the current date and time in the system prompt', function () {
    $now = Carbon::create(2026, 4, 19, 10, 30, 0, config('app.timezone'));
    Carbon::setTestNow($now);

    $agent = new HisabiAgent(null);
    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('Current Date & Time:')
        ->and($instructions)->toContain($now->toDateTimeString())
        ->and($instructions)->toContain(config('app.timezone'));

    Carbon::setTestNow();
});

it('includes the current date and time reflecting the real clock', function () {
    $agent = new HisabiAgent(null);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Current Date & Time:')
        ->toContain(now()->format('Y-m-d'));
});
