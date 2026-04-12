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
        Category::create(['name' => ['en' => 'Income', 'ar' => 'دخل'], 'type' => Category::INCOME, 'color' => 'red'])
            ->brands()
            ->create(['name' => ['en' => 'Salary', 'ar' => 'راتب']]);

        Category::create(['name' => ['en' => 'Housing', 'ar' => 'سكن'], 'type' => Category::EXPENSES, 'color' => 'blue'])
            ->brands()
            ->create(['name' => ['en' => 'House Rent', 'ar' => 'إيجار المنزل']]);

        Category::create(['name' => ['en' => 'Groceries', 'ar' => 'بقالة'], 'type' => Category::EXPENSES, 'color' => 'green'])
            ->brands()
            ->createMany([
                ['name' => ['en' => 'LULU', 'ar' => 'لولو']],
                ['name' => ['en' => 'CARREFOUR', 'ar' => 'كارفور']],
            ]);

        Category::create(['name' => ['en' => 'Utilities', 'ar' => 'خدمات'], 'type' => Category::EXPENSES, 'color' => 'orange'])
            ->brands()
            ->createMany([
                ['name' => ['en' => 'Smart Dubai', 'ar' => 'دبي الذكية']],
            ]);

        Category::create(['name' => ['en' => 'Transportation', 'ar' => 'مواصلات'], 'type' => Category::EXPENSES, 'color' => 'purple'])
            ->brands()
            ->createMany([
                ['name' => ['en' => 'ENOC', 'ar' => 'إينوك']],
            ]);

        Category::create(['name' => ['en' => 'Shopping', 'ar' => 'تسوق'], 'type' => Category::EXPENSES, 'color' => 'pink'])
            ->brands()
            ->createMany([
                ['name' => ['en' => 'IKEA', 'ar' => 'إيكيا']],
                ['name' => ['en' => 'HOME CENTRE', 'ar' => 'هوم سنتر']],
                ['name' => ['en' => 'MCDONALDS', 'ar' => 'ماكدونالدز']],
            ]);

        Category::create(['name' => ['en' => 'Support', 'ar' => 'دعم'], 'type' => Category::EXPENSES, 'color' => 'indigo'])
            ->brands()
            ->createMany([
                ['name' => ['en' => 'Family Support', 'ar' => 'دعم الأسرة']],
            ]);

        Category::create(['name' => ['en' => 'Debt', 'ar' => 'ديون'], 'type' => Category::EXPENSES, 'color' => 'gray'])
            ->brands()
            ->createMany([
                ['name' => ['en' => 'Debt', 'ar' => 'دين']],
            ]);
    }
}
