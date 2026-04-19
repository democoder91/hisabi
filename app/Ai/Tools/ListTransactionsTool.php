<?php

namespace App\Ai\Tools;

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
                ->description('Optional filter for any transaction involving this account.')
                ->nullable(),
            'from_account_id' => $schema->integer()
                ->description('Optional source account ID filter.')
                ->nullable(),
            'to_account_id' => $schema->integer()
                ->description('Optional destination account ID filter.')
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
