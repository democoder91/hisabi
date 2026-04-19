<?php

namespace App\Ai\Tools;

use App\Domains\Category\Models\Category;
use App\Domains\Category\Services\CategoryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateCategoryTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Create a new category for the authenticated user. Use this when the user wants a new spending, income, savings, or investment category.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $input = $request->all();
        $this->uppercaseIfPresent($input, ['type']);

        $validated = $this->validateInput($input, [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in([
                Category::INCOME,
                Category::EXPENSES,
                Category::SAVINGS,
                Category::INVESTMENT,
            ])],
            'color' => ['required', 'string', 'max:50'],
            'icon' => ['required', 'string', 'max:50'],
        ]);

        $category = app(CategoryService::class)->create([
            'user_id' => $user->id,
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'] ?? null,
            ],
            'type' => $validated['type'],
            'color' => $validated['color'],
            'icon' => $validated['icon'],
        ])->load(['account'])->loadCount('transactions');

        return 'Category created successfully: ' . $this->formatCategory($category);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name_en' => $schema->string()
                ->description('The category name in English.')
                ->required(),
            'name_ar' => $schema->string()
                ->description('Optional Arabic translation of the category name.')
                ->nullable(),
            'type' => $schema->string()
                ->description('The category type.')
                ->enum([Category::INCOME, Category::EXPENSES, Category::SAVINGS, Category::INVESTMENT])
                ->required(),
            'color' => $schema->string()
                ->description('A color label for the category, for example gray or red.')
                ->required(),
            'icon' => $schema->string()
                ->description('An icon name for the category, for example wallet or receipt.')
                ->required(),
        ];
    }
}
