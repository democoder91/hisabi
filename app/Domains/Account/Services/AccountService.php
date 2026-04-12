<?php

namespace App\Domains\Account\Services;

use App\Domains\Account\Models\Account;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class AccountService
{
    public function getPaginated(int $perPage = 50): LengthAwarePaginator
    {
        $query = QueryBuilder::for($this->accessibleQuery())
            ->allowedFilters([
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $search = "%{$value}%";

                    $query->where(function (Builder $builder) use ($search) {
                        $builder->whereRaw(Account::localizedNameSqlExpression('en', false) . ' LIKE ?', [$search])
                            ->orWhereRaw(Account::localizedNameSqlExpression('ar', false) . ' LIKE ?', [$search]);
                    });
                }),
            ])
            ->allowedSorts([
                'id',
                'balance',
                'created_at',
                AllowedSort::callback('name', function (Builder $query, bool $descending) {
                    $query->orderByRaw($this->localizedNameSortSql($descending));
                }),
            ])
            ->with(['sharedUsers:id,name,email'])
            ->withCount('transactions');

        if (! request()->filled('sort')) {
            $query->orderByRaw($this->localizedNameSortSql());
        }

        return $query->paginate($perPage);
    }

    public function getAll(): Collection
    {
        $query = QueryBuilder::for($this->accessibleQuery())
            ->allowedSorts([
                'id',
                'balance',
                'created_at',
                AllowedSort::callback('name', function (Builder $query, bool $descending) {
                    $query->orderByRaw($this->localizedNameSortSql($descending));
                }),
            ])
            ->with(['sharedUsers:id,name,email'])
            ->withCount('transactions');

        if (! request()->filled('sort')) {
            $query->orderByRaw($this->localizedNameSortSql());
        }

        return $query->get();
    }

    public function create(array $data): Account
    {
        return Account::query()->create([
            ...$data,
            'user_id' => $data['user_id'] ?? auth()->id(),
        ]);
    }

    public function update(Account $account, array $data): Account
    {
        $account->update($data);

        return $account->fresh()->load(['sharedUsers:id,name,email'])->loadCount('transactions');
    }

    public function delete(Account $account): Account
    {
        $account->loadCount('transactions');
        $account->delete();

        return $account;
    }

    public function invite(Account $account, array $data): Account
    {
        $shareUser = User::query()->where('email', $data['email'])->firstOrFail();

        $account->sharedUsers()->syncWithoutDetaching([
            $shareUser->id => ['permission_level' => $data['permission_level']],
        ]);

        return $account->fresh()->load(['sharedUsers:id,name,email'])->loadCount('transactions');
    }

    public function updateSharePermission(Account $account, int $shareUserId, string $permissionLevel): Account
    {
        $account->sharedUsers()->where('users.id', $shareUserId)->firstOrFail();

        $account->sharedUsers()->updateExistingPivot($shareUserId, [
            'permission_level' => $permissionLevel,
        ]);

        return $account->fresh()->load(['sharedUsers:id,name,email'])->loadCount('transactions');
    }

    public function revokeShare(Account $account, int $shareUserId): Account
    {
        $account->sharedUsers()->where('users.id', $shareUserId)->firstOrFail();
        $account->sharedUsers()->detach($shareUserId);

        return $account->fresh()->load(['sharedUsers:id,name,email'])->loadCount('transactions');
    }

    public function findAccessibleOrFail(int $id): Account
    {
        return $this->accessibleQuery()
            ->with(['sharedUsers:id,name,email'])
            ->withCount('transactions')
            ->findOrFail($id);
    }

    private function accessibleQuery(): Builder
    {
        return Account::query()->accessibleTo(auth()->user());
    }

    private function localizedNameSortSql(bool $descending = false): string
    {
        $direction = $descending ? 'DESC' : 'ASC';
        $locale = app()->getLocale();

        return Account::localizedNameSqlExpression($locale) . ' ' . $direction;
    }
}