<?php

namespace Database\Factories;

use App\Domains\Account\Models\Account;
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
            'account_id' => null,
            'amount' => $this->faker->numberBetween(),
            'transaction_type' => Transaction::TYPE_DEBIT,
            'brand_id' => Brand::factory(),
            'currency' => 'AED',
            'note' => $this->faker->text(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Transaction $transaction) {
            if ($transaction->account_id) {
                return;
            }

            $brandUserId = $transaction->brand?->user_id;

            if (! $brandUserId) {
                $transaction->account_id = Account::factory()->create()->id;

                return;
            }

            $transaction->account_id = Account::withoutGlobalScopes()->firstOrCreate(
                [
                    'user_id' => $brandUserId,
                    'name' => Account::DEFAULT_NAME,
                ],
                [
                    'balance' => 0,
                ]
            )->id;
        })->afterCreating(function (Transaction $transaction) {
            if (! $transaction->brand) {
                return;
            }

            $account = Account::withoutGlobalScopes()->find($transaction->account_id);

            if ($account && $transaction->brand->user_id === $account->user_id) {
                return;
            }

            $alignedAccount = Account::withoutGlobalScopes()->firstOrCreate(
                [
                    'user_id' => $transaction->brand->user_id,
                    'name' => Account::DEFAULT_NAME,
                ],
                [
                    'balance' => 0,
                ]
            );

            Transaction::withoutGlobalScopes()
                ->whereKey($transaction->id)
                ->update(['account_id' => $alignedAccount->id]);

            $transaction->forceFill(['account_id' => $alignedAccount->id])
                ->setRelation('account', $alignedAccount);
        });
    }
}
