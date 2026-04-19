<?php

namespace Tests\Unit\Domains\Transaction\Services;

use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use App\Domains\Transaction\Services\TransactionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->service = new TransactionService();
    }

    public function test_it_returns_paginated_transactions(): void
    {
        // Arrange
        Transaction::factory()->count(3)->create();

        // Act
        $result = $this->service->getPaginated(perPage: 10);

        // Assert
        $this->assertCount(3, $result->items());
        $this->assertEquals(3, $result->total());
        $this->assertEquals(1, $result->currentPage());
        $this->assertFalse($result->hasMorePages());
    }

    public function test_it_paginates_results_correctly(): void
    {
        // Arrange
        Transaction::factory()->count(15)->create();

        // Act
        $page1 = $this->service->getPaginated(perPage: 10);

        // Simulate page 2 request
        request()->merge(['page' => 2]);
        $page2 = $this->service->getPaginated(perPage: 10);

        // Assert
        $this->assertCount(10, $page1->items());
        $this->assertTrue($page1->hasMorePages());
        $this->assertEquals(1, $page1->currentPage());

        $this->assertCount(5, $page2->items());
        $this->assertFalse($page2->hasMorePages());
        $this->assertEquals(2, $page2->currentPage());
    }

    public function test_it_sorts_by_id_descending_by_default(): void
    {
        // Arrange
        $transaction1 = Transaction::factory()->create();
        $transaction2 = Transaction::factory()->create();
        $transaction3 = Transaction::factory()->create();

        // Act
        $result = $this->service->getPaginated(perPage: 10);

        // Assert
        $items = $result->items();
        $this->assertEquals($transaction3->id, $items[0]->id);
        $this->assertEquals($transaction2->id, $items[1]->id);
        $this->assertEquals($transaction1->id, $items[2]->id);
    }

    public function test_it_does_not_eager_load_category_relation(): void
    {
        // Arrange
        Transaction::factory()->create();

        // Act
        $result = $this->service->getPaginated(perPage: 10);

        // Assert
        $item = $result->items()[0];
        $this->assertFalse($item->relationLoaded('category'));
    }

    public function test_it_searches_by_amount(): void
    {
        // Arrange
        Transaction::factory()->create(['amount' => 100.50]);
        Transaction::factory()->create(['amount' => 200.75]);
        $matchingTransaction = Transaction::factory()->create(['amount' => 150.25]);

        // Act
        request()->merge(['filter' => ['search' => '150']]);
        $result = $this->service->getPaginated(perPage: 10);

        // Assert
        $this->assertCount(1, $result->items());
        $this->assertEquals($matchingTransaction->id, $result->items()[0]->id);
    }

    public function test_it_searches_by_note(): void
    {
        // Arrange
        Transaction::factory()->create(['note' => 'Grocery shopping']);
        Transaction::factory()->create(['note' => 'Fuel for car']);
        $matchingTransaction = Transaction::factory()->create(['note' => 'Coffee with friends']);

        // Act
        request()->merge(['filter' => ['search' => 'coffee']]);
        $result = $this->service->getPaginated(perPage: 10);

        // Assert
        $this->assertCount(1, $result->items());
        $this->assertEquals($matchingTransaction->id, $result->items()[0]->id);
    }

    public function test_it_searches_by_account_name(): void
    {
        // Arrange
        $travelWallet = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Travel Wallet', 'ar' => null],
        ]);
        $groceries = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Groceries', 'ar' => null],
        ]);

        Transaction::factory()->create([
            'account_id' => $groceries->id,
            'category_id' => null,
        ]);
        $matchingTransaction = Transaction::factory()->create([
            'account_id' => $travelWallet->id,
            'category_id' => null,
        ]);

        // Act
        request()->merge(['filter' => ['search' => 'travel']]);
        $result = $this->service->getPaginated(perPage: 10);

        // Assert
        $this->assertCount(1, $result->items());
        $this->assertEquals($matchingTransaction->id, $result->items()[0]->id);
        $this->assertEquals($travelWallet->id, $result->items()[0]->account_id);
    }

    public function test_it_searches_across_multiple_fields(): void
    {
        // Arrange
        $coffeeWallet = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Coffee Wallet', 'ar' => null],
        ]);
        $groceries = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Groceries', 'ar' => null],
        ]);
        $travel = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Travel', 'ar' => null],
        ]);

        $t1 = Transaction::factory()->create([
            'account_id' => $coffeeWallet->id,
            'category_id' => null,
            'amount' => 100,
            'note' => 'Regular purchase'
        ]);

        $t2 = Transaction::factory()->create([
            'account_id' => $groceries->id,
            'category_id' => null,
            'amount' => 200,
            'note' => 'Coffee beans'
        ]);

        $t3 = Transaction::factory()->create([
            'account_id' => $travel->id,
            'category_id' => null,
            'amount' => 300,
            'note' => 'Other purchase'
        ]);

        // Act
        request()->merge(['filter' => ['search' => 'coffee']]);
        $result = $this->service->getPaginated(perPage: 10);

        // Assert
        $this->assertCount(2, $result->items());
        $ids = collect($result->items())->pluck('id')->toArray();
        $this->assertContains($t1->id, $ids);
        $this->assertContains($t2->id, $ids);
        $this->assertNotContains($t3->id, $ids);
    }

    public function test_it_returns_empty_result_when_search_has_no_matches(): void
    {
        // Arrange
        Transaction::factory()->count(5)->create();

        // Act
        request()->merge(['filter' => ['search' => 'nonexistent']]);
        $result = $this->service->getPaginated(perPage: 10);

        // Assert
        $this->assertCount(0, $result->items());
        $this->assertEquals(0, $result->total());
    }

    public function test_it_allows_sorting_by_amount_ascending(): void
    {
        // Arrange
        $t1 = Transaction::factory()->create(['amount' => 300]);
        $t2 = Transaction::factory()->create(['amount' => 100]);
        $t3 = Transaction::factory()->create(['amount' => 200]);

        // Act
        request()->merge(['sort' => 'amount']);
        $result = $this->service->getPaginated(perPage: 10);

        // Assert
        $items = $result->items();
        $this->assertEquals($t2->id, $items[0]->id);
        $this->assertEquals($t3->id, $items[1]->id);
        $this->assertEquals($t1->id, $items[2]->id);
    }

    public function test_it_allows_sorting_by_amount_descending(): void
    {
        // Arrange
        $t1 = Transaction::factory()->create(['amount' => 300]);
        $t2 = Transaction::factory()->create(['amount' => 100]);
        $t3 = Transaction::factory()->create(['amount' => 200]);

        // Act
        request()->merge(['sort' => '-amount']);
        $result = $this->service->getPaginated(perPage: 10);

        // Assert
        $items = $result->items();
        $this->assertEquals($t1->id, $items[0]->id);
        $this->assertEquals($t3->id, $items[1]->id);
        $this->assertEquals($t2->id, $items[2]->id);
    }

    public function test_it_allows_sorting_by_created_at(): void
    {
        // Arrange
        $t1 = Transaction::factory()->create(['created_at' => now()->subDays(2)]);
        $t2 = Transaction::factory()->create(['created_at' => now()->subDays(1)]);
        $t3 = Transaction::factory()->create(['created_at' => now()]);

        // Act
        request()->merge(['sort' => '-created_at']);
        $result = $this->service->getPaginated(perPage: 10);

        // Assert
        $items = $result->items();
        $this->assertEquals($t3->id, $items[0]->id);
        $this->assertEquals($t2->id, $items[1]->id);
        $this->assertEquals($t1->id, $items[2]->id);
    }

    public function test_it_respects_custom_per_page_parameter(): void
    {
        // Arrange
        Transaction::factory()->count(10)->create();

        // Act
        $result = $this->service->getPaginated(perPage: 3);

        // Assert
        $this->assertCount(3, $result->items());
        $this->assertEquals(3, $result->perPage());
        $this->assertTrue($result->hasMorePages());
    }
}
