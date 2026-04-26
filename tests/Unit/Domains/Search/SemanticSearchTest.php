<?php

namespace Tests\Unit\Domains\Search;

use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use App\Domains\Search\Models\SearchableRecord;
use App\Domains\Search\Services\SemanticSearchService;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemanticSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_creating_an_account_indexes_one_row_per_locale(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Emergency Fund', 'ar' => 'صندوق الطوارئ'],
        ]);

        $rows = SearchableRecord::query()
            ->where('searchable_type', $account->getMorphClass())
            ->where('searchable_id', $account->id)
            ->orderBy('locale')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['ar', 'en'], $rows->pluck('locale')->all());
        $this->assertSame('صندوق الطوارئ', $rows->firstWhere('locale', 'ar')->content);
        $this->assertSame('Emergency Fund', $rows->firstWhere('locale', 'en')->content);
        $this->assertNotEmpty($rows->first()->embedding);
    }

    public function test_account_index_is_removed_when_account_is_deleted(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Vacation Savings'],
        ]);

        $this->assertSame(1, SearchableRecord::query()->where('searchable_id', $account->id)->count());

        $account->delete();

        $this->assertSame(0, SearchableRecord::query()
            ->where('searchable_type', $account->getMorphClass())
            ->where('searchable_id', $account->id)
            ->count());
    }

    public function test_account_index_is_replaced_when_name_changes(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Original Name'],
        ]);

        $account->setTranslations('name', ['en' => 'Renamed Account'])->save();

        $row = SearchableRecord::query()
            ->where('searchable_type', $account->getMorphClass())
            ->where('searchable_id', $account->id)
            ->where('locale', 'en')
            ->first();

        $this->assertSame('Renamed Account', $row->content);
    }

    public function test_search_service_matches_account_by_substring(): void
    {
        Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Emergency Fund'],
        ]);
        $other = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Travel Wallet'],
        ]);

        $matched = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Daily Wallet'],
        ]);

        $ids = app(SemanticSearchService::class)->searchAccountIds($this->user, 'Wallet');

        $this->assertContains($matched->id, $ids);
        $this->assertContains($other->id, $ids);
    }

    public function test_search_service_matches_arabic_translation(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Emergency Fund', 'ar' => 'صندوق الطوارئ'],
        ]);

        Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Travel', 'ar' => 'السفر'],
        ]);

        $ids = app(SemanticSearchService::class)->searchAccountIds($this->user, 'الطوارئ');

        $this->assertSame([$account->id], $ids);
    }

    public function test_transaction_search_indexes_note_and_description(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'note' => 'Lunch with team',
            'description' => 'Sushi place downtown',
            'amount' => 25,
        ]);

        $rows = SearchableRecord::query()
            ->where('searchable_type', $transaction->getMorphClass())
            ->where('searchable_id', $transaction->id)
            ->orderBy('field')
            ->get();

        $this->assertSame(['description', 'note'], $rows->pluck('field')->all());

        $ids = app(SemanticSearchService::class)->searchTransactionIds($this->user, 'Sushi');
        $this->assertSame([$transaction->id], $ids);

        $idsByNote = app(SemanticSearchService::class)->searchTransactionIds($this->user, 'Lunch');
        $this->assertSame([$transaction->id], $idsByNote);
    }

    public function test_budget_search_indexes_translated_names(): void
    {
        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Groceries Budget', 'ar' => 'ميزانية البقالة'],
        ]);

        Budget::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Travel Plan'],
        ]);

        $ids = app(SemanticSearchService::class)->searchBudgetIds($this->user, 'Groceries');
        $this->assertSame([$budget->id], $ids);

        $arabicIds = app(SemanticSearchService::class)->searchBudgetIds($this->user, 'البقالة');
        $this->assertSame([$budget->id], $arabicIds);
    }

    public function test_search_service_isolates_results_per_user(): void
    {
        $other = User::factory()->create();

        Account::factory()->create([
            'user_id' => $other->id,
            'name' => ['en' => 'Shared Term Wallet'],
        ]);

        $own = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Shared Term Wallet'],
        ]);

        $ids = app(SemanticSearchService::class)->searchAccountIds($this->user, 'Shared Term');

        $this->assertSame([$own->id], $ids);
    }
}
