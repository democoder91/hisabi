<?php

namespace Database\Factories;

use App\Domains\Brand\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Domains\Transaction\Models\Transaction;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'amount' => $this->faker->numberBetween(),
            'transaction_type' => Transaction::TYPE_DEBIT,
            'brand_id' => Brand::factory(),
            'currency' => 'AED',
            'note' => $this->faker->text()
        ];
    }
}
