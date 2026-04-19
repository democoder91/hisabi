<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class MetricsTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    protected function createAccount(array $attributes = []): Account
    {
        return Account::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'currency' => $this->user->default_currency ?? config('hisabi.currency', 'AED'),
        ], $attributes));
    }

    protected function createLedgerTransaction(Account $fromAccount, Account $toAccount, float $amount, array $attributes = []): Transaction
    {
        $transactionType = $attributes['transaction_type']
            ?? ($fromAccount->type === Account::TYPE_INCOME ? Transaction::TYPE_CREDIT : Transaction::TYPE_DEBIT);

        return Transaction::withoutGlobalScopes()->create(array_merge([
            'user_id' => $this->user->id,
            'account_id' => $transactionType === Transaction::TYPE_CREDIT ? $toAccount->id : $fromAccount->id,
            'category_id' => null,
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => $amount,
            'transaction_type' => $transactionType,
            'currency' => $attributes['currency'] ?? $fromAccount->currency,
            'note' => $attributes['note'] ?? 'Metric transaction',
            'created_at' => $attributes['created_at'] ?? now(),
        ], $attributes));
    }
}
