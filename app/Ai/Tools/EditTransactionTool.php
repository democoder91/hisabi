<?php

namespace App\Ai\Tools;

use App\Domains\Transaction\Models\Transaction;
use App\Domains\Transaction\Services\TransactionService;
use App\Scopes\OwnedAccountScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class EditTransactionTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Edit an existing transaction the user is allowed to modify. Use this to change amount, source account, destination account, note, or date. Transaction currency follows the source account. If the user does not know the transaction ID, list transactions first.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $input = $request->all();
        $this->normalizeOptionalTextFields($input, ['note']);

        $this->ensureAnyProvided(
            $input,
            ['amount', 'from_account_id', 'to_account_id', 'note', 'date'],
            'Provide at least one field to update: amount, from_account_id, to_account_id, note, or date.'
        );

        $validated = $this->validateInput($input, [
            'transaction_id' => ['required', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'from_account_id' => ['nullable', 'integer', 'different:to_account_id'],
            'to_account_id' => ['nullable', 'integer', 'different:from_account_id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'date' => ['nullable', 'date'],
        ]);

        $transaction = Transaction::query()
            ->withoutGlobalScope(OwnedAccountScope::class)
            ->forAccessibleAccounts($user)
            ->with(['account.sharedUsers:id,name,email', 'fromAccount', 'toAccount'])
            ->find($validated['transaction_id']);

        if (! $transaction) {
            throw new \RuntimeException('The specified transaction was not found or is not accessible.');
        }

        if (! $transaction->account?->canBeEditedBy($user)) {
            throw new RuntimeException('You do not have permission to edit this transaction.');
        }

        $fromAccount = Arr::exists($validated, 'from_account_id')
            ? $this->accessibleAccount((int) $validated['from_account_id'], $user, true)
            : $transaction->fromAccount;
        $toAccount = Arr::exists($validated, 'to_account_id')
            ? $this->accessibleAccount((int) $validated['to_account_id'], $user, true)
            : $transaction->toAccount;

        if (! $fromAccount || ! $toAccount) {
            throw new RuntimeException('The source and destination accounts could not be resolved.');
        }

        if ((int) $fromAccount->id === (int) $toAccount->id) {
            throw new RuntimeException('The source and destination accounts must be different.');
        }

        $payload = [
            'amount' => Arr::exists($validated, 'amount') ? (float) $validated['amount'] : (float) $transaction->amount,
            'note' => Arr::exists($input, 'note') ? ($validated['note'] ?? null) : $transaction->note,
            'created_at' => Arr::exists($validated, 'date') ? $validated['date'] : $transaction->created_at,
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
        ];

        $updated = app(TransactionService::class)->update($transaction->id, $payload)->load(['account', 'fromAccount', 'toAccount']);

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
            'from_account_id' => $schema->integer()
                ->description('Optional replacement source account ID. The user must be allowed to edit transactions on it.')
                ->nullable(),
            'to_account_id' => $schema->integer()
                ->description('Optional replacement destination account ID. The user must be allowed to edit transactions on it.')
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
