<?php

namespace App\Ai\Tools;

use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Services\TransactionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class CreateTransferTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Create a transfer between two accessible accounts the user can edit. Use this when the user wants to move money from one account to another. The tool creates one real ledger transfer between the source and destination accounts.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $input = $request->all();
        $this->normalizeOptionalTextFields($input, ['note']);

        $validated = $this->validateInput($input, [
            'amount' => ['required', 'numeric', 'min:0'],
            'from_account_id' => ['required', 'integer', 'different:to_account_id'],
            'to_account_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
            'date' => ['nullable', 'date'],
        ]);

        $fromAccount = $this->accessibleAccount((int) $validated['from_account_id'], $user, true);
        $toAccount = $this->accessibleAccount((int) $validated['to_account_id'], $user, true);

        if ((int) $fromAccount->id === (int) $toAccount->id) {
            throw new RuntimeException('The source and destination accounts must be different.');
        }

        if ($fromAccount->currency !== $toAccount->currency) {
            throw new RuntimeException('Transfers between accounts with different currencies are not supported yet.');
        }

        $transaction = app(TransactionService::class)->createTransfer([
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => (float) $validated['amount'],
            'note' => $validated['note'] ?? null,
            'created_at' => $validated['date'] ?? now(),
            'currency' => $fromAccount->currency,
        ])->load(['account', 'category', 'fromAccount', 'toAccount']);

        return 'Transfer created successfully: ' . $this->formatTransaction($transaction);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'amount' => $schema->number()
                ->description('The amount to transfer between the two accounts.')
                ->required(),
            'from_account_id' => $schema->integer()
                ->description('The source account ID to debit. The user must be allowed to edit transactions on it.')
                ->required(),
            'to_account_id' => $schema->integer()
                ->description('The destination account ID to credit. The user must be allowed to edit transactions on it.')
                ->required(),
            'note' => $schema->string()
                ->description('Optional note to include on the transfer entry.')
                ->nullable(),
            'date' => $schema->string()
                ->description('Optional transfer date in YYYY-MM-DD format. Defaults to today.')
                ->nullable(),
        ];
    }

    private function accountLabel(Account $account): string
    {
        return $account->getLocalizedName() ?: "Account #{$account->id}";
    }
}
