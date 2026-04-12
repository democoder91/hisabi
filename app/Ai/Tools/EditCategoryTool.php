<?php

namespace App\Ai\Tools;

use App\Domains\Category\Models\Category;
use App\Domains\Category\Services\CategoryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Laravel\Ai\Tools\Request;
use Stringable;

class EditCategoryTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Edit a category owned by the authenticated user. Use this to rename the category or adjust its type, color, or icon. If the user does not know the category ID, list categories first.';
    }

    public function handle(Request $request): Stringable|string
    {
        $this->authenticatedUser();
        $input = $request->all();
        $this->uppercaseIfPresent($input, ['type']);

        $this->ensureAnyProvided(
            $input,
            ['name_en', 'name_ar', 'type', 'color', 'icon'],
            'Provide at least one field to update: name_en, name_ar, type, color, or icon.'
        );

        $validated = $this->validateInput($input, [
            'category_id' => ['required', 'integer'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in([
                Category::INCOME,
                Category::EXPENSES,
                Category::SAVINGS,
                Category::INVESTMENT,
            ])],
            'color' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        $category = Category::query()->withCount('transactions')->find($validated['category_id']);

        if (! $category) {
            throw new \RuntimeException('The specified category was not found for the authenticated user.');
        }

        $payload = [];
        $translations = $this->normalizeNameTranslations($input, $category->getTranslations('name'));

        if ($translations !== null) {
            $payload['name'] = $translations;
        }

        foreach (['type', 'color', 'icon'] as $field) {
            if (Arr::exists($validated, $field)) {
                $payload[$field] = $validated[$field];
            }
        }

        $updated = app(CategoryService::class)->update($category->id, $payload)->loadCount('transactions');

        return 'Category updated successfully: ' . $this->formatCategory($updated);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category_id' => $schema->integer()
                ->description('The ID of the category to update.')
                ->required(),
            'name_en' => $schema->string()
                ->description('Optional new English name for the category.')
                ->nullable(),
            'name_ar' => $schema->string()
                ->description('Optional new Arabic translation for the category. Use null to clear it.')
                ->nullable(),
            'type' => $schema->string()
                ->description('Optional new category type.')
                ->enum([Category::INCOME, Category::EXPENSES, Category::SAVINGS, Category::INVESTMENT])
                ->nullable(),
            'color' => $schema->string()
                ->description('Optional new color label.')
                ->nullable(),
            'icon' => $schema->string()
                ->description('Optional new icon name.')
                ->nullable(),
        ];
    }
}