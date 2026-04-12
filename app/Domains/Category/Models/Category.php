<?php

namespace App\Domains\Category\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Domains\Transaction\Models\Transaction;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use BelongsToUser, HasFactory, HasTranslations;

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

    public static function findOrCreateFallbackForUser(int $userId, string $type): self
    {
        $name = match ($type) {
            self::INCOME => ['en' => 'Uncategorized Income', 'ar' => null],
            self::SAVINGS => ['en' => 'Uncategorized Savings', 'ar' => null],
            self::INVESTMENT => ['en' => 'Uncategorized Investment', 'ar' => null],
            default => ['en' => 'Uncategorized Expenses', 'ar' => null],
        };

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
