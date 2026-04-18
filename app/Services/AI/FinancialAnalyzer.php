<?php

namespace App\Services\AI;

use App\Models\User;
use App\Scopes\OwnedAccountScope;
use App\Domains\Transaction\Models\Transaction;
use App\Domains\Category\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FinancialAnalyzer
{
    private const UNCATEGORIZED_LABEL = 'Uncategorized';

    /**
     * Generate a comprehensive financial summary for the user
     */
    public function generateSummary($user): string
    {
        $timeRange = now()->subMonths(3);

        $currency = $this->resolveCurrency($user);

        // Get basic metrics
        $totalIncome = $this->transactionQuery($user)
            ->income()
            ->where('created_at', '>=', $timeRange)
            ->sum('amount');

        $totalExpenses = $this->transactionQuery($user)
            ->expenses()
            ->where('created_at', '>=', $timeRange)
            ->sum('amount');

        $totalSavings = $this->transactionQuery($user)
            ->savings()
            ->where('created_at', '>=', $timeRange)
            ->sum('amount');

        $totalInvestment = $this->transactionQuery($user)
            ->investment()
            ->where('created_at', '>=', $timeRange)
            ->sum('amount');

        // Get category breakdown
        $expensesByCategory = $this->getExpensesByCategory($timeRange, $currency, $user);
        $incomeByCategory = $this->getIncomeByCategory($timeRange, $currency, $user);

        // Get monthly trends
        $monthlyTrends = $this->getMonthlyTrends($timeRange, $currency, $user);

        // Get top spending categories
        $topCategories = $this->getTopCategories($timeRange, 5, $currency, $user);

        // Build summary text
        $summary = <<<SUMMARY
**Financial Overview (Last 3 Months):**
- Total Income: {$currency} {$this->formatNumber($totalIncome)}
- Total Expenses: {$currency} {$this->formatNumber($totalExpenses)}
- Total Savings: {$currency} {$this->formatNumber($totalSavings)}
- Total Investment: {$currency} {$this->formatNumber($totalInvestment)}
- Net Cash Available: {$currency} {$this->formatNumber($totalIncome - ($totalExpenses + $totalSavings + $totalInvestment))}

**Expenses by Category:**
{$expensesByCategory}

**Income by Category:**
{$incomeByCategory}

**Top 5 Spending Categories:**
{$topCategories}

**Monthly Trends:**
{$monthlyTrends}

This data represents the user's actual financial transactions and should be used to provide personalized insights.
SUMMARY;
        
        return $summary;
    }
    
    /**
     * Get expenses grouped by category
     */
    protected function getExpensesByCategory($sinceDate, string $currency, ?User $user): string
    {
        $nameExpression = $this->categoryNameExpression();

        $expenses = $this->transactionQuery($user)
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw("{$nameExpression} as name")
            ->selectRaw('SUM(transactions.amount) as total')
            ->where('categories.type', Category::EXPENSES)
            ->where('transactions.created_at', '>=', $sinceDate)
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();

        if ($expenses->isEmpty()) {
            return "No expense data available.";
        }

        return $expenses->map(fn($exp) => "  - {$this->normalizeLabel($exp->name)}: {$currency} {$this->formatNumber($exp->total)}")
            ->join("\n");
    }
    
    /**
     * Get income grouped by category
     */
    protected function getIncomeByCategory($sinceDate, string $currency, ?User $user): string
    {
        $nameExpression = $this->categoryNameExpression();

        $income = $this->transactionQuery($user)
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw("{$nameExpression} as name")
            ->selectRaw('SUM(transactions.amount) as total')
            ->where('categories.type', Category::INCOME)
            ->where('transactions.created_at', '>=', $sinceDate)
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();

        if ($income->isEmpty()) {
            return "No income data available.";
        }

        return $income->map(fn($inc) => "  - {$this->normalizeLabel($inc->name)}: {$currency} {$this->formatNumber($inc->total)}")
            ->join("\n");
    }
    
    /**
     * Get monthly spending trends
     */
    protected function getMonthlyTrends($sinceDate, string $currency, ?User $user): string
    {
        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', transactions.created_at)"
            : 'DATE_FORMAT(transactions.created_at, "%Y-%m")';

        $expenseCondition = 'categories.type = "' . Category::EXPENSES . '"';
        $incomeCondition = 'categories.type = "' . Category::INCOME . '"';

        $trends = $this->transactionQuery($user)
            ->select(
                DB::raw("{$monthExpr} as month"),
                DB::raw("SUM(CASE WHEN {$expenseCondition} THEN transactions.amount ELSE 0 END) as expenses"),
                DB::raw("SUM(CASE WHEN {$incomeCondition} THEN transactions.amount ELSE 0 END) as income")
            )
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.created_at', '>=', $sinceDate)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        if ($trends->isEmpty()) {
            return "No trend data available.";
        }

        return $trends->map(function ($trend) use ($currency) {
            return "  - {$trend->month}: Income {$currency} {$this->formatNumber($trend->income)}, Expenses {$currency} {$this->formatNumber($trend->expenses)}";
        })->join("\n");
    }
    
    /**
     * Get top spending categories
     */
    protected function getTopCategories($sinceDate, int $limit = 5, string $currency = 'AED', ?User $user = null): string
    {
        $nameExpression = $this->categoryNameExpression();

        $categories = $this->transactionQuery($user)
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw("{$nameExpression} as name")
            ->selectRaw('SUM(transactions.amount) as total')
            ->where('categories.type', Category::EXPENSES)
            ->where('transactions.created_at', '>=', $sinceDate)
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        if ($categories->isEmpty()) {
            return "No category data available.";
        }

        return $categories->map(fn($category, $index) =>
            "  " . ($index + 1) . ". {$this->normalizeLabel($category->name)}: {$currency} {$this->formatNumber($category->total)}"
        )->join("\n");
    }

    protected function transactionQuery(?User $user): Builder
    {
        $query = Transaction::query()->withoutGlobalScope(OwnedAccountScope::class);

        if ($user) {
            $query->forAccessibleAccounts($user);
        }

        return $query;
    }

    protected function categoryNameExpression(): string
    {
        $driver = DB::connection()->getDriverName();
        $locale = app()->getLocale();

        if ($driver === 'sqlite') {
            $localeExpression = sprintf(
                "CASE WHEN json_valid(categories.name) THEN json_extract(categories.name, '$.\"%s\"') END",
                $locale,
            );
            $englishExpression = "CASE WHEN json_valid(categories.name) THEN json_extract(categories.name, '$.en') END";
            $plainExpression = 'CASE WHEN NOT json_valid(categories.name) THEN categories.name END';
        } else {
            $localeExpression = sprintf(
                'CASE WHEN JSON_VALID(categories.name) THEN JSON_UNQUOTE(JSON_EXTRACT(categories.name, "$.%s")) END',
                $locale,
            );
            $englishExpression = 'CASE WHEN JSON_VALID(categories.name) THEN JSON_UNQUOTE(JSON_EXTRACT(categories.name, "$.en")) END';
            $plainExpression = 'CASE WHEN NOT JSON_VALID(categories.name) THEN categories.name END';
        }

        return "COALESCE({$localeExpression}, {$englishExpression}, {$plainExpression}, '')";
    }
    
    /**
     * Resolve currency from user preference or system config
     */
    protected function resolveCurrency($user): string
    {
        if ($user && isset($user->default_currency) && $user->default_currency) {
            return $user->default_currency;
        }

        return config('hisabi.currency', 'AED');
    }

    /**
     * Format number with thousands separator
     */
    protected function formatNumber($number): string
    {
        return number_format($number, 2);
    }

    protected function normalizeLabel(?string $label): string
    {
        if (! $label) {
            return self::UNCATEGORIZED_LABEL;
        }

        $decoded = json_decode($label, true);

        if (! is_array($decoded)) {
            return $label;
        }

        $locale = app()->getLocale();

        return $decoded[$locale]
            ?? $decoded['en']
            ?? reset($decoded)
            ?? self::UNCATEGORIZED_LABEL;
    }

}
