<?php

namespace App\Domains\Category\Models;

use App\Domains\Account\Models\Account;
use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\HasLocalizedTranslatableName;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Domains\Transaction\Models\Transaction;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use BelongsToUser, HasFactory, HasLocalizedTranslatableName, HasTranslations, SoftDeletes;

    public array $translatable = ['name'];

    const INCOME = "INCOME";
    const EXPENSES = "EXPENSES";
    const SAVINGS = "SAVINGS";
    const INVESTMENT = "INVESTMENT";

    protected $guarded = [];

    protected static function newFactory(): Factory
    {
        return CategoryFactory::new();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }

    public static function findOrCreateFallbackForUser(int $userId, string $type): self
    {
        switch ($type) {
            case self::INCOME:
                $name = ['en' => 'Uncategorized Income', 'ar' => null];
                break;
            case self::SAVINGS:
                $name = ['en' => 'Uncategorized Savings', 'ar' => null];
                break;
            case self::INVESTMENT:
                $name = ['en' => 'Uncategorized Investment', 'ar' => null];
                break;
            default:
                $name = ['en' => 'Uncategorized Expenses', 'ar' => null];
                break;
        }

        return static::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'type' => $type,
                'name' => $name,
            ],
            [
                'color' => 'gray',
                'icon' => 'shapes',
            ],
        );
    }
}
