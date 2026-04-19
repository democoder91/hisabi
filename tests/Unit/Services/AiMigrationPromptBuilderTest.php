<?php

use App\Services\Finance\AiMigrationPromptBuilder;

it('builds a double entry migration prompt from legacy transaction data', function () {
    $prompt = (new AiMigrationPromptBuilder())->build([
        'date' => '2026-04-19',
        'amount' => 250,
        'currency' => 'egp',
        'description' => 'Internet bill',
        'account_name' => '{"en":"Cash"}',
        'category_name' => ['en' => 'Bills'],
    ]);

    expect($prompt)
        ->toContain("On 2026-04-19")
        ->toContain("250.00 EGP")
        ->toContain("'Internet bill'")
        ->toContain("'Cash'")
        ->toContain("'Bills'")
        ->toContain('double-entry ledger');
});
