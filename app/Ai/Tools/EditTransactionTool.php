<?php

namespace App\Ai\Tools;

use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Domains\Transaction\Services\TransactionService;
use App\Scopes\OwnedAccountScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Laravel\Ai\Tools\Request;
use Stringable;

class EditTransactionTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Edit an existing transaction the user is allowed to modify. Use this to change amount, category, account, note, date, or currency. If the user does not know the transaction ID, list transactions first.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $input = $request->all();
        $this->uppercaseIfPresent($input, ['category_type', 'currency']);

        $this->ensureAnyProvided(
            $input,
            ['amount', 'account_id', 'category_id', 'category_type', 'currency', 'note', 'date'],
            'Provide at least one field to update: amount, account_id, category_id, category_type, currency, note, or date.'
        );

        $validated = $this->validateInput($input, [
            'transaction_id' => ['required', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'category_type' => ['nullable', 'string', Rule::in([
                Category::EXPENSES,
                Category::INCOME,
                Category::SAVINGS,
                Category::INVESTMENT,
            ])],
            'currency' => ['nullable', 'string', 'size:3'],
            'note' => ['nullable', 'string', 'max:1000'],
            'date' => ['nullable', 'date'],
        ]);

        $transaction = Transaction::query()
            ->withoutGlobalScope(OwnedAccountScope::class)
            ->forAccessibleAccounts($user)
            ->with(['account.sharedUsers:id,name,email', 'category'])
            ->find($validated['transaction_id']);

        if (! $transaction) {
            throw new \RuntimeException('The specified transaction was not found or is not accessible.');
        }

        if (! $transaction->account?->canBeEditedBy($user)) {
            throw new \RuntimeException('You do not have permission to edit this transaction.');
        }

        $targetAccount = Arr::exists($validated, 'account_id')
            ? $this->accessibleAccount((int) $validated['account_id'], $user, true)
            : $transaction->account;

        if (! $targetAccount) {
            throw new \RuntimeException('The target account could not be resolved.');
        }

        if ((Arr::exists($validated, 'account_id'))
            && ! Arr::exists($validated, 'category_id')
            && ! Arr::exists($validated, 'category_type')
            && $transaction->category
            && ! in_array((int) $transaction->category->user_id, $targetAccount->participantUserIds(), true)
        ) {
            throw new \RuntimeException('When moving a transaction to an account owned by a different user, provide category_id or category_type.');
        }

        if (Arr::exists($validated, 'category_id') || Arr::exists($validated, 'category_type')) {
            $category = $this->transactionCategory(
                $targetAccount,
                Arr::exists($validated, 'category_id') ? (int) $validated['category_id'] : null,
                $validated['category_type'] ?? null,
            );

            if (! empty($validated['category_type']) && $category->type !== $validated['category_type']) {
                throw new \RuntimeException('The provided category_id does not match the provided category_type.');
            }
        } else {
            $category = $transaction->category;
        }

        if (! $category) {
            throw new \RuntimeException('A valid category is required to update this transaction.');
        }

        $payload = [
            'account_id' => $targetAccount->id,
            'category_id' => $category->id,
            'amount' => Arr::exists($validated, 'amount') ? (float) $validated['amount'] : (float) $transaction->amount,
            'transaction_type' => Transaction::transactionTypeForCategoryType($category->type),
            'currency' => Arr::exists($input, 'currency') ? ($validated['currency'] ?? null) : $transaction->currency,
            'note' => Arr::exists($input, 'note') ? ($validated['note'] ?? null) : $transaction->note,
            'created_at' => Arr::exists($validated, 'date') ? $validated['date'] : $transaction->created_at,
        ];

        $updated = app(TransactionService::class)->update($transaction->id, $payload)->load(['account', 'category']);

        return 'Transaction updated successfully: ' . $this->formatTransaction($updated);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'transaction_id' => $schema->integer()
                ->description('The ID of the transaction to update.')
                ->required(),
            'amount' => $schema->number()
                ->description('Optional new transaction amount.')
                ->nullable(),
            'account_id' => $schema->integer()
                ->description('Optional replacement account ID. The user must be allowed to edit transactions on it.')
                ->nullable(),
            'category_id' => $schema->integer()
                ->description('Optional replacement category ID. It must belong to someone participating in the selected account.')
                ->nullable(),
            'category_type' => $schema->string()
                ->description('Optional replacement category type to use with a fallback category when category_id is not provided.')
                ->enum([Category::EXPENSES, Category::INCOME, Category::SAVINGS, Category::INVESTMENT])
                ->nullable(),
            'currency' => $schema->string()
                ->description('Optional replacement 3-letter currency code. Use null to clear it.')
                ->nullable(),
            'note' => $schema->string()
                ->description('Optional replacement note. Use null to clear it.')
                ->nullable(),
            'date' => $schema->string()
                ->description('Optional replacement date in YYYY-MM-DD format.')
                ->nullable(),
        ];
    }
}
