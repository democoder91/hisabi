<?php

namespace App\Ai\Tools;

use App\Domains\Budget\Models\Budget;
use App\Domains\Budget\Services\BudgetService;
use App\Enums\Currency;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Laravel\Ai\Tools\Request;
use Stringable;

class EditBudgetTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Edit a budget owned by the authenticated user. Use this to adjust the amount, accounts, dates, recurrence, or name. If the user does not know the budget ID, list budgets first.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $input = $request->all();
        $this->uppercaseIfPresent($input, ['reoccurrence', 'currency']);

        $this->ensureAnyProvided(
            $input,
            ['name_en', 'name_ar', 'amount', 'currency', 'start_at', 'end_at', 'saving', 'period', 'reoccurrence', 'account_ids'],
            'Provide at least one field to update on the budget.'
        );

        $validated = $this->validateInput($input, [
            'budget_id' => ['required', 'integer'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date'],
            'saving' => ['nullable', 'boolean'],
            'period' => ['nullable', 'integer', 'min:1'],
            'reoccurrence' => ['nullable', 'string', Rule::in([
                Budget::CUSTOM,
                Budget::DAILY,
                Budget::WEEKLY,
                Budget::MONTHLY,
                Budget::YEARLY,
            ])],
            'account_ids' => ['nullable', 'array', 'min:1'],
            'account_ids.*' => ['integer'],
        ]);

        $budget = Budget::query()->with('accounts')->find($validated['budget_id']);

        if (! $budget) {
            throw new \RuntimeException('The specified budget was not found for the authenticated user.');
        }

        $translations = $this->normalizeNameTranslations($input, $budget->getTranslations('name'));
        $merged = [
            'name' => $translations ?? $budget->getTranslations('name'),
            'amount' => Arr::exists($validated, 'amount') ? (float) $validated['amount'] : (float) $budget->amount,
            'currency' => Arr::exists($validated, 'currency') ? ($validated['currency'] ?? $this->defaultCurrency()) : $budget->currency,
            'start_at' => Arr::exists($validated, 'start_at') ? $validated['start_at'] : $budget->start_at?->format('Y-m-d'),
            'end_at' => Arr::exists($input, 'end_at') ? ($validated['end_at'] ?? null) : $budget->end_at?->format('Y-m-d'),
            'saving' => Arr::exists($validated, 'saving') ? (bool) $validated['saving'] : (bool) $budget->saving,
            'period' => Arr::exists($validated, 'period') ? (int) $validated['period'] : (int) $budget->period,
            'reoccurrence' => Arr::exists($validated, 'reoccurrence') ? $validated['reoccurrence'] : $budget->reoccurrence,
            'account_ids' => Arr::exists($validated, 'account_ids')
                ? $this->accessibleAccountIds($validated['account_ids'], $user)
                : $budget->accounts->pluck('id')->map(fn(mixed $id) => (int) $id)->all(),
        ];

        $this->validateInput([
            'name_en' => $merged['name']['en'] ?? null,
            'name_ar' => $merged['name']['ar'] ?? null,
            'amount' => $merged['amount'],
            'currency' => $merged['currency'],
            'start_at' => $merged['start_at'],
            'end_at' => $merged['end_at'],
            'saving' => $merged['saving'],
            'period' => $merged['period'],
            'reoccurrence' => $merged['reoccurrence'],
            'account_ids' => $merged['account_ids'],
        ], [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'start_at' => ['required', 'date'],
            'end_at' => [
                Rule::requiredIf(fn() => $merged['reoccurrence'] === Budget::CUSTOM),
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
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => ['integer'],
        ]);

        $updated = app(BudgetService::class)->update($budget, [
            'name' => $merged['name'],
            'amount' => $merged['amount'],
            'currency' => $merged['currency'],
            'start_at' => $merged['start_at'],
            'end_at' => $merged['end_at'],
            'saving' => $merged['saving'],
            'period' => $merged['period'],
            'reoccurrence' => $merged['reoccurrence'],
            'account_ids' => $merged['account_ids'],
        ]);

        return 'Budget updated successfully: ' . $this->formatBudget($updated);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'budget_id' => $schema->integer()
                ->description('The ID of the budget to update.')
                ->required(),
            'name_en' => $schema->string()
                ->description('Optional new English name for the budget.')
                ->required()
                ->nullable(),
            'name_ar' => $schema->string()
                ->description('Optional new Arabic translation for the budget. Use null to clear it.')
                ->required()
                ->nullable(),
            'amount' => $schema->number()
                ->description('Optional new budget amount.')
                ->required()
                ->nullable(),
            'currency' => $schema->string()
                ->description('Optional replacement 3-letter currency code. Defaults to the existing budget currency when omitted.')
                ->enum(Currency::values())
                ->required()
                ->nullable(),
            'start_at' => $schema->string()
                ->description('Optional new start date in YYYY-MM-DD format.')
                ->required()
                ->nullable(),
            'end_at' => $schema->string()
                ->description('Optional new end date in YYYY-MM-DD format. Required when the resulting recurrence is CUSTOM.')
                ->required()
                ->nullable(),
            'saving' => $schema->boolean()
                ->description('Optional updated savings flag.')
                ->required()
                ->nullable(),
            'period' => $schema->integer()
                ->description('Optional updated recurrence period length.')
                ->required()
                ->nullable(),
            'reoccurrence' => $schema->string()
                ->description('Optional updated recurrence type.')
                ->enum([Budget::CUSTOM, Budget::DAILY, Budget::WEEKLY, Budget::MONTHLY, Budget::YEARLY])
                ->required()
                ->nullable(),
            'account_ids' => $schema->array()
                ->description('Optional replacement list of account IDs for the budget.')
                ->items($schema->integer())
                ->min(1)
                ->required()
                ->nullable(),
        ];
    }
}
