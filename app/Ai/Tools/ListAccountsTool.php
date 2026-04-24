<?php

namespace App\Ai\Tools;

use App\Domains\Account\Models\Account;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListAccountsTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'List accounts accessible to the authenticated user, including owned and shared accounts. Use this before editing an account or when the user asks what accounts they have.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $input = $request->all();

        $validated = $this->validateInput($input, [
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $query = Account::query()
            ->accessibleTo($user)
            ->with(['sharedUsers:id,name,email'])
            ->withCount('transactions');

        if (! empty($validated['search'])) {
            $this->applyLocalizedSearch($query, 'name', $validated['search']);
        }

        $accounts = $query
            ->orderByRaw(Account::localizedNameSqlExpression(app()->getLocale()) . ' ASC')
            ->limit($this->normalizeLimit($validated['limit'] ?? null))
            ->get();

        if ($accounts->isEmpty()) {
            return 'No accounts found for the current user.';
        }

        return "Accounts:\n" . $accounts->map(fn (Account $account) => $this->formatAccount($account))->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Optional search term for the account name.')
                ->required()
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Maximum number of accounts to return. Defaults to 10, max 25.')
                ->required()
                ->nullable(),
        ];
    }
}