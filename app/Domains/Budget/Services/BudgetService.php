<?php

namespace App\Domains\Budget\Services;

use App\Domains\Budget\Models\Budget;
use App\Domains\Budget\Models\BudgetAccount;
use App\Domains\Search\Services\SemanticSearchService;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BudgetService
{
    public function getAll(): Collection
    {
        return QueryBuilder::for(Budget::class)
            ->with($this->relations())
            ->allowedFilters([
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $term = trim((string) $value);

                    if ($term === '') {
                        return;
                    }

                    $user = Auth::user();
                    $matchedIds = $user
                        ? app(SemanticSearchService::class)->searchBudgetIds($user, $term, 200)
                        : [];

                    if ($matchedIds !== []) {
                        $query->whereIn('id', $matchedIds);

                        return;
                    }

                    $like = "%{$term}%";

                    $query->where(function (Builder $builder) use ($like) {
                        $builder->where('name', 'LIKE', $like);
                    });
                }),
                AllowedFilter::exact('saving'),
                AllowedFilter::exact('reoccurrence'),
            ])
            ->allowedSorts(['id', 'amount', 'start_at'])
            ->get();
    }

    public function findOwnedOrFail(int $id): Budget
    {
        return Budget::with($this->relations())->findOrFail($id);
    }

    public function create(array $data): Budget
    {
        $budget = Budget::create([
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'amount' => $data['amount'],
            'currency' => $this->resolveCurrency($data['currency'] ?? null),
            'start_at' => $data['start_at'],
            'end_at' => $data['reoccurrence'] === Budget::CUSTOM ? $data['end_at'] : ($data['end_at'] ?? null),
            'saving' => $data['saving'] ?? false,
            'period' => $data['period'],
            'reoccurrence' => $data['reoccurrence'],
        ]);

        $this->syncAccounts($budget, $data['account_ids']);

        return $budget->load($this->relations());
    }

    public function update(Budget $budget, array $data): Budget
    {
        $budget->update([
            'name' => $data['name'],
            'amount' => $data['amount'],
            'currency' => $this->resolveCurrency($data['currency'] ?? $budget->currency),
            'start_at' => $data['start_at'],
            'end_at' => $data['reoccurrence'] === Budget::CUSTOM ? $data['end_at'] : ($data['end_at'] ?? null),
            'saving' => $data['saving'] ?? false,
            'period' => $data['period'],
            'reoccurrence' => $data['reoccurrence'],
        ]);

        $this->syncAccounts($budget, $data['account_ids']);

        return $budget->load($this->relations());
    }

    public function delete(Budget $budget): Budget
    {
        $budget->loadMissing($this->relations());
        $budget->delete();

        return $budget;
    }

    private function syncAccounts(Budget $budget, array $accountIds): void
    {
        $normalizedAccountIds = collect($accountIds)
            ->map(fn($accountId) => (int) $accountId)
            ->unique()
            ->values();

        $detachQuery = BudgetAccount::query()->where('budget_id', $budget->id);

        if ($normalizedAccountIds->isNotEmpty()) {
            $detachQuery->whereNotIn('account_id', $normalizedAccountIds->all());
        }

        $detachQuery->delete();

        $existingLinks = BudgetAccount::withTrashed()
            ->where('budget_id', $budget->id)
            ->whereIn('account_id', $normalizedAccountIds->all())
            ->get()
            ->keyBy('account_id');

        foreach ($normalizedAccountIds as $accountId) {
            $existingLink = $existingLinks->get($accountId);

            if ($existingLink) {
                if ($existingLink->trashed()) {
                    $existingLink->restore();
                }

                continue;
            }

            BudgetAccount::create([
                'budget_id' => $budget->id,
                'account_id' => $accountId,
            ]);
        }
    }

    private function relations(): array
    {
        return ['accounts.user:id,name', 'accounts.sharedUsers:id,name,email'];
    }

    private function resolveCurrency(?string $currency): string
    {
        if (is_string($currency) && $currency !== '') {
            return strtoupper($currency);
        }

        $user = Auth::user();

        if ($user instanceof User && $user->default_currency) {
            return strtoupper($user->default_currency);
        }

        return strtoupper((string) config('hisabi.currency', 'EGP'));
    }
}
