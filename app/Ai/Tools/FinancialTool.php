<?php

namespace App\Ai\Tools;

use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Contracts\Tool;
use RuntimeException;

abstract class FinancialTool implements Tool
{
    protected function authenticatedUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new RuntimeException('An authenticated user is required to use this tool.');
        }

        return $user;
    }

    protected function validateInput(array $input, array $rules, array $messages = []): array
    {
        $validator = Validator::make($input, $rules, $messages);

        if ($validator->fails()) {
            throw new RuntimeException($validator->errors()->first());
        }

        return $validator->validated();
    }

    protected function ensureAnyProvided(array $input, array $keys, string $message): void
    {
        foreach ($keys as $key) {
            if (Arr::exists($input, $key)) {
                return;
            }
        }

        throw new RuntimeException($message);
    }

    protected function uppercaseIfPresent(array &$input, array $keys): void
    {
        foreach ($keys as $key) {
            if (Arr::exists($input, $key) && is_string($input[$key])) {
                $input[$key] = strtoupper(trim($input[$key]));
            }
        }
    }

    protected function normalizeLimit(mixed $value, int $default = 10, int $max = 25): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return max(1, min((int) $value, $max));
    }

    protected function normalizeNameTranslations(array $input, array $existing = []): ?array
    {
        if (! Arr::exists($input, 'name_en') && ! Arr::exists($input, 'name_ar')) {
            return null;
        }

        return [
            'en' => Arr::exists($input, 'name_en') ? $input['name_en'] : ($existing['en'] ?? null),
            'ar' => Arr::exists($input, 'name_ar') ? $input['name_ar'] : ($existing['ar'] ?? null),
        ];
    }

    protected function applyLocalizedSearch(Builder $query, string $column, string $search): void
    {
        $term = trim($search);

        if ($term === '') {
            return;
        }

        $like = "%{$term}%";
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $localeExpression = sprintf(
                "CASE WHEN json_valid(%s) THEN json_extract(%s, '$.\"%s\"') END",
                $column,
                $column,
                app()->getLocale(),
            );
            $englishExpression = sprintf(
                "CASE WHEN json_valid(%s) THEN json_extract(%s, '$.en') END",
                $column,
                $column,
            );
            $plainExpression = sprintf("CASE WHEN NOT json_valid(%s) THEN %s END", $column, $column);
        } else {
            $localeExpression = sprintf(
                'CASE WHEN JSON_VALID(%s) THEN JSON_UNQUOTE(JSON_EXTRACT(%s, "$.%s")) END',
                $column,
                $column,
                app()->getLocale(),
            );
            $englishExpression = sprintf(
                'CASE WHEN JSON_VALID(%s) THEN JSON_UNQUOTE(JSON_EXTRACT(%s, "$.en")) END',
                $column,
                $column,
            );
            $plainExpression = sprintf('CASE WHEN NOT JSON_VALID(%s) THEN %s END', $column, $column);
        }

        $query->where(function (Builder $builder) use ($like, $localeExpression, $englishExpression, $plainExpression) {
            $builder->whereRaw("{$localeExpression} LIKE ?", [$like])
                ->orWhereRaw("{$englishExpression} LIKE ?", [$like])
                ->orWhereRaw("{$plainExpression} LIKE ?", [$like]);
        });
    }

    protected function defaultCurrency(): string
    {
        $user = Auth::user();

        if ($user instanceof User && $user->default_currency) {
            return $user->default_currency;
        }

        return config('hisabi.currency', 'AED');
    }

    protected function ownedCategoryIds(array $categoryIds, User $user): array
    {
        $normalized = array_values(array_unique(array_map(static fn($id) => (int) $id, $categoryIds)));

        $count = Category::query()->whereIn('id', $normalized)->count();

        if ($count !== count($normalized)) {
            throw new RuntimeException('One or more category_ids are invalid for the authenticated user.');
        }

        return $normalized;
    }

    protected function accessibleAccount(int $accountId, User $user, bool $requireEditable = false): Account
    {
        $account = Account::query()
            ->accessibleTo($user)
            ->with(['sharedUsers:id,name,email'])
            ->withCount('transactions')
            ->find($accountId);

        if (! $account) {
            throw new RuntimeException('The specified account was not found or is not accessible.');
        }

        if ($requireEditable && ! $account->canBeEditedBy($user)) {
            throw new RuntimeException('You do not have permission to modify transactions for the specified account.');
        }

        return $account;
    }

    protected function transactionCategory(Account $account, ?int $categoryId, ?string $categoryType): Category
    {
        if ($categoryId) {
            $category = Category::query()
                ->withoutGlobalScope(TenantScope::class)
                ->find($categoryId);

            if (! $category || ! in_array((int) $category->user_id, $account->participantUserIds(), true)) {
                throw new RuntimeException('The selected category is invalid for the chosen account.');
            }

            return $category;
        }

        if (! $categoryType) {
            throw new RuntimeException('Provide either category_id or category_type.');
        }

        $authenticatedUser = $this->authenticatedUser();
        $fallbackOwnerId = in_array((int) $authenticatedUser->id, $account->participantUserIds(), true)
            ? (int) $authenticatedUser->id
            : (int) $account->user_id;

        return Category::findOrCreateFallbackForUser($fallbackOwnerId, $categoryType);
    }

    protected function formatAmount(float|int $amount, ?string $currency = null): string
    {
        $formatted = number_format((float) $amount, 2, '.', '');

        return $currency ? "{$currency} {$formatted}" : $formatted;
    }

    protected function formatAccount(Account $account): string
    {
        $user = Auth::user();
        $permission = $account->isOwnedBy($user) ? 'owner' : ($account->permissionLevelFor($user) ?? 'view');

        return sprintf(
            '#%d %s | balance %s | transactions %d | permission %s',
            $account->id,
            $account->getLocalizedName() ?? 'Unnamed account',
            $this->formatAmount($account->balance),
            (int) ($account->transactions_count ?? 0),
            $permission,
        );
    }

    protected function formatCategory(Category $category): string
    {
        $name = $category->getTranslation('name', app()->getLocale(), false)
            ?: $category->getTranslation('name', 'en', false)
            ?: 'Unnamed category';

        return sprintf(
            '#%d %s | type %s | color %s | icon %s | transactions %d',
            $category->id,
            $name,
            $category->type,
            $category->color,
            $category->icon,
            (int) ($category->transactions_count ?? 0),
        );
    }

    protected function formatBudget(Budget $budget): string
    {
        $name = $budget->getLocalizedName() ?? 'Unnamed budget';
        $categoryNames = $budget->relationLoaded('categories')
            ? $budget->categories
            ->map(fn(Category $category) => $category->getTranslation('name', app()->getLocale(), false)
                ?: $category->getTranslation('name', 'en', false)
                ?: 'Unnamed category')
            ->join(', ')
            : '';

        $parts = [
            sprintf('#%d %s', $budget->id, $name),
            'amount ' . $this->formatAmount($budget->amount),
            'reoccurrence ' . $budget->reoccurrence,
            'period ' . $budget->period,
            'saving ' . ($budget->saving ? 'yes' : 'no'),
            'window ' . ($budget->start_at?->format('Y-m-d') ?? 'n/a') . ' -> ' . ($budget->end_at?->format('Y-m-d') ?? 'n/a'),
        ];

        if ($categoryNames !== '') {
            $parts[] = 'categories ' . $categoryNames;
        }

        return implode(' | ', $parts);
    }

    protected function formatTransaction(Transaction $transaction): string
    {
        $categoryName = $transaction->category?->getTranslation('name', app()->getLocale(), false)
            ?: $transaction->category?->getTranslation('name', 'en', false)
            ?: 'Uncategorized';
        $accountName = $transaction->account?->getLocalizedName() ?? 'Unknown account';

        $parts = [
            sprintf('#%d %s', $transaction->id, $transaction->created_at?->format('Y-m-d') ?? 'n/a'),
            $transaction->transaction_type,
            $this->formatAmount($transaction->amount, $transaction->currency ?: $this->defaultCurrency()),
            'account ' . $accountName,
            'category ' . $categoryName,
        ];

        if ($transaction->category?->type) {
            $parts[] = 'category_type ' . $transaction->category->type;
        }

        if ($transaction->note) {
            $parts[] = 'note ' . $transaction->note;
        }

        return implode(' | ', $parts);
    }
}
