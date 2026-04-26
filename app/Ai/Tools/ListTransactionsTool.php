<?php

namespace App\Ai\Tools;

use App\Domains\Search\Services\SemanticSearchService;
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
        $this->uppercaseIfPresent($input, ['transaction_type']);

        $validated = $this->validateInput($input, [
            'transaction_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'from_account_id' => ['nullable', 'integer'],
            'to_account_id' => ['nullable', 'integer'],
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
            ->with(['account.sharedUsers:id,name,email', 'fromAccount', 'toAccount']);

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

        if (! empty($validated['from_account_id'])) {
            $fromAccount = $this->accessibleAccount((int) $validated['from_account_id'], $user);
            $query->where('from_account_id', $fromAccount->id);
        }

        if (! empty($validated['to_account_id'])) {
            $toAccount = $this->accessibleAccount((int) $validated['to_account_id'], $user);
            $query->where('to_account_id', $toAccount->id);
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
            $rawSearch = trim((string) $validated['search']);
            $like = '%' . $rawSearch . '%';
            $semantic = app(SemanticSearchService::class);

            $transactionIds = $semantic->searchTransactionIds($user, $rawSearch, 200);
            $accountIds = $semantic->searchAccountIds($user, $rawSearch, 50);

            $query->where(function ($builder) use ($like, $rawSearch, $transactionIds, $accountIds) {
                if ($transactionIds !== []) {
                    $builder->orWhereIn('id', $transactionIds);
                } else {
                    $builder->orWhere('note', 'like', $like)
                        ->orWhere('description', 'like', $like);
                }

                $builder->orWhere('amount', 'like', $like);

                if ($accountIds !== []) {
                    $builder->orWhereIn('from_account_id', $accountIds)
                        ->orWhereIn('to_account_id', $accountIds)
                        ->orWhereIn('account_id', $accountIds);
                } else {
                    $builder->orWhereHas('fromAccount', function ($accountQuery) use ($rawSearch) {
                        $this->applyLocalizedSearch($accountQuery, 'name', $rawSearch);
                    })
                    ->orWhereHas('toAccount', function ($accountQuery) use ($rawSearch) {
                        $this->applyLocalizedSearch($accountQuery, 'name', $rawSearch);
                    });
                }
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
                ->required()
                ->nullable(),
            'account_id' => $schema->integer()
                ->description('Optional filter for any transaction involving this account.')
                ->required()
                ->nullable(),
            'from_account_id' => $schema->integer()
                ->description('Optional source account ID filter.')
                ->required()
                ->nullable(),
            'to_account_id' => $schema->integer()
                ->description('Optional destination account ID filter.')
                ->required()
                ->nullable(),
            'transaction_type' => $schema->string()
                ->description('Optional transaction direction filter.')
                ->enum([Transaction::TYPE_DEBIT, Transaction::TYPE_CREDIT])
                ->required()
                ->nullable(),
            'date_from' => $schema->string()
                ->description('Optional start date filter in YYYY-MM-DD format.')
                ->required()
                ->nullable(),
            'date_to' => $schema->string()
                ->description('Optional end date filter in YYYY-MM-DD format.')
                ->required()
                ->nullable(),
            'search' => $schema->string()
                ->description('Optional search term that matches the note or amount.')
                ->required()
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Maximum number of transactions to return. Defaults to 10, max 25.')
                ->required()
                ->nullable(),
        ];
    }
}
