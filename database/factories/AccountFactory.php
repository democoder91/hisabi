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
        $name = $this->faker->randomElement(['Checking', 'Savings', 'Cash']);

        return [
            'user_id' => Auth::id() ?? User::factory(),
            'name' => ['en' => $name],
            'type' => Account::TYPE_ASSET,
            'balance' => 0,
            'currency' => config('hisabi.currency'),
        ];
    }
}
