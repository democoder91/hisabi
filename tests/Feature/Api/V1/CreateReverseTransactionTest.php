<?php

declare(strict_types=1);

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('creates a reverse debit transaction in another editable accessible account', function () {
    $user = User::factory()->createOne();
    $primaryAccount = Account::factory()->create([
        'user_id' => $user->id,
        'name' => ['en' => 'Main Account'],
    ]);
    $sharedOwner = User::factory()->createOne();
    $reverseAccount = Account::factory()->create([
        'user_id' => $sharedOwner->id,
        'name' => ['en' => 'Shared Wallet'],
    ]);
    $reverseAccount->sharedUsers()->attach($user->id, ['permission_level' => Account::PERMISSION_EDIT]);

    $incomeCategory = Category::factory()->create([
        'user_id' => $user->id,
        'name' => ['en' => 'Salary'],
        'type' => Category::INCOME,
    ]);

    actingAs($user);

    $response = postJson('/api/v1/transactions', [
        'account_id' => $primaryAccount->id,
        'category_id' => $incomeCategory->id,
        'amount' => 150,
        'created_at' => now()->toDateString(),
        'note' => 'Monthly transfer',
        'transaction_type' => Transaction::TYPE_CREDIT,
        'create_reverse_transaction' => true,
        'reverse_account_id' => $reverseAccount->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('transaction.account.id', $primaryAccount->id)
        ->assertJsonPath('transaction.transaction_type', Transaction::TYPE_CREDIT)
        ->assertJsonCount(2, 'transactions')
        ->assertJsonPath('transactions.1.account.id', $reverseAccount->id)
        ->assertJsonPath('transactions.1.transaction_type', Transaction::TYPE_DEBIT)
        ->assertJsonPath('transactions.1.category.type', Category::EXPENSES);

    expect(Transaction::withoutGlobalScopes()->count())->toBe(2);

    $primaryTransaction = Transaction::withoutGlobalScopes()->findOrFail($response->json('transaction.id'));
    $reverseTransaction = Transaction::withoutGlobalScopes()->findOrFail($response->json('transactions.1.id'));
    $reverseCategory = Category::query()->findOrFail($reverseTransaction->category_id);

    expect($primaryTransaction->account_id)->toBe($primaryAccount->id)
        ->and($primaryTransaction->transaction_type)->toBe(Transaction::TYPE_CREDIT)
        ->and($reverseTransaction->account_id)->toBe($reverseAccount->id)
        ->and($reverseTransaction->transaction_type)->toBe(Transaction::TYPE_DEBIT)
        ->and($reverseTransaction->amount)->toBe(150.0)
        ->and($reverseTransaction->note)->toBe('Monthly transfer')
        ->and($reverseCategory->type)->toBe(Category::EXPENSES)
        ->and($reverseCategory->getTranslation('name', 'en'))->toBe('Uncategorized Expenses')
        ->and($primaryAccount->fresh()->balance)->toBe(150.0)
        ->and($reverseAccount->fresh()->balance)->toBe(-150.0);
});

it('requires a reverse account when reverse transaction creation is enabled', function () {
    $user = User::factory()->createOne();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $incomeCategory = Category::factory()->create([
        'user_id' => $user->id,
        'type' => Category::INCOME,
    ]);

    actingAs($user);

    $response = postJson('/api/v1/transactions', [
        'account_id' => $account->id,
        'category_id' => $incomeCategory->id,
        'amount' => 85,
        'created_at' => now()->toDateString(),
        'transaction_type' => Transaction::TYPE_CREDIT,
        'create_reverse_transaction' => true,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['reverse_account_id']);

    expect(Transaction::withoutGlobalScopes()->count())->toBe(0);
});

it('requires the reverse account to be different from the primary account', function () {
    $user = User::factory()->createOne();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $incomeCategory = Category::factory()->create([
        'user_id' => $user->id,
        'type' => Category::INCOME,
    ]);

    actingAs($user);

    $response = postJson('/api/v1/transactions', [
        'account_id' => $account->id,
        'category_id' => $incomeCategory->id,
        'amount' => 60,
        'created_at' => now()->toDateString(),
        'transaction_type' => Transaction::TYPE_CREDIT,
        'create_reverse_transaction' => true,
        'reverse_account_id' => $account->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['reverse_account_id']);

    expect(Transaction::withoutGlobalScopes()->count())->toBe(0);
});