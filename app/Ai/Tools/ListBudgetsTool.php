<?php

namespace App\Ai\Tools;

use App\Domains\Budget\Models\Budget;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListBudgetsTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'List budgets owned by the authenticated user. Use this before editing a budget or when the user asks which budgets they already have.';
    }

    public function handle(Request $request): Stringable|string
    {
        $this->authenticatedUser();
        $input = $request->all();
        $this->uppercaseIfPresent($input, ['reoccurrence']);

        $validated = $this->validateInput($input, [
            'saving' => ['nullable', 'boolean'],
            'reoccurrence' => ['nullable', 'string', Rule::in([
                Budget::CUSTOM,
                Budget::DAILY,
                Budget::WEEKLY,
                Budget::MONTHLY,
                Budget::YEARLY,
            ])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $query = Budget::query()->with('categories');

        if (array_key_exists('saving', $validated)) {
            $query->where('saving', (bool) $validated['saving']);
        }

        if (! empty($validated['reoccurrence'])) {
            $query->where('reoccurrence', $validated['reoccurrence']);
        }

        $budgets = $query
            ->orderByDesc('start_at')
            ->limit($this->normalizeLimit($validated['limit'] ?? null))
            ->get();

        if ($budgets->isEmpty()) {
            return 'No budgets found for the current filters.';
        }

        return "Budgets:\n" . $budgets->map(fn(Budget $budget) => $this->formatBudget($budget))->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'saving' => $schema->boolean()
                ->description('Optional filter for savings budgets only.')
                ->nullable(),
            'reoccurrence' => $schema->string()
                ->description('Optional recurrence filter.')
                ->enum([Budget::CUSTOM, Budget::DAILY, Budget::WEEKLY, Budget::MONTHLY, Budget::YEARLY])
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Maximum number of budgets to return. Defaults to 10, max 25.')
                ->nullable(),
        ];
    }
}
