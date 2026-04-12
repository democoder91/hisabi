<?php

namespace Database\Factories;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
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
            'category_id' => Category::factory(),
            'amount' => $this->faker->numberBetween(),
            'transaction_type' => Transaction::TYPE_DEBIT,
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

            $categoryUserId = $transaction->category?->user_id;

            if (! $categoryUserId) {
                $transaction->account_id = Account::factory()->create()->id;

                return;
            }

            $transaction->account_id = Account::withoutGlobalScopes()->firstOrCreate(
                [
                    'user_id' => $categoryUserId,
                    'name' => Account::DEFAULT_NAME,
                ],
                [
                    'balance' => 0,
                ]
            )->id;
        })->afterCreating(function (Transaction $transaction) {
            if (! $transaction->category) {
                return;
            }

            $account = Account::withoutGlobalScopes()->find($transaction->account_id);

            if ($account && $transaction->category->user_id === $account->user_id) {
                return;
            }

            $alignedAccount = Account::withoutGlobalScopes()->firstOrCreate(
                [
                    'user_id' => $transaction->category->user_id,
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
