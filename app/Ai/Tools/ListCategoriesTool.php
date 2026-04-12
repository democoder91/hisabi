<?php

namespace App\Ai\Tools;

use App\Domains\Category\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListCategoriesTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'List categories owned by the authenticated user. Use this before editing categories or budgets, or when the user asks which categories exist.';
    }

    public function handle(Request $request): Stringable|string
    {
        $this->authenticatedUser();
        $input = $request->all();
        $this->uppercaseIfPresent($input, ['type']);

        $validated = $this->validateInput($input, [
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in([
                Category::INCOME,
                Category::EXPENSES,
                Category::SAVINGS,
                Category::INVESTMENT,
            ])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $query = Category::query()->withCount('transactions');

        if (! empty($validated['search'])) {
            $this->applyLocalizedSearch($query, 'name', $validated['search']);
        }

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $categories = $query
            ->orderByDesc('id')
            ->limit($this->normalizeLimit($validated['limit'] ?? null))
            ->get();

        if ($categories->isEmpty()) {
            return 'No categories found for the current filters.';
        }

        return "Categories:\n" . $categories->map(fn (Category $category) => $this->formatCategory($category))->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Optional search term for the category name.')
                ->nullable(),
            'type' => $schema->string()
                ->description('Optional category type filter.')
                ->enum([Category::INCOME, Category::EXPENSES, Category::SAVINGS, Category::INVESTMENT])
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Maximum number of categories to return. Defaults to 10, max 25.')
                ->nullable(),
        ];
    }
}