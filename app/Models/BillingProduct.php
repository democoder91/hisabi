<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingProduct extends Model
{
    use HasFactory;

    public const TYPE_CREDITS = 'credits';
    public const TYPE_SUBSCRIPTION = 'subscription';

    protected $fillable = [
        'type',
        'slug',
        'name',
        'name_en',
        'name_ar',
        'currency',
        'price_cents',
        'price',
        'credits',
        'renews_in_days',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'price' => 'integer',
        'credits' => 'integer',
        'renews_in_days' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function localizedName(?string $locale = null): string
    {
        $activeLocale = $locale ?: app()->getLocale();

        if ($activeLocale === 'ar' && $this->name_ar) {
            return $this->name_ar;
        }

        if ($this->name_en) {
            return $this->name_en;
        }

        return (string) $this->name;
    }

    public function paymobAmountCents(): int
    {
        if ($this->price !== null) {
            return (int) $this->price * 100;
        }

        return (int) $this->price_cents;
    }
}
