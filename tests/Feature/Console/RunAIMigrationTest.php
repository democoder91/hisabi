<?php

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

it('migrates a legacy expense transaction into the double entry ledger', function () {
    $user = User::factory()->create(['default_currency' => 'EGP']);

    $liveCategory = Category::factory()->create([
        'id' => 501,
        'user_id' => $user->id,
        'type' => Category::EXPENSES,
        'name' => ['en' => 'Groceries', 'ar' => null],
        'color' => 'green',
        'icon' => 'basket',
    ]);

    DB::table('legacy_categories')->insert([
        'id' => $liveCategory->id,
        'name' => json_encode(['en' => 'Groceries', 'ar' => null], JSON_UNESCAPED_UNICODE),
        'type' => Category::EXPENSES,
        'color' => 'green',
        'icon' => 'basket',
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    DB::table('legacy_accounts')->insert([
        'id' => 700,
        'user_id' => $user->id,
        'name' => json_encode(['en' => 'Wallet', 'ar' => null], JSON_UNESCAPED_UNICODE),
        'type' => Account::TYPE_ASSET,
        'parent_id' => null,
        'balance' => 0,
        'currency' => 'EGP',
        'color' => null,
        'icon' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    DB::table('legacy_transactions')->insert([
        'id' => 900,
        'user_id' => $user->id,
        'amount' => 25,
        'created_at' => '2026-04-19 12:30:00',
        'updated_at' => '2026-04-19 12:30:00',
        'note' => 'Milk and bread',
        'currency' => 'EGP',
        'transaction_type' => Transaction::TYPE_DEBIT,
        'account_id' => 700,
        'category_id' => $liveCategory->id,
        'deleted_at' => null,
        'is_migrated' => false,
        'migration_error' => null,
    ]);

    artisan('financial:ai-migrate', ['--limit' => 1])
        ->expectsOutput('Migrated legacy transaction 900 into ledger transaction 1.')
        ->assertSuccessful();

    $wallet = Account::query()->where('user_id', $user->id)->where('type', Account::TYPE_ASSET)->firstOrFail();
    $groceries = Account::query()->where('user_id', $user->id)->where('type', Account::TYPE_EXPENSE)->firstOrFail();

    expect($wallet->getTranslation('name', 'en'))->toBe('Wallet');
    expect($groceries->getTranslation('name', 'en'))->toBe('Groceries');
    expect((float) $wallet->balance)->toBe(-25.0);
    expect((float) $groceries->balance)->toBe(25.0);

    $transaction = Transaction::query()->firstOrFail();

    expect($transaction->from_account_id)->toBe($wallet->id);
    expect($transaction->to_account_id)->toBe($groceries->id);
    expect($transaction->account_id)->toBe($wallet->id);
    expect($transaction->category_id)->toBe($liveCategory->id);
    expect($transaction->transaction_type)->toBe(Transaction::TYPE_DEBIT);
    expect($transaction->note)->toBe('Milk and bread');

    expect(DB::table('legacy_transactions')->where('id', 900)->value('is_migrated'))->toBe(1);
    expect(DB::table('transaction_audits')->count())->toBe(1);
});
