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
            'currency' => config('hisabi.currency'),
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

            $existingAccount = Account::withoutGlobalScopes()
                ->where('user_id', $categoryUserId)
                ->first();

            if (! $existingAccount) {
                $existingAccount = Account::factory()->create([
                    'user_id' => $categoryUserId,
                    'name' => ['en' => Account::DEFAULT_NAME],
                    'balance' => 0,
                ]);
            }

            $transaction->account_id = $existingAccount->id;
            $transaction->currency = $existingAccount->currency;
        })->afterCreating(function (Transaction $transaction) {
            if (! $transaction->category) {
                return;
            }

            $account = Account::withoutGlobalScopes()->find($transaction->account_id);

            if ($account && $transaction->category->user_id === $account->user_id) {
                return;
            }

            $alignedAccount = Account::withoutGlobalScopes()
                ->where('user_id', $transaction->category->user_id)
                ->first();

            if (! $alignedAccount) {
                $alignedAccount = Account::factory()->create([
                    'user_id' => $transaction->category->user_id,
                    'name' => ['en' => Account::DEFAULT_NAME],
                    'balance' => 0,
                ]);
            }

            Transaction::withoutGlobalScopes()
                ->whereKey($transaction->id)
                ->update([
                    'account_id' => $alignedAccount->id,
                    'currency' => $alignedAccount->currency,
                ]);

            $transaction->forceFill(['account_id' => $alignedAccount->id])
                ->setRelation('account', $alignedAccount);
        });
    }
}
