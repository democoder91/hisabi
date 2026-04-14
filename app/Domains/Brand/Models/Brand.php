<?php

namespace App\Domains\Brand\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Category;
use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\HasLocalizedTranslatableName;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Translatable\HasTranslations;
use Illuminate\Support\Facades\Auth;

class Brand extends Model
{
    use BelongsToUser, HasFactory, HasLocalizedTranslatableName, HasTranslations;

    public array $translatable = ['name'];

    protected $guarded = [];

    protected static function newFactory(): Factory
    {
        return BrandFactory::new();
    }

    protected static function booted()
    {
        static::deleted(function ($brand) {
            $brand->transactions()->delete();
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class)->withoutGlobalScopes();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function transactionsCount()
    {
        return $this->transactions()->count();
    }

    public static function findOrCreateNew($name)
    {
        foreach(static::get() as $knownBrand) {
            $knownBrandName = strtolower($knownBrand->getLocalizedName() ?? '');

            if($knownBrandName !== '' && str_contains(strtolower($name), $knownBrandName)) {
                return $knownBrand;
            }
        }

        return static::create([
            'name' => ['en' => $name],
            'user_id' => Auth::id(),
        ]);
    }

    public static function search($query): Builder
    {
        return (new static())->newQuery()
            ->where('name', 'LIKE', "%$query%")
            ->orWhereHas('category', function($builder) use($query) {
                return $builder->where('name', 'LIKE', "%$query%");
            });
    }
}
