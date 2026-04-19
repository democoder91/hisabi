<?php

namespace App\Ai\Tools;

use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Contracts\Tool;
use RuntimeException;
use Stringable;

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

    protected function normalizeOptionalTextFields(array &$input, array $keys): void
    {
        foreach ($keys as $key) {
            if (! Arr::exists($input, $key)) {
                continue;
            }

            $input[$key] = $this->normalizeOptionalTextValue($input[$key]);
        }
    }

    protected function normalizeOptionalTextValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value instanceof Stringable) {
            $trimmed = trim((string) $value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_object($value)) {
            $value = (array) $value;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach (['note', 'text', 'description', 'content', 'message', 'value', 'label', 'name', 'title'] as $preferredKey) {
            if (! Arr::exists($value, $preferredKey)) {
                continue;
            }

            $normalized = $this->normalizeOptionalTextValue($value[$preferredKey]);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        $fragments = [];

        array_walk_recursive($value, function (mixed $item) use (&$fragments): void {
            if (is_string($item)) {
                $trimmed = trim($item);

                if ($trimmed !== '') {
                    $fragments[] = $trimmed;
                }

                return;
            }

            if (is_int($item) || is_float($item) || is_bool($item) || $item instanceof Stringable) {
                $fragments[] = (string) $item;
            }
        });

        if ($fragments !== []) {
            return implode(' | ', array_values(array_unique($fragments)));
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : $encoded;
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

    protected function accessibleAccountIds(array $accountIds, User $user, bool $requireEditable = false): array
    {
        $normalized = array_values(array_unique(array_map(static fn($id) => (int) $id, $accountIds)));

        return collect($normalized)
            ->map(fn(int $accountId) => $this->accessibleAccount($accountId, $user, $requireEditable))
            ->pluck('id')
            ->map(fn(mixed $id) => (int) $id)
            ->all();
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
    protected function formatBudget(Budget $budget): string
    {
        $name = $budget->getLocalizedName() ?? 'Unnamed budget';
        $accountNames = $budget->relationLoaded('accounts')
            ? $budget->accounts
                ->map(fn(Account $account) => $account->getLocalizedName() ?: 'Unnamed account')
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

        if ($accountNames !== '') {
            $parts[] = 'accounts ' . $accountNames;
        }

        return implode(' | ', $parts);
    }

    protected function formatTransaction(Transaction $transaction): string
    {
        $accountName = $transaction->account?->getLocalizedName() ?? 'Unknown account';
        $fromAccountName = $transaction->fromAccount?->getLocalizedName();
        $toAccountName = $transaction->toAccount?->getLocalizedName();

        $parts = [
            sprintf('#%d %s', $transaction->id, $transaction->created_at?->format('Y-m-d') ?? 'n/a'),
            $transaction->transaction_type,
            $this->formatAmount($transaction->amount, $transaction->currency ?: $this->defaultCurrency()),
        ];

        if ($fromAccountName && $toAccountName) {
            $parts[] = 'source ' . $fromAccountName;
            $parts[] = 'destination ' . $toAccountName;
            $parts[] = 'primary_account ' . $accountName;
        } else {
            $parts[] = 'account ' . $accountName;
        }

        if ($transaction->note) {
            $parts[] = 'note ' . $transaction->note;
        }

        return implode(' | ', $parts);
    }
}
