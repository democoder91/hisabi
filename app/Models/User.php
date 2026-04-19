<?php

namespace App\Models;

use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const INITIAL_AVAILABLE_CREDITS = 10;
    public const TRIAL_PERIOD_DAYS = 30;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'default_currency',
        'locale',
        'available_credits',
        'trial_ends_at',
        'is_super',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'available_credits' => 'integer',
        'trial_ends_at' => 'datetime',
        'is_super' => 'boolean',
    ];

    public static function registrationAccessDefaults(): array
    {
        return [
            'available_credits' => self::INITIAL_AVAILABLE_CREDITS,
            'trial_ends_at' => now()->addDays(self::TRIAL_PERIOD_DAYS),
        ];
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function sharedAccounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class)
            ->wherePivotNull('deleted_at')
            ->withPivot('id', 'permission_level', 'deleted_at')
            ->withTimestamps();
    }

    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function hasActiveSubscription(): bool
    {
        if ($this->isSuperUser()) {
            return true;
        }

        return $this->subscriptions()->active()->exists();
    }

    public function hasActiveAccess(): bool
    {
        if ($this->isSuperUser()) {
            return true;
        }

        $hasActiveTrial = $this->trial_ends_at && $this->trial_ends_at->isFuture();

        return $this->hasActiveSubscription() || $hasActiveTrial;
    }

    public function isSuperUser(): bool
    {
        return $this->is_super === true;
    }

    public function getOrCreateDefaultAccount(): Account
    {
        $account = $this->accounts()
            ->whereIn(
                DB::raw(Account::localizedNameSqlExpression('en', false)),
                Account::LEGACY_DEFAULT_NAMES,
            )
            ->first();

        if ($account) {
            if (! $account->type) {
                $account->forceFill([
                    'type' => Account::TYPE_ASSET,
                ])->saveQuietly();
            }

            return $account;
        }

        return $this->accounts()->create([
            'name' => ['en' => Account::DEFAULT_NAME],
            'type' => Account::TYPE_ASSET,
            'balance' => 0,
            'currency' => $this->default_currency ?: config('hisabi.currency'),
        ]);
    }

    public function getOrCreateStartingBalanceAccount(): Account
    {
        $account = $this->accounts()
            ->where(
                DB::raw(Account::localizedNameSqlExpression('en', false)),
                Account::STARTING_BALANCE_NAME,
            )
            ->first();

        if ($account) {
            if ($account->type !== Account::TYPE_EQUITY) {
                $account->forceFill([
                    'type' => Account::TYPE_EQUITY,
                ])->saveQuietly();
            }

            return $account;
        }

        return $this->accounts()->create([
            'name' => ['en' => Account::STARTING_BALANCE_NAME],
            'type' => Account::TYPE_EQUITY,
            'balance' => 0,
            'currency' => $this->default_currency ?: config('hisabi.currency'),
            'color' => 'gray',
            'icon' => 'scale',
        ]);
    }

    public function getOrCreateUncategorizedExpenseAccount(): Account
    {
        return $this->getOrCreateNamedLedgerAccount(
            'Uncategorized Expenses',
            Account::TYPE_EXPENSE,
            'gray',
            'shapes',
        );
    }

    public function getOrCreateUncategorizedIncomeAccount(): Account
    {
        return $this->getOrCreateNamedLedgerAccount(
            'Uncategorized Income',
            Account::TYPE_INCOME,
            'gray',
            'shapes',
        );
    }

    private function getOrCreateNamedLedgerAccount(string $name, string $type, string $color, string $icon): Account
    {
        $account = $this->accounts()
            ->withTrashed()
            ->where(DB::raw(Account::localizedNameSqlExpression('en', false)), $name)
            ->first();

        if ($account) {
            if ($account->trashed()) {
                $account->restore();
            }

            $account->forceFill([
                'type' => $type,
                'color' => $account->color ?: $color,
                'icon' => $account->icon ?: $icon,
            ])->saveQuietly();

            return $account;
        }

        return $this->accounts()->create([
            'name' => ['en' => $name],
            'type' => $type,
            'balance' => 0,
            'currency' => $this->default_currency ?: config('hisabi.currency'),
            'color' => $color,
            'icon' => $icon,
        ]);
    }
}
