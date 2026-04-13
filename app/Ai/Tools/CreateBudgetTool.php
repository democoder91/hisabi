<?php

namespace App\Ai\Tools;

use App\Domains\Budget\Models\Budget;
use App\Domains\Budget\Services\BudgetService;
use App\Enums\Currency;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateBudgetTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Create a budget for the authenticated user. Use this when the user wants to cap spending or set a savings target for specific categories.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $input = $request->all();
        $this->uppercaseIfPresent($input, ['reoccurrence', 'currency']);

        $validated = $this->validateInput($input, [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'start_at' => ['required', 'date'],
            'end_at' => [
                Rule::requiredIf(fn() => ($input['reoccurrence'] ?? null) === Budget::CUSTOM),
                'nullable',
                'date',
                'after_or_equal:start_at',
            ],
            'saving' => ['nullable', 'boolean'],
            'period' => ['required', 'integer', 'min:1'],
            'reoccurrence' => ['required', 'string', Rule::in([
                Budget::CUSTOM,
                Budget::DAILY,
                Budget::WEEKLY,
                Budget::MONTHLY,
                Budget::YEARLY,
            ])],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer'],
        ]);

        $categoryIds = $this->ownedCategoryIds($validated['category_ids'], $user);

        $budget = app(BudgetService::class)->create([
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'] ?? null,
            ],
            'amount' => (float) $validated['amount'],
            'currency' => $validated['currency'] ?? $this->defaultCurrency(),
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'] ?? null,
            'saving' => (bool) ($validated['saving'] ?? false),
            'period' => (int) $validated['period'],
            'reoccurrence' => $validated['reoccurrence'],
            'category_ids' => $categoryIds,
        ]);

        return 'Budget created successfully: ' . $this->formatBudget($budget);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name_en' => $schema->string()
                ->description('The budget name in English.')
                ->required(),
            'name_ar' => $schema->string()
                ->description('Optional Arabic translation of the budget name.')
                ->nullable(),
            'amount' => $schema->number()
                ->description('The total budget amount. Must be greater than 0.')
                ->required(),
            'currency' => $schema->string()
                ->description('Optional 3-letter currency code for the budget. Defaults to the user preferred currency.')
                ->enum(Currency::values())
                ->nullable(),
            'start_at' => $schema->string()
                ->description('Budget start date in YYYY-MM-DD format.')
                ->required(),
            'end_at' => $schema->string()
                ->description('Budget end date in YYYY-MM-DD format. Required when reoccurrence is CUSTOM.')
                ->nullable(),
            'saving' => $schema->boolean()
                ->description('Whether this is a savings budget.')
                ->nullable(),
            'period' => $schema->integer()
                ->description('The recurrence period length, for example 1 for monthly.')
                ->required(),
            'reoccurrence' => $schema->string()
                ->description('The recurrence type for the budget.')
                ->enum([Budget::CUSTOM, Budget::DAILY, Budget::WEEKLY, Budget::MONTHLY, Budget::YEARLY])
                ->required(),
            'category_ids' => $schema->array()
                ->description('The IDs of categories included in this budget. At least one is required.')
                ->items($schema->integer())
                ->min(1)
                ->required(),
        ];
    }
}
