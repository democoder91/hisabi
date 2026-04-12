<?php

namespace App\Models;

use App\Domains\Account\Models\Account;
use App\Domains\Brand\Models\Brand;
use App\Domains\Budget\Models\Budget;
use App\Domains\Category\Models\Category as DomainCategory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

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
    ];

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
            ->withPivot('permission_level')
            ->withTimestamps();
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(DomainCategory::class);
    }

    public function getOrCreateDefaultAccount(): Account
    {
        $account = $this->accounts()
            ->whereRaw(Account::localizedNameSqlExpression('en', false) . ' = ?', [Account::DEFAULT_NAME])
            ->first();

        if ($account) {
            return $account;
        }

        return $this->accounts()->create([
            'name' => ['en' => Account::DEFAULT_NAME],
            'balance' => 0,
        ]);
    }
}
