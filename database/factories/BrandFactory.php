<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use App\Domains\Brand\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition()
    {
        return [
            'user_id' => Auth::id() ?? User::factory(),
            'name' => [
                'en' => $this->faker->company(),
                'ar' => null,
            ],
            'category_id' => Category::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Brand $brand) {
            if ($brand->category && empty($brand->user_id)) {
                $brand->user_id = $brand->category->user_id;
            }
        })->afterCreating(function (Brand $brand) {
            if ($brand->category && $brand->user_id !== $brand->category->user_id) {
                $brand->forceFill(['user_id' => $brand->category->user_id])->save();
            }
        });
    }
}
