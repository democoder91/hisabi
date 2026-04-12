<?php

namespace Database\Factories;

use App\Domains\Account\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'user_id' => Auth::id() ?? User::factory(),
            'name' => $this->faker->randomElement(['Checking', 'Savings', 'Cash']),
            'balance' => 0,
        ];
    }
}