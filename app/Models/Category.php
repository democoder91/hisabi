<?php

namespace App\Models;

use App\Contracts\Searchable;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Domains\Transaction\Models\Transaction;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Category extends Model implements Searchable
{
    use BelongsToUser, HasFactory, HasTranslations;

    public array $translatable = ['name'];

    const INCOME = "INCOME";
    const EXPENSES = "EXPENSES";
    const SAVINGS = "SAVINGS";
    const INVESTMENT = "INVESTMENT";

    protected $guarded = [];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @param $query
     * @return Builder
     */
    public static function search($query): Builder
    {
        return (new static())->newQuery()
            ->where('name', 'LIKE', "%$query%");
    }
}
