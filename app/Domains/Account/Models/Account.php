<?php

namespace App\Domains\Account\Models;

use App\Domains\Transaction\Models\TransactionAudit;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    public const DEFAULT_NAME = 'Checking';
    public const PERMISSION_VIEW = 'view';
    public const PERMISSION_EDIT = 'edit';

    protected $guarded = [];

    protected $casts = [
        'balance' => 'float',
    ];

    protected static function newFactory(): Factory
    {
        return AccountFactory::new();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function transactionAudits(): HasMany
    {
        return $this->hasMany(TransactionAudit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('permission_level')
            ->withTimestamps();
    }

    public function scopeAccessibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) use ($user) {
            $builder->where('user_id', $user->id)
                ->orWhereHas('sharedUsers', function (Builder $sharedQuery) use ($user) {
                    $sharedQuery->where('users.id', $user->id);
                });
        });
    }

    public function isOwnedBy(?User $user): bool
    {
        return (bool) $user && (int) $this->user_id === (int) $user->id;
    }

    public function permissionLevelFor(?User $user): ?string
    {
        if (! $user || $this->isOwnedBy($user)) {
            return null;
        }

        if ($this->relationLoaded('sharedUsers')) {
            return $this->sharedUsers->firstWhere('id', $user->id)?->pivot?->permission_level;
        }

        return $this->sharedUsers()
            ->where('users.id', $user->id)
            ->value('permission_level');
    }

    public function canBeViewedBy(?User $user): bool
    {
        return $this->isOwnedBy($user) || $this->permissionLevelFor($user) !== null;
    }

    public function canBeEditedBy(?User $user): bool
    {
        return $this->isOwnedBy($user) || $this->permissionLevelFor($user) === self::PERMISSION_EDIT;
    }

    public function applyBalanceDelta(float $delta): void
    {
        $this->forceFill([
            'balance' => round((float) $this->balance + $delta, 2),
        ])->saveQuietly();
    }
}