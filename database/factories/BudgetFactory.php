<?php

namespace Database\Factories;

use App\Domains\Budget\Models\Budget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => [
                'en' => $this->faker->words(2, true),
                'ar' => null,
            ],
            'amount' => $this->faker->numberBetween(100, 5000),
            'currency' => config('hisabi.currency'),
            'start_at' => now(),
            'reoccurrence' => Budget::DAILY,
            'period' => 1,
        ];
    }
}
