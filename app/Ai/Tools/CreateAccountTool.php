<?php

namespace App\Ai\Tools;

use App\Domains\Account\Models\Account;
use App\Domains\Account\Services\AccountService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateAccountTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Create a new account for the authenticated user. Use this when the user wants a new wallet, bank, cash, savings, or similar account. You need at least the English account name and the starting balance.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $input = $request->all();

        $validated = $this->validateInput($input, [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'balance' => ['required', 'numeric'],
            'type' => ['nullable', 'string', 'in:' . implode(',', Account::ledgerTypes())],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        $account = app(AccountService::class)->create([
            'user_id' => $user->id,
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'] ?? null,
            ],
            'balance' => (float) $validated['balance'],
            'type' => $validated['type'] ?? Account::TYPE_ASSET,
            'parent_id' => $validated['parent_id'] ?? null,
        ])->load(['sharedUsers:id,name,email'])->loadCount('transactions');

        return 'Account created successfully: ' . $this->formatAccount($account);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name_en' => $schema->string()
                ->description('The account name in English.')
                ->required(),
            'name_ar' => $schema->string()
                ->description('Optional Arabic translation of the account name.')
                ->required()
                ->nullable(),
            'balance' => $schema->number()
                ->description('The starting balance for the account. Can be 0.')
                ->required(),
            'type' => $schema->string()
                ->description('The account type. One of: asset, liability, equity, income, expense. Defaults to asset.')
                ->required()
                ->nullable(),
            'parent_id' => $schema->integer()
                ->description('Optional ID of a parent account to nest this account under.')
                ->required()
                ->nullable(),
        ];
    }
}