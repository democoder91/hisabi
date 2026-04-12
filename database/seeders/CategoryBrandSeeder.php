<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoryBrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $categories = [
            ['name' => ['en' => 'Income', 'ar' => 'دخل'], 'type' => Category::INCOME, 'color' => 'red', 'icon' => 'wallet'],
            ['name' => ['en' => 'Housing', 'ar' => 'سكن'], 'type' => Category::EXPENSES, 'color' => 'blue', 'icon' => 'house'],
            ['name' => ['en' => 'Groceries', 'ar' => 'بقالة'], 'type' => Category::EXPENSES, 'color' => 'green', 'icon' => 'shopping-cart'],
            ['name' => ['en' => 'Utilities', 'ar' => 'خدمات'], 'type' => Category::EXPENSES, 'color' => 'orange', 'icon' => 'lightning'],
            ['name' => ['en' => 'Transportation', 'ar' => 'مواصلات'], 'type' => Category::EXPENSES, 'color' => 'purple', 'icon' => 'car'],
            ['name' => ['en' => 'Shopping', 'ar' => 'تسوق'], 'type' => Category::EXPENSES, 'color' => 'pink', 'icon' => 'shopping-bag'],
            ['name' => ['en' => 'Support', 'ar' => 'دعم'], 'type' => Category::EXPENSES, 'color' => 'indigo', 'icon' => 'heart'],
            ['name' => ['en' => 'Debt', 'ar' => 'ديون'], 'type' => Category::EXPENSES, 'color' => 'gray', 'icon' => 'receipt'],
        ];

        foreach ($categories as $attributes) {
            Category::query()->firstOrCreate(
                [
                    'name' => $attributes['name'],
                    'type' => $attributes['type'],
                ],
                [
                    'color' => $attributes['color'],
                    'icon' => $attributes['icon'],
                ],
            );
        }
    }
}
