<?php

namespace App\Domains\Budget\Models;

use App\Domains\Category\Models\Category;
use App\Models\Concerns\BelongsToUser;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Budget extends Model
{
    use BelongsToUser, HasFactory, HasTranslations, SoftDeletes;

    public array $translatable = ['name'];

    const CUSTOM = "CUSTOM";
    const DAILY = "DAILY";
    const WEEKLY = "WEEKLY";
    const MONTHLY = "MONTHLY";
    const YEARLY = "YEARLY";

    protected $guarded = [];

    protected $appends = [
        'is_saving',
        'total_spent_percentage',
        'total_margin_per_day',
        'start_at_date',
        'end_at_date',
        'total_transactions_amount',
        'remaining_days',
        'remaining_to_spend',
        'elapsed_days_percentage',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'saving' => 'boolean',
        'amount' => 'float',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->wherePivotNull('deleted_at')
            ->withPivot('id', 'deleted_at')
            ->withTimestamps();
    }

    public function getIsSavingAttribute(): bool
    {
        return $this->saving;
    }

    public function getLocalizedName(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $this->getTranslation('name', $locale, false)
            ?: $this->getTranslation('name', 'en', false);
    }

    public function getTotalSpentPercentageAttribute(): string
    {
        return (int) number_format($this->totalTransactionsAmount / $this->amount * 100, 2);
    }

    public function getTotalMarginPerDayAttribute(): string
    {
        $days = now()->diffInDays($this->end_at_date);
        $remainingAmount = $this->amount - $this->totalTransactionsAmount;

        if ($days < 0 || $remainingAmount <= 0) {
            return 0;
        }

        return $days == 0 ? number_format($remainingAmount, 2) : number_format($remainingAmount / $days, 2);
    }

    public function getRemainingDaysAttribute(): float
    {
        return now()->diffInDays($this->end_at_date);
    }

    public function getRemainingToSpendAttribute(): string
    {
        return $this->amount - $this->totalTransactionsAmount;
    }

    public function getElapsedDaysPercentageAttribute(): int
    {
        [$startAt, $endAt] = $this->getCurrentWindowStartAndEndDates();
        $totalDays = $startAt->diffInDays($endAt);

        if ($totalDays == 0) {
            return 0;
        }

        $elapsedDays = $startAt->diffInDays(now());
        $percentage = ($elapsedDays / $totalDays) * 100;

        return (int) max(0, min(100, $percentage));
    }

    public function getStartAtDateAttribute(): string
    {
        return $this->getCurrentWindowStartAndEndDates()[0]->format('Y-m-d');
    }

    public function getEndAtDateAttribute(): string
    {
        return $this->getCurrentWindowStartAndEndDates()[1]->format('Y-m-d');
    }

    public function getTotalTransactionsAmountAttribute()
    {
        [$startAt, $endAt] = $this->getCurrentWindowStartAndEndDates();

        return $this->categories()
            ->join('transactions', 'categories.id', '=', 'transactions.category_id')
            ->where('categories.user_id', $this->user_id)
            ->whereBetween('transactions.created_at', [$startAt, $endAt])
            ->sum('transactions.amount');
    }

    private function getCurrentWindowStartAndEndDates()
    {
        if ($this->reoccurrence === self::CUSTOM) {
            return [$this->start_at, $this->end_at ?? $this->start_at];
        }

        $unit = $this->getUnitMapping();
        $startAt = $this->start_at->copy()->startOfDay();

        if (now()->isBefore($startAt)) {
            return [$startAt, $startAt->copy()->add($unit, $this->period)];
        }

        $intervalString = $this->period . ' ' . $unit;
        $ranges = CarbonPeriod::create($startAt, $intervalString, now()->copy()->add($unit, $this->period))->toArray();

        foreach (array_reverse($ranges) as $range) {
            if (now()->isAfter($range)) {
                return [$range->copy(), $range->copy()->add($unit, $this->period)];
            }
        }

        return [$startAt, $startAt->copy()->add($unit, $this->period)];
    }

    private function getUnitMapping(): string
    {
        return [
            self::DAILY => 'day',
            self::WEEKLY => 'week',
            self::MONTHLY => 'month',
            self::YEARLY => 'year',
        ][$this->reoccurrence];
    }

    protected static function newFactory()
    {
        return \Database\Factories\BudgetFactory::new();
    }
}
