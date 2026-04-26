<?php

namespace App\Ai\Tools;

use App\Domains\Budget\Models\Budget;
use App\Domains\Search\Services\SemanticSearchService;
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
        $user = $this->authenticatedUser();
        $input = $request->all();
        $this->uppercaseIfPresent($input, ['reoccurrence']);

        $validated = $this->validateInput($input, [
            'search' => ['nullable', 'string', 'max:255'],
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

        $limit = $this->normalizeLimit($validated['limit'] ?? null);
        $query = Budget::query()->with('accounts');

        if (array_key_exists('saving', $validated)) {
            $query->where('saving', (bool) $validated['saving']);
        }

        if (! empty($validated['reoccurrence'])) {
            $query->where('reoccurrence', $validated['reoccurrence']);
        }

        $search = isset($validated['search']) ? trim((string) $validated['search']) : '';
        $orderedIds = null;

        if ($search !== '') {
            $matchedIds = app(SemanticSearchService::class)->searchBudgetIds($user, $search, $limit * 4);

            if ($matchedIds !== []) {
                $orderedIds = $matchedIds;
                $query->whereIn('id', $matchedIds);
            } else {
                $this->applyLocalizedSearch($query, 'name', $search);
                $query->orderByDesc('start_at');
            }
        } else {
            $query->orderByDesc('start_at');
        }

        $budgets = $query->limit($limit)->get();

        if ($orderedIds !== null) {
            $position = array_flip($orderedIds);
            $budgets = $budgets
                ->sortBy(fn (Budget $budget) => $position[$budget->id] ?? PHP_INT_MAX)
                ->values();
        }

        if ($budgets->isEmpty()) {
            return 'No budgets found for the current filters.';
        }

        return "Budgets:\n" . $budgets->map(fn(Budget $budget) => $this->formatBudget($budget))->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Optional search term that matches the budget name (supports semantic and partial matching).')
                ->required()
                ->nullable(),
            'saving' => $schema->boolean()
                ->description('Optional filter for savings budgets only.')
                ->required()
                ->nullable(),
            'reoccurrence' => $schema->string()
                ->description('Optional recurrence filter.')
                ->enum([Budget::CUSTOM, Budget::DAILY, Budget::WEEKLY, Budget::MONTHLY, Budget::YEARLY])
                ->required()
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Maximum number of budgets to return. Defaults to 10, max 25.')
                ->required()
                ->nullable(),
        ];
    }
}
