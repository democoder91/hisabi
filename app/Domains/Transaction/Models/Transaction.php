<?php

namespace App\Domains\Transaction\Models;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Models\User;
use App\Scopes\OwnedAccountScope;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

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

        static::saving(function (self $transaction) {
            if ($transaction->usesDoubleEntry()) {
                $fromAccount = $transaction->from_account_id
                    ? Account::withoutGlobalScopes()->find($transaction->from_account_id)
                    : null;
                $toAccount = $transaction->to_account_id
                    ? Account::withoutGlobalScopes()->find($transaction->to_account_id)
                    : null;

                if (! $transaction->currency) {
                    $transaction->currency = $fromAccount
                        ? $fromAccount->currency
                        : ($toAccount ? $toAccount->currency : null);
                }

                if (! $transaction->user_id) {
                    $transaction->user_id = $fromAccount
                        ? $fromAccount->user_id
                        : ($toAccount ? $toAccount->user_id : null);
                }

                if (! $transaction->account_id) {
                    $transaction->account_id = strtoupper((string) $transaction->transaction_type) === self::TYPE_CREDIT
                        ? $transaction->to_account_id
                        : $transaction->from_account_id;
                }

                if (! $transaction->description && $transaction->note) {
                    $transaction->description = $transaction->note;
                }

                if (! $transaction->date && $transaction->created_at) {
                    $transaction->date = $transaction->created_at;
                }

                return;
            }

            if (! $transaction->account_id) {
                return;
            }

            $account = Account::withoutGlobalScopes()->find($transaction->account_id);

            if ($account) {
                $transaction->currency = $account->currency;
            }
        });

        static::created(function (self $transaction) {
            $transaction->applyBalanceImpacts($transaction->currentBalanceImpacts());
        });

        static::updating(function (self $transaction) {
            $transaction->balanceSnapshot = $transaction->originalBalanceImpacts();
        });

        static::updated(function (self $transaction) {
            if ($transaction->balanceSnapshot !== []) {
                $transaction->applyBalanceImpacts($transaction->balanceSnapshot, true);
            }

            $transaction->applyBalanceImpacts($transaction->currentBalanceImpacts());
            $transaction->balanceSnapshot = [];
        });

        static::deleted(function (self $transaction) {
            if ($transaction->isForceDeleting() || ! $transaction->trashed()) {
                return;
            }

            $transaction->applyBalanceImpacts($transaction->currentBalanceImpacts(), true);
        });

        static::restored(function (self $transaction) {
            $transaction->applyBalanceImpacts($transaction->currentBalanceImpacts());
        });

        static::forceDeleted(function (self $transaction) {
            if ($transaction->getOriginal('deleted_at') !== null) {
                return;
            }

            $transaction->applyBalanceImpacts($transaction->currentBalanceImpacts(), true);
        });
    }

    protected $casts = [
        'meta' => 'array',
        'amount' => 'float',
        'date' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id')->withTrashed();
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id')->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withoutGlobalScopes()->withTrashed();
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

    public function usesDoubleEntry(): bool
    {
        return (int) ($this->from_account_id ?? 0) > 0 || (int) ($this->to_account_id ?? 0) > 0;
    }

    public static function signedAmountFromValues(float $amount, string $transactionType): float
    {
        return strtoupper($transactionType) === self::TYPE_CREDIT ? $amount : -1 * $amount;
    }

    public function scopeExpenses($query)
    {
        return $query->whereHas('toAccount', function ($query) {
            return $query->where('type', Account::TYPE_EXPENSE);
        });
    }

    public function scopeIncome($query)
    {
        return $query->whereHas('fromAccount', function ($query) {
            return $query->where('type', Account::TYPE_INCOME);
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

    public function reportingAccount(): ?Account
    {
        if ($this->fromAccount && $this->fromAccount->type === Account::TYPE_INCOME) {
            return $this->fromAccount;
        }

        if ($this->toAccount && $this->toAccount->type === Account::TYPE_EXPENSE) {
            return $this->toAccount;
        }

        return $this->counterpartyAccount() ?: $this->account;
    }

    public function reportingAccountType(): ?string
    {
        $reportingAccount = $this->reportingAccount();

        return $reportingAccount ? $reportingAccount->type : null;
    }

    public function movementForAccount(Account $account): float
    {
        if ($this->usesDoubleEntry()) {
            if ((int) $this->from_account_id === (int) $account->id) {
                return $account->balanceDeltaForCredit((float) $this->amount);
            }

            if ((int) $this->to_account_id === (int) $account->id) {
                return $account->balanceDeltaForDebit((float) $this->amount);
            }

            return 0.0;
        }

        if ((int) $this->account_id !== (int) $account->id) {
            return 0.0;
        }

        return self::signedAmountFromValues((float) $this->amount, (string) $this->transaction_type);
    }

    public function counterpartyAccount(): ?Account
    {
        if (! $this->usesDoubleEntry()) {
            return null;
        }

        return strtoupper((string) $this->transaction_type) === self::TYPE_CREDIT
            ? $this->fromAccount
            : $this->toAccount;
    }

    public static function tryCreateFromSms($sms)
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $brandFromSms = $sms->meta['data']['brand'] ?? null;
        $amountFromSms = $sms->meta['data']['amount'] ?? null;
        $transactionDatetimeFromSMS = $sms->meta['data']['datetime'] ?? null;

        if (! $amountFromSms) {
            return null;
        }

        $amount = (float) filter_var($amountFromSms, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $transactionDatetime = $transactionDatetimeFromSMS ? Carbon::parse($transactionDatetimeFromSMS) : now();

        if (static::smsRepresentsIncome((string) ($sms->body ?? ''))) {
            $fromAccount = $user->getOrCreateUncategorizedIncomeAccount();
            $toAccount = $user->getOrCreateDefaultAccount();
            $transactionType = self::TYPE_CREDIT;
        } else {
            $fromAccount = $user->getOrCreateDefaultAccount();
            $toAccount = $user->getOrCreateUncategorizedExpenseAccount();
            $transactionType = self::TYPE_DEBIT;
        }

        return static::create([
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => $amount,
            'transaction_type' => $transactionType,
            'note' => $brandFromSms,
            'date' => $transactionDatetime,
            'created_at' => $transactionDatetime,
        ]);
    }

    private static function smsRepresentsIncome(string $smsBody): bool
    {
        return str_contains(mb_strtolower($smsBody), 'credited');
    }

    private function applyAccountBalanceDelta(int $accountId, float $delta): void
    {
        $account = Account::withoutGlobalScopes()->find($accountId);

        if (! $account) {
            return;
        }

        $account->applyBalanceDelta($delta);
    }

    /**
     * @return array<int, float>
     */
    private function currentBalanceImpacts(): array
    {
        return $this->buildBalanceImpactsFromAttributes([
            'account_id' => $this->account_id,
            'from_account_id' => $this->from_account_id,
            'to_account_id' => $this->to_account_id,
            'amount' => $this->amount,
            'transaction_type' => $this->transaction_type,
        ]);
    }

    /**
     * @return array<int, float>
     */
    private function originalBalanceImpacts(): array
    {
        return $this->buildBalanceImpactsFromAttributes([
            'account_id' => $this->getOriginal('account_id') ?? $this->account_id,
            'from_account_id' => $this->getOriginal('from_account_id') ?? $this->from_account_id,
            'to_account_id' => $this->getOriginal('to_account_id') ?? $this->to_account_id,
            'amount' => $this->getOriginal('amount') ?? $this->amount,
            'transaction_type' => $this->getOriginal('transaction_type') ?? $this->transaction_type,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, float>
     */
    private function buildBalanceImpactsFromAttributes(array $attributes): array
    {
        $amount = round((float) ($attributes['amount'] ?? 0), 2);

        if ($amount === 0.0) {
            return [];
        }

        if ($this->usesDoubleEntryFromAttributes($attributes)) {
            $impacts = [];

            $this->appendBalanceImpact(
                $impacts,
                (int) ($attributes['from_account_id'] ?? 0),
                $amount,
                true,
            );
            $this->appendBalanceImpact(
                $impacts,
                (int) ($attributes['to_account_id'] ?? 0),
                $amount,
                false,
            );

            return $impacts;
        }

        $accountId = (int) ($attributes['account_id'] ?? 0);

        if ($accountId <= 0) {
            return [];
        }

        return [
            $accountId => self::signedAmountFromValues(
                $amount,
                (string) ($attributes['transaction_type'] ?? self::TYPE_DEBIT),
            ),
        ];
    }

    /**
     * @param  array<int, float>  $impacts
     */
    private function applyBalanceImpacts(array $impacts, bool $reverse = false): void
    {
        foreach ($impacts as $accountId => $delta) {
            $this->applyAccountBalanceDelta(
                (int) $accountId,
                $reverse ? round(-1 * $delta, 2) : round($delta, 2),
            );
        }
    }

    /**
     * @param  array<int, float>  $impacts
     */
    private function appendBalanceImpact(array &$impacts, int $accountId, float $amount, bool $credit): void
    {
        if ($accountId <= 0) {
            return;
        }

        $account = Account::withoutGlobalScopes()->find($accountId);

        if (! $account) {
            return;
        }

        $delta = $credit
            ? $account->balanceDeltaForCredit($amount)
            : $account->balanceDeltaForDebit($amount);

        $impacts[$accountId] = round(($impacts[$accountId] ?? 0) + $delta, 2);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function usesDoubleEntryFromAttributes(array $attributes): bool
    {
        return (int) ($attributes['from_account_id'] ?? 0) > 0 || (int) ($attributes['to_account_id'] ?? 0) > 0;
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
