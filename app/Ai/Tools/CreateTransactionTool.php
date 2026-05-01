<?php

namespace App\Ai\Tools;

use App\Domains\Transaction\Services\TransactionService;
use App\Models\UploadedFile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class CreateTransactionTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Create a new financial transaction between a source account and a destination account. Use this when the user wants to record spending, income, savings, or investment activity as a single ledger entry. Destination account IDs may come from choose_transaction_accounts, but the source account must still be collected or resolved separately. Prefer create_transfer for simple internal moves between editable accounts that already share a currency.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $input = $request->all();
        $this->normalizeOptionalTextFields($input, ['brand_name', 'note']);

        try {
            $this->normalizeAccountReferenceInputs($input, $user, ['from_account_id', 'to_account_id'], true);

            $validated = $this->validateInput($input, [
                'amount' => ['required', 'numeric', 'min:0'],
                'from_account_id' => ['required', 'integer', 'different:to_account_id'],
                'to_account_id' => ['required', 'integer'],
                'brand_name' => ['nullable', 'string', 'max:255'],
                'note' => ['nullable', 'string', 'max:1000'],
                'date' => ['nullable', 'date'],
                'receipt' => ['nullable', 'array'],
                'receipt.upload_ids' => ['nullable', 'array'],
                'receipt.upload_ids.*' => ['integer'],
                'receipt.tax_amount' => ['nullable', 'numeric', 'min:0'],
                'receipt.total_amount' => ['nullable', 'numeric', 'min:0'],
                'receipt.merchant' => ['nullable', 'string', 'max:255'],
                'receipt.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
                'receipt.document_type' => ['nullable', 'string', 'max:50'],
            ]);

            $fromAccount = $this->accessibleAccount((int) $validated['from_account_id'], $user, true);
            $toAccount = $this->accessibleAccount((int) $validated['to_account_id'], $user, true);

            if ((int) $fromAccount->id === (int) $toAccount->id) {
                throw new RuntimeException('The source and destination accounts must be different.');
            }
        } catch (RuntimeException $exception) {
            return $this->recoverableToolFailure(
                'create the transaction',
                $exception,
                'Use list_accounts to resolve explicit account IDs, use choose_transaction_accounts for memo-based destination inference, or ask the user for the missing account details with ask_user_for_input before retrying create_transaction.',
            );
        }

        $receiptMeta = $this->normalizeReceiptMeta($validated['receipt'] ?? null, $user);

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
            'meta' => $receiptMeta !== null ? ['receipt' => $receiptMeta] : null,
            'created_at' => $validated['date'] ?? now(),
        ])->load(['account', 'fromAccount', 'toAccount']);

        if ($receiptMeta !== null && ($receiptMeta['upload_ids'] ?? []) !== []) {
            UploadedFile::query()
                ->whereIn('id', $receiptMeta['upload_ids'])
                ->where('user_id', $user->id)
                ->get()
                ->each(function (UploadedFile $upload) use ($receiptMeta, $transaction): void {
                    $upload->update([
                        'custom_attributes' => array_merge($upload->custom_attributes ?? [], [
                            'linked_transaction_id' => $transaction->id,
                            'receipt' => $receiptMeta,
                        ]),
                    ]);
                });
        }

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
                ->required()
                ->nullable(),
            'note' => $schema->string()
                ->description('Optional note or description for the transaction')
                ->required()
                ->nullable(),
            'date' => $schema->string()
                ->description('The transaction date in YYYY-MM-DD format. Optional - defaults to today.')
                ->required()
                ->nullable(),
            'receipt' => $schema->object([
                'upload_ids' => $schema->array()
                    ->items($schema->integer()->description('An uploaded receipt or bill file ID related to this transaction.'))
                    ->required()
                    ->nullable(),
                'tax_amount' => $schema->number()
                    ->description('The visible tax or VAT amount on the receipt, if present.')
                    ->required()
                    ->nullable(),
                'total_amount' => $schema->number()
                    ->description('The extracted total amount shown on the receipt, if different from the transaction amount.')
                    ->required()
                    ->nullable(),
                'merchant' => $schema->string()
                    ->description('The merchant or payee name extracted from the receipt, if available.')
                    ->required()
                    ->nullable(),
                'confidence' => $schema->number()
                    ->description('A confidence score from 0 to 1 for the extracted receipt data.')
                    ->required()
                    ->nullable(),
                'document_type' => $schema->string()
                    ->description('The uploaded document type, such as receipt or bill.')
                    ->required()
                    ->nullable(),
            ])
                ->description('Optional metadata when the transaction was created from an uploaded receipt or bill.')
                ->required()
                ->nullable(),
        ];
    }

    private function normalizeReceiptMeta(?array $receipt, $user): ?array
    {
        if (! is_array($receipt) || $receipt === []) {
            return null;
        }

        $uploadIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $receipt['upload_ids'] ?? [])));

        if ($uploadIds !== []) {
            $resolvedIds = UploadedFile::query()
                ->whereIn('id', $uploadIds)
                ->where('user_id', $user->id)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            sort($uploadIds);
            sort($resolvedIds);

            if ($resolvedIds !== $uploadIds) {
                throw new RuntimeException('One or more receipt uploads are not accessible to the current user.');
            }
        }

        $merchant = $this->normalizeOptionalTextValue($receipt['merchant'] ?? null);
        $documentType = $this->normalizeOptionalTextValue($receipt['document_type'] ?? null);

        return array_filter([
            'upload_ids' => $uploadIds,
            'tax_amount' => array_key_exists('tax_amount', $receipt) && $receipt['tax_amount'] !== null ? (float) $receipt['tax_amount'] : null,
            'total_amount' => array_key_exists('total_amount', $receipt) && $receipt['total_amount'] !== null ? (float) $receipt['total_amount'] : null,
            'merchant' => $merchant,
            'confidence' => array_key_exists('confidence', $receipt) && $receipt['confidence'] !== null ? (float) $receipt['confidence'] : null,
            'document_type' => $documentType,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
