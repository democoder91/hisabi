<?php

namespace App\Services\AI;

use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use App\Scopes\OwnedAccountScope;
use App\Services\Currency\CurrencyRateService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinancialAnalyzer
{
    private const UNKNOWN_LABEL = 'Unknown';

    /**
     * Generate a comprehensive financial summary for the user.
     */
    public function generateSummary($user): string
    {
        $timeRange = now()->subMonths(3);

        $currency = $this->resolveCurrency($user);
        $transactions = $this->transactionsSince($timeRange, $user);
        $incomeAccounts = $this->accountsByType($user, Account::TYPE_INCOME);
        $expenseAccounts = $this->accountsByType($user, Account::TYPE_EXPENSE);
        $assetAccounts = $this->accountsByType($user, Account::TYPE_ASSET);
        $liabilityAccounts = $this->accountsByType($user, Account::TYPE_LIABILITY);
        $equityAccounts = $this->accountsByType($user, Account::TYPE_EQUITY);

        $totalIncome = $this->sumAccountMovements($transactions, $incomeAccounts, $currency, $user);
        $totalExpenses = $this->sumAccountMovements($transactions, $expenseAccounts, $currency, $user);
        $totalAssets = $this->sumAccountMovements($transactions, $assetAccounts, $currency, $user);
        $totalLiabilities = $this->sumAccountMovements($transactions, $liabilityAccounts, $currency, $user);
        $totalEquity = $this->sumAccountMovements($transactions, $equityAccounts, $currency, $user);

        $expensesByAccount = $this->formatAccountBreakdown(
            $transactions,
            $expenseAccounts,
            $currency,
            $user,
            'No expense data available.'
        );
        $incomeByAccount = $this->formatAccountBreakdown(
            $transactions,
            $incomeAccounts,
            $currency,
            $user,
            'No income data available.'
        );
        $monthlyTrends = $this->getMonthlyTrends($transactions, $incomeAccounts, $expenseAccounts, $currency, $user);
        $topAccounts = $this->getTopAccounts($transactions, $expenseAccounts, 5, $currency, $user);

        return <<<SUMMARY
**Financial Overview (Last 3 Months):**
- Total Income: {$currency} {$this->formatNumber($totalIncome)}
- Total Expenses: {$currency} {$this->formatNumber($totalExpenses)}
- Total Assets: {$currency} {$this->formatNumber($totalAssets)}
- Total Liabilities: {$currency} {$this->formatNumber($totalLiabilities)}
- Total Equity: {$currency} {$this->formatNumber($totalEquity)}
- Net Position: {$currency} {$this->formatNumber($totalAssets - $totalLiabilities)}

**Expenses by Account:**
{$expensesByAccount}

**Income by Account:**
{$incomeByAccount}

**Top 5 Spending Accounts:**
{$topAccounts}

**Monthly Trends:**
{$monthlyTrends}

This data represents the user's accessible account movements and should be used to provide personalized insights.
SUMMARY;
    }

    protected function transactionsSince(CarbonInterface $sinceDate, ?User $user): Collection
    {
        return $this->transactionQuery($user)
            ->with(['account', 'fromAccount', 'toAccount'])
            ->where('transactions.created_at', '>=', $sinceDate)
            ->get();
    }

    protected function accountsByType(?User $user, string $type): Collection
    {
        $query = Account::query();

        if ($user) {
            $query->accessibleTo($user);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query->where('type', $type)->get();
    }

    protected function formatAccountBreakdown(
        Collection $transactions,
        Collection $accounts,
        string $currency,
        ?User $user,
        string $emptyMessage
    ): string {
        $items = $this->summariesForAccounts($transactions, $accounts, $currency, $user);

        if ($items->isEmpty()) {
            return $emptyMessage;
        }

        return $items->map(function (array $item) use ($currency) {
            return "  - {$item['name']}: {$currency} {$this->formatNumber($item['total'])}";
        })->join("\n");
    }

    protected function getMonthlyTrends(
        Collection $transactions,
        Collection $incomeAccounts,
        Collection $expenseAccounts,
        string $currency,
        ?User $user
    ): string {
        $trends = $transactions->groupBy(function (Transaction $transaction) {
            return $transaction->created_at->format('Y-m');
        })->sortKeys()->map(function (Collection $monthTransactions, string $month) use ($incomeAccounts, $expenseAccounts, $currency, $user) {
            return [
                'month' => $month,
                'income' => $this->sumAccountMovements($monthTransactions, $incomeAccounts, $currency, $user),
                'expenses' => $this->sumAccountMovements($monthTransactions, $expenseAccounts, $currency, $user),
            ];
        });

        if ($trends->isEmpty()) {
            return 'No trend data available.';
        }

        return $trends->map(function (array $trend) use ($currency) {
            return "  - {$trend['month']}: Income {$currency} {$this->formatNumber($trend['income'])}, Expenses {$currency} {$this->formatNumber($trend['expenses'])}";
        })->join("\n");
    }

    protected function getTopAccounts(
        Collection $transactions,
        Collection $accounts,
        int $limit = 5,
        string $currency = 'AED',
        ?User $user = null
    ): string {
        $summaries = $this->summariesForAccounts($transactions, $accounts, $currency, $user)
            ->take($limit)
            ->values();

        if ($summaries->isEmpty()) {
            return 'No account data available.';
        }

        return $summaries->map(function (array $account, int $index) use ($currency) {
            $position = $index + 1;

            return "  {$position}. {$account['name']}: {$currency} {$this->formatNumber($account['total'])}";
        })->join("\n");
    }

    protected function transactionQuery(?User $user): Builder
    {
        $query = Transaction::query()->withoutGlobalScope(OwnedAccountScope::class);

        if ($user) {
            $query->forAccessibleAccounts($user);
        }

        return $query;
    }

    /**
     * Resolve currency from user preference or system config.
     */
    protected function resolveCurrency($user): string
    {
        if ($user && isset($user->default_currency) && $user->default_currency) {
            return $user->default_currency;
        }

        return config('hisabi.currency', 'AED');
    }

    /**
     * Format number with thousands separator.
     */
    protected function formatNumber($number): string
    {
        return number_format($number, 2);
    }

    protected function accountLabel(Account $account): string
    {
        return $account->getLocalizedName() ?: self::UNKNOWN_LABEL;
    }

    protected function convertAmount(?User $user, float $amount, string $sourceCurrency, string $targetCurrency): float
    {
        if (! $user) {
            return round($amount, 2);
        }

        /** @var CurrencyRateService $currencyRateService */
        $currencyRateService = app(CurrencyRateService::class);

        return round($currencyRateService->convert($user, $amount, $sourceCurrency, $targetCurrency), 2);
    }

    protected function sumAccountMovements(Collection $transactions, Collection $accounts, string $currency, ?User $user): float
    {
        return round((float) $accounts->sum(function (Account $account) use ($transactions, $currency, $user) {
            return $transactions->sum(function (Transaction $transaction) use ($account, $currency, $user) {
                $movement = $transaction->movementForAccount($account);

                if ($movement === 0.0) {
                    return 0.0;
                }

                return $this->convertAmount($user, $movement, (string) $transaction->currency, $currency);
            });
        }), 2);
    }

    protected function summariesForAccounts(Collection $transactions, Collection $accounts, string $currency, ?User $user): Collection
    {
        return $accounts->map(function (Account $account) use ($transactions, $currency, $user) {
            return [
                'name' => $this->accountLabel($account),
                'total' => $this->sumAccountMovements($transactions, collect([$account]), $currency, $user),
            ];
        })->filter(function (array $item) {
            return abs((float) $item['total']) > 0.00001;
        })->sortByDesc('total')->values();
    }
}