<?php

namespace App\Domains\Account\Models;

use App\Domains\Transaction\Models\TransactionAudit;
use App\Domains\Transaction\Models\Transaction;
use App\Models\Concerns\HasLocalizedTranslatableName;
use App\Models\User;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Translatable\HasTranslations;

class Account extends Model
{
    use HasFactory, HasLocalizedTranslatableName, HasTranslations, SoftDeletes;

    public const DEFAULT_NAME = 'Checking';
    public const PERMISSION_VIEW = 'view';
    public const PERMISSION_EDIT = 'edit';

    public array $translatable = ['name'];

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
            ->wherePivotNull('deleted_at')
            ->withPivot('id', 'permission_level', 'deleted_at')
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
            $sharedUser = $this->sharedUsers->firstWhere('id', $user->id);

            return $sharedUser && $sharedUser->pivot
                ? $sharedUser->pivot->permission_level
                : null;
        }

        return $this->sharedUsers()
            ->where('users.id', $user->id)
            ->value('permission_level');
    }

    public function participantUserIds(): array
    {
        $participantIds = [(int) $this->user_id];

        if ($this->relationLoaded('sharedUsers')) {
            $participantIds = [
                ...$participantIds,
                ...$this->sharedUsers->pluck('id')->map(fn(mixed $id) => (int) $id)->all(),
            ];
        } else {
            $participantIds = [
                ...$participantIds,
                ...$this->sharedUsers()->pluck('users.id')->map(fn(mixed $id) => (int) $id)->all(),
            ];
        }

        return array_values(array_unique($participantIds));
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

    public static function localizedNameSqlExpression(string $locale, bool $fallbackToEnglish = true): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $localeExpression = sprintf(
                "CASE WHEN json_valid(name) THEN json_extract(name, '$.\"%s\"') END",
                $locale,
            );
            $englishExpression = "CASE WHEN json_valid(name) THEN json_extract(name, '$.en') END";
            $plainExpression = "CASE WHEN NOT json_valid(name) THEN name END";
        } else {
            $localeExpression = sprintf(
                'CASE WHEN JSON_VALID(name) THEN JSON_UNQUOTE(JSON_EXTRACT(name, "$.%s")) END',
                $locale,
            );
            $englishExpression = 'CASE WHEN JSON_VALID(name) THEN JSON_UNQUOTE(JSON_EXTRACT(name, "$.en")) END';
            $plainExpression = 'CASE WHEN NOT JSON_VALID(name) THEN name END';
        }

        if ($fallbackToEnglish) {
            return "COALESCE({$localeExpression}, {$englishExpression}, {$plainExpression}, '')";
        }

        return "COALESCE({$localeExpression}, {$plainExpression}, '')";
    }
}
