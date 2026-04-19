<?php

namespace App\Ai\Tools;

use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Domains\Transaction\Services\TransactionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateTransactionTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Create a new financial transaction. Use this when the user wants to record spending, income, savings, or investment activity. You need the amount and either category_id or category_type. account_id is optional and defaults to the user\'s default account.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $input = $request->all();
        $this->uppercaseIfPresent($input, ['category_type', 'currency']);
        $this->normalizeOptionalTextFields($input, ['brand_name', 'note']);

        $validated = $this->validateInput($input, [
            'amount' => ['required', 'numeric', 'min:0'],
            'account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'category_type' => ['nullable', 'string', Rule::in([
                Category::EXPENSES,
                Category::INCOME,
                Category::SAVINGS,
                Category::INVESTMENT,
            ])],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'note' => ['nullable', 'string', 'max:1000'],
            'date' => ['nullable', 'date'],
        ]);

        if (! Arr::exists($validated, 'category_id') && ! Arr::exists($validated, 'category_type')) {
            throw new \RuntimeException('Provide either category_id or category_type to create a transaction.');
        }

        $account = Arr::exists($validated, 'account_id')
            ? $this->accessibleAccount((int) $validated['account_id'], $user, true)
            : $user->getOrCreateDefaultAccount();

        $category = $this->transactionCategory(
            $account,
            Arr::exists($validated, 'category_id') ? (int) $validated['category_id'] : null,
            $validated['category_type'] ?? null,
        );

        $resolvedNote = $validated['note'] ?? null;

        if (! empty($validated['brand_name'])) {
            $resolvedNote = $resolvedNote
                ? $resolvedNote . ' | Merchant: ' . $validated['brand_name']
                : 'Merchant: ' . $validated['brand_name'];
        }

        $transaction = app(TransactionService::class)->create([
            'account_id' => $account->id,
            'amount' => (float) $validated['amount'],
            'category_id' => $category->id,
            'transaction_type' => Transaction::transactionTypeForCategoryType($category->type),
            'currency' => $validated['currency'] ?? $this->defaultCurrency(),
            'note' => $resolvedNote,
            'created_at' => $validated['date'] ?? now(),
        ])->load(['account', 'category', 'fromAccount', 'toAccount']);

        return 'Transaction created successfully: ' . $this->formatTransaction($transaction);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'amount' => $schema->number()
                ->description('The transaction amount (positive number)')
                ->required(),
            'account_id' => $schema->integer()
                ->description('Optional account ID. If omitted, the user\'s default account is used.')
                ->nullable(),
            'category_id' => $schema->integer()
                ->description('Optional category ID. If omitted, provide category_type instead.')
                ->nullable(),
            'brand_name' => $schema->string()
                ->description('The merchant, store, company, or source name. Optional - leave empty if no specific brand.')
                ->nullable(),
            'category_type' => $schema->string()
                ->description('The category type to use when category_id is not provided.')
                ->enum(['EXPENSES', 'INCOME', 'SAVINGS', 'INVESTMENT'])
                ->nullable(),
            'currency' => $schema->string()
                ->description('The 3-letter currency code (e.g. USD, EUR, AED). Optional - defaults to user preferred currency.')
                ->nullable(),
            'note' => $schema->string()
                ->description('Optional note or description for the transaction')
                ->nullable(),
            'date' => $schema->string()
                ->description('The transaction date in YYYY-MM-DD format. Optional - defaults to today.')
                ->nullable(),
        ];
    }
}
