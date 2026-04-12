<?php

namespace App\Domains\Metrics;

use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Contracts\HasPreviousRange;
use App\Models\User;
use App\Services\Currency\CurrencyRateService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

abstract class Metric
{
    protected ?string $from = null;
    protected ?string $to = null;

    public function __construct(?string $from = null, ?string $to = null)
    {
        $this->from = $from;
        $this->to = $to;
    }

    abstract public function calculate(): array;

    protected function getStartDate(): ?string
    {
        return $this->from;
    }

    protected function getEndDate(): ?string
    {
        return $this->to;
    }

    protected function hasDateRange(): bool
    {
        return $this->from !== null && $this->to !== null;
    }

    protected function getPreviousRange(): ?array
    {
        if (!$this->hasDateRange()) {
            return null;
        }

        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);
        $daysDiff = $from->diffInDays($to) + 1;

        return [
            'start' => $from->copy()->subDays($daysDiff)->format('Y-m-d'),
            'end' => $from->copy()->subDay()->format('Y-m-d'),
        ];
    }

    protected function getDateFormat(string $format): string
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $sqliteFormat = str_replace(['%Y', '%m', '%d'], ['%Y', '%m', '%d'], $format);
            return "strftime('{$sqliteFormat}', created_at)";
        }
        return "date_format(created_at, '{$format}')";
    }

    protected function localizedJsonValueExpression(string $column): string
    {
        $locale = app()->getLocale() ?: 'en';
        $fallbackLocale = 'en';
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return sprintf(
                "coalesce(json_extract(%s, '$.%s'), json_extract(%s, '$.%s'))",
                $column,
                $locale,
                $column,
                $fallbackLocale
            );
        }

        return sprintf(
            "coalesce(JSON_UNQUOTE(JSON_EXTRACT(%s, '$.\"%s\"')), JSON_UNQUOTE(JSON_EXTRACT(%s, '$.\"%s\"')))",
            $column,
            $locale,
            $column,
            $fallbackLocale
        );
    }

    protected function localizedJsonSelect(string $column, string $alias = 'label'): string
    {
        return $this->localizedJsonValueExpression($column) . " as {$alias}";
    }

    protected function metricUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    protected function metricCurrency(): string
    {
        /** @var CurrencyRateService $currencyRateService */
        $currencyRateService = app(CurrencyRateService::class);

        return $currencyRateService->effectiveCurrency($this->metricUser());
    }

    protected function transactions(?callable $callback = null, ?array $range = null): Collection
    {
        $query = Transaction::query()->with('category');

        $effectiveRange = $range;

        if ($effectiveRange === null && $this->hasDateRange()) {
            $effectiveRange = [
                'start' => $this->getStartDate(),
                'end' => $this->getEndDate(),
            ];
        }

        if ($effectiveRange !== null) {
            $query->whereBetween('transactions.created_at', [$effectiveRange['start'], $effectiveRange['end']]);
        }

        if ($callback) {
            $callback($query);
        }

        return $query->get();
    }

    protected function convertedTransactionAmount(Transaction $transaction, ?string $targetCurrency = null): float
    {
        $user = $this->metricUser();

        if (! $user instanceof User) {
            return round((float) $transaction->amount, 2);
        }

        /** @var CurrencyRateService $currencyRateService */
        $currencyRateService = app(CurrencyRateService::class);

        return $currencyRateService->convert(
            $user,
            (float) $transaction->amount,
            $transaction->currency,
            $targetCurrency ?: $this->metricCurrency(),
        );
    }

    protected function sumConverted(Collection $transactions, ?string $targetCurrency = null): float
    {
        return round($transactions->sum(fn (Transaction $transaction) => $this->convertedTransactionAmount($transaction, $targetCurrency)), 2);
    }

    protected function categoryLabel(?Category $category): string
    {
        if (! $category) {
            return 'Unknown';
        }

        return $category->getTranslation('name', app()->getLocale(), false)
            ?: $category->getTranslation('name', 'en', false)
            ?: 'Unknown';
    }

    protected function valuePayload(float $value, ?float $previous = null): array
    {
        $payload = [
            'value' => round($value, 2),
            'currency' => $this->metricCurrency(),
        ];

        if ($previous !== null) {
            $payload['previous'] = round($previous, 2);
        }

        return $payload;
    }

    protected function itemsPayload(array $items): array
    {
        return [
            'items' => $items,
            'currency' => $this->metricCurrency(),
        ];
    }
}
