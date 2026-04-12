<?php

namespace App\Domains\Transaction\Models;

use App\Domains\Brand\Models\Brand;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Category;
use Carbon\Carbon;

class Transaction extends Model
{
    use HasFactory;

    public const TYPE_DEBIT = 'DEBIT';
    public const TYPE_CREDIT = 'CREDIT';

    protected $guarded = [];

    protected $attributes = [
        'transaction_type' => self::TYPE_DEBIT,
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return TransactionFactory::new();
    }

    protected $casts = [
        'meta' => 'array',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeExpenses($query)
    {
        return $query->where(function (Builder $builder) {
            $builder->whereHas('brand.category', function ($query) {
                return $query->where('type', Category::EXPENSES);
            })->orWhere(function (Builder $uncategorizedQuery) {
                $uncategorizedQuery->whereNull('brand_id')
                    ->where('transaction_type', self::TYPE_DEBIT);
            });
        });
    }

    public function scopeIncome($query)
    {
        return $query->where(function (Builder $builder) {
            $builder->whereHas('brand.category', function ($query) {
                return $query->where('type', Category::INCOME);
            })->orWhere(function (Builder $uncategorizedQuery) {
                $uncategorizedQuery->whereNull('brand_id')
                    ->where('transaction_type', self::TYPE_CREDIT);
            });
        });
    }

    public function scopeSavings($query)
    {
        return $query->whereHas('brand.category', function ($query) {
            return $query->where('type', Category::SAVINGS);
        });
    }

    public function scopeInvestment($query)
    {
        return $query->whereHas('brand.category', function ($query) {
            return $query->where('type', Category::INVESTMENT);
        });
    }

    public function scopeDebit($query)
    {
        return $query->where('transaction_type', self::TYPE_DEBIT);
    }

    public function scopeCredit($query)
    {
        return $query->where('transaction_type', self::TYPE_CREDIT);
    }

    public static function transactionTypeForCategoryType(string $categoryType): string
    {
        return $categoryType === Category::INCOME
            ? self::TYPE_CREDIT
            : self::TYPE_DEBIT;
    }

    public static function tryCreateFromSms($sms)
    {
        $brandFromSms = $sms->meta['data']['brand'] ?? null;
        $amountFromSms = $sms->meta['data']['amount'] ?? null;
        $transactionDatetimeFromSMS = $sms->meta['data']['datetime'] ?? null;

        if(! $brandFromSms || ! $amountFromSms) {
            return;
        }

        $brand = Brand::findOrCreateNew($brandFromSms);

        $amount = (float) filter_var($amountFromSms, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $transactionDatetime = $transactionDatetimeFromSMS ? Carbon::parse($transactionDatetimeFromSMS) : now();

        return static::create([
            'amount' => $amount,
            'brand_id' => $brand->id,
            'transaction_type' => static::transactionTypeForCategoryType($brand->category?->type ?? Category::EXPENSES),
            'created_at' => $transactionDatetime
        ]);
    }
}

