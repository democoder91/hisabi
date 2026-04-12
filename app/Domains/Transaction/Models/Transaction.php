<?php

namespace App\Domains\Transaction\Models;

use App\Domains\Account\Models\Account;
use App\Domains\Brand\Models\Brand;
use App\Scopes\OwnedAccountScope;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Category;
use Carbon\Carbon;

class Transaction extends Model
{
    use HasFactory;

    protected array $balanceSnapshot = [];
    protected array $auditSnapshot = [];

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

    protected static function booted(): void
    {
        static::addGlobalScope(new OwnedAccountScope());

        static::created(function (self $transaction) {
            $transaction->applyAccountBalanceDelta($transaction->account_id, $transaction->signedAmount());
        });

        static::updating(function (self $transaction) {
            $transaction->balanceSnapshot = [
                'account_id' => (int) ($transaction->getOriginal('account_id') ?? $transaction->account_id),
                'signed_amount' => self::signedAmountFromValues(
                    (float) ($transaction->getOriginal('amount') ?? $transaction->amount),
                    (string) ($transaction->getOriginal('transaction_type') ?? $transaction->transaction_type)
                ),
            ];
        });

        static::updated(function (self $transaction) {
            if ($transaction->balanceSnapshot !== []) {
                $transaction->applyAccountBalanceDelta(
                    $transaction->balanceSnapshot['account_id'],
                    -1 * $transaction->balanceSnapshot['signed_amount']
                );
            }

            $transaction->applyAccountBalanceDelta($transaction->account_id, $transaction->signedAmount());
            $transaction->balanceSnapshot = [];
        });

        static::deleted(function (self $transaction) {
            $transaction->applyAccountBalanceDelta($transaction->account_id, -1 * $transaction->signedAmount());
        });
    }

    protected $casts = [
        'meta' => 'array',
        'amount' => 'float',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class)->withoutGlobalScopes();
    }

    public function audits(): HasMany
    {
        return $this->hasMany(TransactionAudit::class);
    }

    public function scopeForAccessibleAccounts(Builder $query, $user)
    {
        return $query->whereHas('account', function (Builder $accountQuery) use ($user) {
            $accountQuery->accessibleTo($user);
        });
    }

    public function signedAmount(): float
    {
        return self::signedAmountFromValues((float) $this->amount, (string) $this->transaction_type);
    }

    public static function signedAmountFromValues(float $amount, string $transactionType): float
    {
        return strtoupper($transactionType) === self::TYPE_CREDIT ? $amount : -1 * $amount;
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
        $user = auth()->user();

        if (! $user) {
            return;
        }

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
            'account_id' => $user->getOrCreateDefaultAccount()->id,
            'amount' => $amount,
            'brand_id' => $brand->id,
            'transaction_type' => static::transactionTypeForCategoryType($brand->category?->type ?? Category::EXPENSES),
            'created_at' => $transactionDatetime
        ]);
    }

    private function applyAccountBalanceDelta(int $accountId, float $delta): void
    {
        $account = Account::withoutGlobalScopes()->find($accountId);

        if (! $account) {
            return;
        }

        $account->applyBalanceDelta($delta);
    }

    public function storeAuditSnapshot(array $snapshot): void
    {
        $this->auditSnapshot = $snapshot;
    }

    public function pullAuditSnapshot(): array
    {
        $snapshot = $this->auditSnapshot;
        $this->auditSnapshot = [];

        return $snapshot;
    }
}

