<?php

namespace App\Ai\Tools;

use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class CreateTransactionTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Create a new financial transaction. Use this when the user wants to record a purchase, expense, income, savings deposit, or investment. You MUST have: amount and category type (EXPENSES, INCOME, SAVINGS, or INVESTMENT). Brand/merchant name is optional. If amount or category type is missing, ask the user before calling this tool.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();

        if (! $user) {
            throw new RuntimeException('An authenticated user is required to create a transaction.');
        }

        $amount = (float) $request['amount'];
        $brandName = $request['brand_name'] ?? null;
        $categoryType = strtoupper($request['category_type']);
        $currency = $request['currency'] ?? $this->getDefaultCurrency();
        $note = $request['note'] ?? null;
        $date = $request['date'] ?? now()->toDateTimeString();

        $category = Category::findOrCreateFallbackForUser($user->id, $categoryType);

        $resolvedNote = $note;
        if ($brandName) {
            $resolvedNote = $note
                ? $note . ' | Merchant: ' . $brandName
                : 'Merchant: ' . $brandName;
        }

        $transactionService = app(TransactionService::class);

        $transaction = $transactionService->create([
            'account_id' => $user->getOrCreateDefaultAccount()->id,
            'amount' => $amount,
            'category_id' => $category->id,
            'transaction_type' => $categoryType === Category::INCOME ? 'CREDIT' : 'DEBIT',
            'currency' => $currency,
            'note' => $resolvedNote,
            'created_at' => Carbon::parse($date),
        ]);

        $categoryName = $category->getTranslation('name', app()->getLocale(), false)
            ?: $category->getTranslation('name', 'en', false)
            ?: $categoryType;
        $brandLabel = $brandName ? " for {$brandName}" : '';

        return "Transaction created successfully: {$currency} {$amount}{$brandLabel} ({$categoryName}) on {$transaction->created_at->format('Y-m-d')}" .
            ($resolvedNote ? " - Note: {$resolvedNote}" : '');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'amount' => $schema->number()
                ->description('The transaction amount (positive number)')
                ->required(),
            'brand_name' => $schema->string()
                ->description('The merchant, store, company, or source name. Optional - leave empty if no specific brand.')
                ->nullable(),
            'category_type' => $schema->string()
                ->description('The type of transaction')
                ->enum(['EXPENSES', 'INCOME', 'SAVINGS', 'INVESTMENT'])
                ->required(),
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

    private function getDefaultCurrency(): string
    {
        $user = Auth::user();

        if ($user && $user->default_currency) {
            return $user->default_currency;
        }

        return config('hisabi.currency', 'AED');
    }
}
