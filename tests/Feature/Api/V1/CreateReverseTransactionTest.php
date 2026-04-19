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

it('rejects legacy reverse transaction fields', function () {
    $user = User::factory()->createOne();
    $primaryAccount = Account::factory()->create([
        'user_id' => $user->id,
        'name' => ['en' => 'Main Account'],
    ]);

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
        'create_reverse_transaction' => true,
        'reverse_account_id' => 9999,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['create_reverse_transaction', 'reverse_account_id']);

    expect(Transaction::withoutGlobalScopes()->count())->toBe(0)
        ->and($primaryAccount->fresh()->balance)->toBe(0.0)
        ->and(Category::query()->find($incomeCategory->id))->not->toBeNull();
});
