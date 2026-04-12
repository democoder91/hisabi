<?php

use App\Domains\Transaction\Models\Transaction;
use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        DB::table('transactions')
            ->leftJoin('brands', 'brands.id', '=', 'transactions.brand_id')
            ->leftJoin('categories', 'categories.id', '=', 'brands.category_id')
            ->select(['transactions.id', 'categories.type as category_type'])
            ->orderBy('transactions.id')
            ->chunkById(100, function ($transactions) {
                foreach ($transactions as $transaction) {
                    DB::table('transactions')
                        ->where('id', $transaction->id)
                        ->update([
                            'transaction_type' => Transaction::transactionTypeForCategoryType(
                                $transaction->category_type ?? Category::EXPENSES,
                            ),
                        ]);
                }
            }, 'transactions.id', 'id');
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('transaction_type');
        });
    }
};