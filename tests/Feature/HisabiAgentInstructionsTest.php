<?php

use App\Ai\Agents\HisabiAgent;
use App\Services\AI\FinancialAnalyzer;
use Illuminate\Support\Carbon;

it('includes the current date and time in the system prompt', function () {
    $now = Carbon::create(2026, 4, 19, 10, 30, 0, config('app.timezone'));
    Carbon::setTestNow($now);

    $this->mock(FinancialAnalyzer::class)
        ->shouldReceive('generateSummary')
        ->andReturn('');

    $agent = new HisabiAgent(null);
    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('Current Date & Time:')
        ->and($instructions)->toContain($now->toDateTimeString())
        ->and($instructions)->toContain(config('app.timezone'));

    Carbon::setTestNow();
});

it('includes the current date and time reflecting the real clock', function () {
    $before = now()->toDateTimeString();

    $this->mock(FinancialAnalyzer::class)
        ->shouldReceive('generateSummary')
        ->andReturn('');

    $agent = new HisabiAgent(null);
    $instructions = (string) $agent->instructions();

    $after = now()->toDateTimeString();

    expect($instructions)->toContain('Current Date & Time:')
        ->and($instructions)->toContain($before)
        ->or(fn($e) => $e->toContain($after));
});
