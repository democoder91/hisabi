<?php

namespace Database\Factories;

use App\Domains\Category\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => Auth::id() ?? User::factory(),
            'name' => [
                'en' => $this->faker->words(2, true),
                'ar' => null,
            ],
            'type' => Category::EXPENSES,
            'color' => 'gray',
            'icon' => 'wallet',
        ];
    }
}
