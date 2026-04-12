<?php

use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('account_id')->constrained()->cascadeOnDelete();
        });

        $primaryUserId = DB::table('users')->orderBy('id')->value('id');

        DB::table('transactions')
            ->leftJoin('brands', 'brands.id', '=', 'transactions.brand_id')
            ->leftJoin('categories', 'categories.id', '=', 'brands.category_id')
            ->leftJoin('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->select([
                'transactions.id',
                'transactions.transaction_type',
                'brands.user_id as brand_user_id',
                'accounts.user_id as account_user_id',
                'categories.id as resolved_category_id',
                'categories.type as resolved_category_type',
            ])
            ->orderBy('transactions.id')
            ->chunkById(250, function ($rows) use ($primaryUserId) {
                $fallbackCategoryIds = [];

                foreach ($rows as $row) {
                    $categoryId = $row->resolved_category_id;
                    $categoryType = $row->resolved_category_type;

                    if (! $categoryId) {
                        $ownerUserId = $row->account_user_id ?? $row->brand_user_id ?? $primaryUserId;

                        if (! $ownerUserId) {
                            throw new RuntimeException('Unable to determine a category owner while backfilling transactions.category_id.');
                        }

                        $categoryType = strtoupper((string) $row->transaction_type) === Transaction::TYPE_CREDIT
                            ? Category::INCOME
                            : Category::EXPENSES;

                        $cacheKey = $ownerUserId . ':' . $categoryType;

                        if (! isset($fallbackCategoryIds[$cacheKey])) {
                            $fallbackCategoryIds[$cacheKey] = $this->resolveFallbackCategoryId((int) $ownerUserId, $categoryType);
                        }

                        $categoryId = $fallbackCategoryIds[$cacheKey];
                    }

                    DB::table('transactions')
                        ->where('id', $row->id)
                        ->update([
                            'category_id' => $categoryId,
                            'transaction_type' => $categoryType === Category::INCOME
                                ? Transaction::TYPE_CREDIT
                                : Transaction::TYPE_DEBIT,
                        ]);
                }
            }, 'transactions.id', 'id');

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }

    private function resolveFallbackCategoryId(int $userId, string $type): int
    {
        $name = match ($type) {
            Category::INCOME => ['en' => 'Uncategorized Income', 'ar' => null],
            Category::SAVINGS => ['en' => 'Uncategorized Savings', 'ar' => null],
            Category::INVESTMENT => ['en' => 'Uncategorized Investment', 'ar' => null],
            default => ['en' => 'Uncategorized Expenses', 'ar' => null],
        };

        $encodedName = json_encode($name, JSON_UNESCAPED_UNICODE);

        $existingId = DB::table('categories')
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('name', $encodedName)
            ->value('id');

        if ($existingId) {
            return (int) $existingId;
        }

        return (int) DB::table('categories')->insertGetId([
            'user_id' => $userId,
            'name' => $encodedName,
            'type' => $type,
            'color' => 'gray',
            'icon' => 'shapes',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};