<?php

namespace App\Ai\Tools;

use App\Domains\Transaction\Services\TransactionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class CreateTransactionTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Create a new financial transaction between a source account and a destination account. Use this when the user wants to record spending, income, savings, or investment activity as a single ledger entry. Prefer create_transfer for simple internal moves between editable accounts that already share a currency.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $input = $request->all();
        $this->normalizeOptionalTextFields($input, ['brand_name', 'note']);

        $validated = $this->validateInput($input, [
            'amount' => ['required', 'numeric', 'min:0'],
            'from_account_id' => ['required', 'integer', 'different:to_account_id'],
            'to_account_id' => ['required', 'integer'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'date' => ['nullable', 'date'],
        ]);

        $fromAccount = $this->accessibleAccount((int) $validated['from_account_id'], $user, true);
        $toAccount = $this->accessibleAccount((int) $validated['to_account_id'], $user, true);

        if ((int) $fromAccount->id === (int) $toAccount->id) {
            throw new RuntimeException('The source and destination accounts must be different.');
        }

        $resolvedNote = $validated['note'] ?? null;

        if (! empty($validated['brand_name'])) {
            $resolvedNote = $resolvedNote
                ? $resolvedNote . ' | Merchant: ' . $validated['brand_name']
                : 'Merchant: ' . $validated['brand_name'];
        }

        $transaction = app(TransactionService::class)->create([
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => (float) $validated['amount'],
            'note' => $resolvedNote,
            'created_at' => $validated['date'] ?? now(),
        ])->load(['account', 'fromAccount', 'toAccount']);

        return 'Transaction created successfully: ' . $this->formatTransaction($transaction);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'amount' => $schema->number()
                ->description('The transaction amount (positive number)')
                ->required(),
            'from_account_id' => $schema->integer()
                ->description('The source account ID for this transaction. The user must be able to edit it.')
                ->required(),
            'to_account_id' => $schema->integer()
                ->description('The destination account ID for this transaction. The user must be able to edit it.')
                ->required(),
            'brand_name' => $schema->string()
                ->description('The merchant, store, company, or source name. Optional - leave empty if no specific brand.')
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
