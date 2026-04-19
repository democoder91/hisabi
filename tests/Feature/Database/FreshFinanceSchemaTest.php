<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the live finance schema directly on a fresh migration', function () {
    expect(Schema::hasTable('accounts'))->toBeTrue()
        ->and(Schema::hasTable('categories'))->toBeTrue()
        ->and(Schema::hasTable('transactions'))->toBeTrue()
        ->and(Schema::hasTable('budgets'))->toBeTrue()
        ->and(Schema::hasTable('budget_category'))->toBeFalse()
        ->and(Schema::hasTable('budget_account'))->toBeTrue()
        ->and(Schema::hasTable('transaction_audits'))->toBeTrue()
        ->and(Schema::hasTable('legacy_accounts'))->toBeFalse()
        ->and(Schema::hasTable('legacy_categories'))->toBeFalse()
        ->and(Schema::hasTable('legacy_transactions'))->toBeFalse();

    expect(Schema::hasColumns('accounts', [
        'user_id',
        'name',
        'type',
        'parent_id',
        'balance',
        'currency',
        'color',
        'icon',
        'deleted_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('categories', [
        'user_id',
        'account_id',
        'name',
        'type',
        'color',
        'icon',
        'deleted_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('transactions', [
        'user_id',
        'account_id',
        'category_id',
        'from_account_id',
        'to_account_id',
        'amount',
        'currency',
        'transaction_type',
        'note',
        'description',
        'date',
        'meta',
        'deleted_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('budgets', [
        'user_id',
        'name',
        'amount',
        'currency',
        'start_at',
        'end_at',
        'saving',
        'period',
        'reoccurrence',
        'deleted_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('budget_account', [
        'budget_id',
        'account_id',
        'deleted_at',
    ]))->toBeTrue();
});