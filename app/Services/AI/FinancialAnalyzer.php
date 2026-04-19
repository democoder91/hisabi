<?php

namespace App\Services\AI;

use App\Domains\Account\Models\Account;
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
        $totalIncome = $this->sumByLedgerType($timeRange, Category::INCOME, $user);
        $totalExpenses = $this->sumByLedgerType($timeRange, Category::EXPENSES, $user);
        $totalSavings = $this->sumByLedgerType($timeRange, Category::SAVINGS, $user);
        $totalInvestment = $this->sumByLedgerType($timeRange, Category::INVESTMENT, $user);

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
        $nameExpression = $this->breakdownNameExpression();

        $expenses = $this->ledgerBreakdownQuery($sinceDate, Category::EXPENSES, $user)
            ->selectRaw("{$nameExpression} as name")
            ->selectRaw('SUM(transactions.amount) as total')
            ->groupByRaw($nameExpression)
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
        $nameExpression = $this->breakdownNameExpression();

        $income = $this->ledgerBreakdownQuery($sinceDate, Category::INCOME, $user)
            ->selectRaw("{$nameExpression} as name")
            ->selectRaw('SUM(transactions.amount) as total')
            ->groupByRaw($nameExpression)
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

        $expenseCondition = $this->ledgerTypeSql(Category::EXPENSES);
        $incomeCondition = $this->ledgerTypeSql(Category::INCOME);

        $trends = $this->transactionQuery($user)
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->leftJoin('accounts as from_accounts', 'transactions.from_account_id', '=', 'from_accounts.id')
            ->leftJoin('accounts as to_accounts', 'transactions.to_account_id', '=', 'to_accounts.id')
            ->select(
                DB::raw("{$monthExpr} as month"),
                DB::raw("SUM(CASE WHEN {$expenseCondition} THEN transactions.amount ELSE 0 END) as expenses"),
                DB::raw("SUM(CASE WHEN {$incomeCondition} THEN transactions.amount ELSE 0 END) as income")
            )
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
        $nameExpression = $this->breakdownNameExpression();

        $categories = $this->ledgerBreakdownQuery($sinceDate, Category::EXPENSES, $user)
            ->selectRaw("{$nameExpression} as name")
            ->selectRaw('SUM(transactions.amount) as total')
            ->groupByRaw($nameExpression)
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
        return $this->localizedNameExpression('categories.name');
    }

    protected function breakdownNameExpression(): string
    {
        $categoryName = $this->localizedNameExpression('categories.name');
        $toAccountName = $this->localizedNameExpression('to_accounts.name');
        $fromAccountName = $this->localizedNameExpression('from_accounts.name');

        return "COALESCE({$categoryName}, {$toAccountName}, {$fromAccountName}, '')";
    }

    protected function localizedNameExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();
        $locale = app()->getLocale();

        if ($driver === 'sqlite') {
            $localeExpression = sprintf(
                "CASE WHEN json_valid(%s) THEN json_extract(%s, '$.\"%s\"') END",
                $column,
                $column,
                $locale,
            );
            $englishExpression = sprintf("CASE WHEN json_valid(%s) THEN json_extract(%s, '$.en') END", $column, $column);
            $plainExpression = sprintf('CASE WHEN NOT json_valid(%s) THEN %s END', $column, $column);
        } else {
            $localeExpression = sprintf(
                'CASE WHEN JSON_VALID(%s) THEN JSON_UNQUOTE(JSON_EXTRACT(%s, "$.%s")) END',
                $column,
                $column,
                $locale,
            );
            $englishExpression = sprintf('CASE WHEN JSON_VALID(%s) THEN JSON_UNQUOTE(JSON_EXTRACT(%s, "$.en")) END', $column, $column);
            $plainExpression = sprintf('CASE WHEN NOT JSON_VALID(%s) THEN %s END', $column, $column);
        }

        return "COALESCE({$localeExpression}, {$englishExpression}, {$plainExpression}, '')";
    }

    protected function ledgerBreakdownQuery($sinceDate, string $ledgerType, ?User $user): Builder
    {
        $query = $this->transactionQuery($user)
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->leftJoin('accounts as from_accounts', 'transactions.from_account_id', '=', 'from_accounts.id')
            ->leftJoin('accounts as to_accounts', 'transactions.to_account_id', '=', 'to_accounts.id')
            ->where('transactions.created_at', '>=', $sinceDate);

        return $query->whereRaw($this->ledgerTypeSql($ledgerType));
    }

    protected function sumByLedgerType($sinceDate, string $ledgerType, ?User $user): float
    {
        return (float) $this->ledgerBreakdownQuery($sinceDate, $ledgerType, $user)->sum('transactions.amount');
    }

    protected function ledgerTypeSql(string $ledgerType): string
    {
        return match ($ledgerType) {
            Category::INCOME => "(categories.type = 'INCOME' OR from_accounts.type = '" . Account::TYPE_INCOME . "')",
            Category::EXPENSES => "(categories.type = 'EXPENSES' OR to_accounts.type = '" . Account::TYPE_EXPENSE . "')",
            Category::SAVINGS => "categories.type = 'SAVINGS'",
            Category::INVESTMENT => "categories.type = 'INVESTMENT'",
            default => '1 = 0',
        };
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
