<?php

namespace App\Ai\Tools;

use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Scopes\OwnedAccountScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListTransactionsTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'List transactions accessible to the authenticated user. Use this before editing a transaction or when the user asks to review recent spending, income, savings, or investments.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $input = $request->all();
        $this->uppercaseIfPresent($input, ['category_type', 'transaction_type']);

        $validated = $this->validateInput($input, [
            'transaction_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'category_type' => ['nullable', 'string', Rule::in([
                Category::EXPENSES,
                Category::INCOME,
                Category::SAVINGS,
                Category::INVESTMENT,
            ])],
            'transaction_type' => ['nullable', 'string', Rule::in([
                Transaction::TYPE_DEBIT,
                Transaction::TYPE_CREDIT,
            ])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $query = Transaction::query()
            ->withoutGlobalScope(OwnedAccountScope::class)
            ->forAccessibleAccounts($user)
            ->with(['account.sharedUsers:id,name,email', 'category', 'fromAccount', 'toAccount']);

        if (! empty($validated['transaction_id'])) {
            $query->whereKey($validated['transaction_id']);
        }

        if (! empty($validated['account_id'])) {
            $account = $this->accessibleAccount((int) $validated['account_id'], $user);
            $query->where(function ($builder) use ($account) {
                $builder->where('account_id', $account->id)
                    ->orWhere('from_account_id', $account->id)
                    ->orWhere('to_account_id', $account->id);
            });
        }

        if (! empty($validated['category_id'])) {
            $query->where('category_id', (int) $validated['category_id']);
        }

        if (! empty($validated['category_type'])) {
            $query->whereHas('category', fn($builder) => $builder->where('type', $validated['category_type']));
        }

        if (! empty($validated['transaction_type'])) {
            $query->where('transaction_type', $validated['transaction_type']);
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if (! empty($validated['search'])) {
            $search = '%' . trim($validated['search']) . '%';
            $plainSearch = trim($validated['search']);

            $query->where(function ($builder) use ($search, $plainSearch) {
                $builder->where('note', 'like', $search)
                    ->orWhere('amount', 'like', $search)
                    ->orWhereHas('fromAccount', function ($accountQuery) use ($plainSearch) {
                        $this->applyLocalizedSearch($accountQuery, 'name', $plainSearch);
                    })
                    ->orWhereHas('toAccount', function ($accountQuery) use ($plainSearch) {
                        $this->applyLocalizedSearch($accountQuery, 'name', $plainSearch);
                    });
            });
        }

        $transactions = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($this->normalizeLimit($validated['limit'] ?? null))
            ->get();

        if ($transactions->isEmpty()) {
            return 'No transactions found for the current filters.';
        }

        return "Transactions:\n" . $transactions->map(fn(Transaction $transaction) => $this->formatTransaction($transaction))->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'transaction_id' => $schema->integer()
                ->description('Optional exact transaction ID to retrieve.')
                ->nullable(),
            'account_id' => $schema->integer()
                ->description('Optional account ID filter.')
                ->nullable(),
            'category_id' => $schema->integer()
                ->description('Optional category ID filter.')
                ->nullable(),
            'category_type' => $schema->string()
                ->description('Optional category type filter.')
                ->enum([Category::EXPENSES, Category::INCOME, Category::SAVINGS, Category::INVESTMENT])
                ->nullable(),
            'transaction_type' => $schema->string()
                ->description('Optional transaction direction filter.')
                ->enum([Transaction::TYPE_DEBIT, Transaction::TYPE_CREDIT])
                ->nullable(),
            'date_from' => $schema->string()
                ->description('Optional start date filter in YYYY-MM-DD format.')
                ->nullable(),
            'date_to' => $schema->string()
                ->description('Optional end date filter in YYYY-MM-DD format.')
                ->nullable(),
            'search' => $schema->string()
                ->description('Optional search term that matches the note or amount.')
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Maximum number of transactions to return. Defaults to 10, max 25.')
                ->nullable(),
        ];
    }
}
