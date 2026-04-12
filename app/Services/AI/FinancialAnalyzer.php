<?php

namespace App\Services\AI;

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
        $totalIncome = Transaction::income()
            ->where('created_at', '>=', $timeRange)
            ->sum('amount');
            
        $totalExpenses = Transaction::expenses()
            ->where('created_at', '>=', $timeRange)
            ->sum('amount');
            
        $totalSavings = Transaction::savings()
            ->where('created_at', '>=', $timeRange)
            ->sum('amount');
            
        $totalInvestment = Transaction::investment()
            ->where('created_at', '>=', $timeRange)
            ->sum('amount');
        
        // Get category breakdown
        $expensesByCategory = $this->getExpensesByCategory($timeRange, $currency);
        $incomeByCategory = $this->getIncomeByCategory($timeRange, $currency);
        
        // Get monthly trends
        $monthlyTrends = $this->getMonthlyTrends($timeRange, $currency);
        
        // Get top spending categories
        $topCategories = $this->getTopCategories($timeRange, 5, $currency);
        
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
    protected function getExpensesByCategory($sinceDate, string $currency): string
    {
        $expenses = Transaction::query()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw("COALESCE(categories.name, ?) as name", [self::UNCATEGORIZED_LABEL])
            ->selectRaw('SUM(transactions.amount) as total')
            ->where('categories.type', Category::EXPENSES)
            ->where('transactions.created_at', '>=', $sinceDate)
            ->groupByRaw("COALESCE(categories.name, ?)", [self::UNCATEGORIZED_LABEL])
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
    protected function getIncomeByCategory($sinceDate, string $currency): string
    {
        $income = Transaction::query()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw("COALESCE(categories.name, ?) as name", [self::UNCATEGORIZED_LABEL])
            ->selectRaw('SUM(transactions.amount) as total')
            ->where('categories.type', Category::INCOME)
            ->where('transactions.created_at', '>=', $sinceDate)
            ->groupByRaw("COALESCE(categories.name, ?)", [self::UNCATEGORIZED_LABEL])
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
    protected function getMonthlyTrends($sinceDate, string $currency): string
    {
        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', transactions.created_at)"
            : 'DATE_FORMAT(transactions.created_at, "%Y-%m")';

        $expenseCondition = 'categories.type = "' . Category::EXPENSES . '"';
        $incomeCondition = 'categories.type = "' . Category::INCOME . '"';

        $trends = Transaction::select(
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
        
        return $trends->map(function($trend) use ($currency) {
            return "  - {$trend->month}: Income {$currency} {$this->formatNumber($trend->income)}, Expenses {$currency} {$this->formatNumber($trend->expenses)}";
        })->join("\n");
    }
    
    /**
     * Get top spending categories
     */
    protected function getTopCategories($sinceDate, int $limit = 5, string $currency = 'AED'): string
    {
        $categories = Transaction::query()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw("COALESCE(categories.name, ?) as name", [self::UNCATEGORIZED_LABEL])
            ->selectRaw('SUM(transactions.amount) as total')
            ->where('categories.type', Category::EXPENSES)
            ->where('transactions.created_at', '>=', $sinceDate)
            ->groupByRaw("COALESCE(categories.name, ?)", [self::UNCATEGORIZED_LABEL])
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
