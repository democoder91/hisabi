<?php

namespace App\Ai\Tools;

use App\Domains\Account\Models\Account;
use App\Domains\Search\Services\SemanticSearchService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListAccountsTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'List accounts accessible to the authenticated user, including owned and shared accounts. Use this before editing an account or when the user asks what accounts they have. Do NOT pass a type filter when looking up an account by name — accounts can have any ledger type (e.g. a credit card is typically a liability, a savings/cash account is an asset). Only set type when the user explicitly asks for accounts of that ledger category.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $input = $request->all();

        $validated = $this->validateInput($input, [
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
            'type' => ['nullable', 'string', Rule::in(Account::ledgerTypes())],
        ]);

        $limit = $this->normalizeLimit($validated['limit'] ?? null);

        $query = Account::query()
            ->accessibleTo($user)
            ->with(['sharedUsers:id,name,email'])
            ->withCount('transactions');

        if (! empty($validated['type']) && Account::supportsTypeColumn()) {
            $query->where('type', $validated['type']);
        }

        $search = isset($validated['search']) ? trim((string) $validated['search']) : '';
        $orderedIds = null;

        if ($search !== '') {
            $matchedIds = app(SemanticSearchService::class)->searchAccountIds($user, $search, $limit * 4);

            if ($matchedIds !== []) {
                $orderedIds = $matchedIds;
                $query->whereIn('id', $matchedIds);
            } else {
                $this->applyLocalizedSearch($query, 'name', $search);
                $query->orderByRaw(Account::localizedNameSqlExpression(app()->getLocale()) . ' ASC');
            }
        } else {
            $query->orderByRaw(Account::localizedNameSqlExpression(app()->getLocale()) . ' ASC');
        }

        $accounts = $query->limit($limit)->get();

        if ($orderedIds !== null) {
            $position = array_flip($orderedIds);
            $accounts = $accounts
                ->sortBy(fn (Account $account) => $position[$account->id] ?? PHP_INT_MAX)
                ->values();
        }

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
            'type' => $schema->string()
                ->description('Optional account ledger type filter. Accepted values: asset (cash, bank, savings, wallet), liability (credit cards, loans, debt), equity, income, expense. Leave null when resolving an account by name — credit cards and loans live under liability, not asset.')
                ->enum(Account::ledgerTypes())
                ->required()
                ->nullable(),
        ];
    }
}