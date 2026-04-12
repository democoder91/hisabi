<?php

use App\Domains\Transaction\Models\Transaction;
use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('transaction_type', 10)
                ->default(Transaction::TYPE_DEBIT)
                ->after('amount');
        });

        Transaction::query()
            ->with('brand.category')
            ->chunkById(100, function ($transactions) {
                foreach ($transactions as $transaction) {
                    $categoryType = $transaction->brand?->category?->type;

                    $transaction->forceFill([
                        'transaction_type' => Transaction::transactionTypeForCategoryType($categoryType ?? Category::EXPENSES),
                    ])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('transaction_type');
        });
    }
};