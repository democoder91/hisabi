<?php

namespace App\Ai\Tools;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Services\TransactionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class CreateTransferTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Create a transfer between two accessible accounts the user can edit. Use this when the user wants to move money from one account to another. The tool creates a debit in the source account and a matching credit in the destination account.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $validated = $this->validateInput($request->all(), [
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

        $date = $validated['date'] ?? now();
        $amount = (float) $validated['amount'];
        $sourceCategory = $this->transactionCategory($fromAccount, null, Category::EXPENSES);
        $destinationCategory = $this->transactionCategory($toAccount, null, Category::INCOME);
        $sourceLabel = $this->accountLabel($fromAccount);
        $destinationLabel = $this->accountLabel($toAccount);

        [$outgoingTransaction, $incomingTransaction] = DB::transaction(function () use (
            $amount,
            $date,
            $validated,
            $fromAccount,
            $toAccount,
            $sourceCategory,
            $destinationCategory,
            $sourceLabel,
            $destinationLabel,
        ): array {
            $transactionService = app(TransactionService::class);

            $outgoingTransaction = $transactionService->create([
                'account_id' => $fromAccount->id,
                'category_id' => $sourceCategory->id,
                'amount' => $amount,
                'note' => $this->transferNote($validated['note'] ?? null, "Transfer to: {$destinationLabel}"),
                'created_at' => $date,
            ])->load(['account', 'category']);

            $incomingTransaction = $transactionService->create([
                'account_id' => $toAccount->id,
                'category_id' => $destinationCategory->id,
                'amount' => $amount,
                'note' => $this->transferNote($validated['note'] ?? null, "Transfer from: {$sourceLabel}"),
                'created_at' => $date,
            ])->load(['account', 'category']);

            return [$outgoingTransaction, $incomingTransaction];
        });

        return "Transfer created successfully:\n"
            . 'Outgoing: ' . $this->formatTransaction($outgoingTransaction) . "\n"
            . 'Incoming: ' . $this->formatTransaction($incomingTransaction);
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
                ->description('Optional note to include on both transfer entries.')
                ->nullable(),
            'date' => $schema->string()
                ->description('Optional transfer date in YYYY-MM-DD format. Defaults to today.')
                ->nullable(),
        ];
    }

    private function transferNote(?string $userNote, string $directionNote): string
    {
        $trimmedUserNote = is_string($userNote) ? trim($userNote) : '';

        return $trimmedUserNote !== ''
            ? "{$trimmedUserNote} | {$directionNote}"
            : $directionNote;
    }

    private function accountLabel(Account $account): string
    {
        return $account->getLocalizedName() ?: "Account #{$account->id}";
    }
}