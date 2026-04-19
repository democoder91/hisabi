<?php

namespace App\BusinessLogic;

use Carbon\Carbon;
use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Contracts\ReportManager as ReportManagerContract;
use Illuminate\Database\Eloquent\Builder;

class ReportManager implements ReportManagerContract
{
    protected $data = [];
    private Carbon $startDateModel;
    private Carbon $endDateModel;
    private Carbon $startDatePrevMonthModel;
    private Carbon $endDatePrevMonthModel;

    public function generate($startDate = null, $endDate = null)
    {
        $startDate = $startDate ?? now();
        $endDate = $endDate ?? now();

        $this->startDateModel = Carbon::parse($startDate)->startOfMonth();
        $this->endDateModel = Carbon::parse($endDate)->endOfMonth();
        $this->startDatePrevMonthModel = Carbon::parse($startDate)->subMonthNoOverflow()->startOfMonth();
        $this->endDatePrevMonthModel = Carbon::parse($endDate)->subMonthNoOverflow()->endOfMonth();

        $newCategoryIds = Category::query()
            ->whereNotNull('account_id')
            ->whereBetween('created_at', [$this->startDateModel, $this->endDateModel])
            ->pluck('id');

        $this->addSection('Overview', $this->getOverviewData());

        $ledgerCategories = Account::query()
            ->with('compatibilityCategory')
            ->whereHas('compatibilityCategory')
            ->get()
            ->filter(fn(Account $account) => $account->compatibilityCategory !== null)
            ->groupBy(fn(Account $account) => $account->compatibilityCategory?->type);

        foreach ($ledgerCategories as $type => $accounts) {
            $categoriesData = [];

            foreach ($accounts as $account) {
                $category = $account->compatibilityCategory;

                if (! $category) {
                    continue;
                }

                $totalCurrentMonth = $this->transactionsForLedgerCategory($account, $category)
                    ->whereBetween('created_at', [$this->startDateModel, $this->endDateModel])
                    ->sum('amount');
                $totalLastMonth = $this->transactionsForLedgerCategory($account, $category)
                    ->whereBetween('created_at', [$this->startDatePrevMonthModel, $this->endDatePrevMonthModel])
                    ->sum('amount');
                $change = ! $totalLastMonth ? '-' : number_format(($totalCurrentMonth / $totalLastMonth - 1) * 100, 2);

                if ($totalCurrentMonth == 0 && $totalLastMonth == 0) {
                    continue;
                }

                $categoriesData[] = [
                    'name' => $category->getLocalizedName(),
                    'total_current_month' => $totalCurrentMonth,
                    'total_previous_month' => $totalLastMonth,
                    'change' => $change,
                    'change_color' => $this->getChangeColor($change, $type),
                    'is_new' => $newCategoryIds->contains($category->id),
                ];
            }

            if ($categoriesData === []) {
                continue;
            }

            $this->addSection($type, $this->calculateAndAddAllBrandsData($categoriesData, $type));
        }

        return $this->data;
    }

    protected function addSection($sectionName, $data)
    {
        $this->data[$sectionName] = $data;
    }

    protected function getChangeColor($change, $type)
    {
        if ($change == '-') {
            return 'gray';
        }

        if ($type == Category::INCOME) {
            return $change >= 0 ? 'green' : 'red';
        }

        return $change >= 0 ? 'red' : 'green';
    }

    protected function calculateAndAddAllBrandsData($brandsData, $type)
    {
        $allCurrentMonth = array_reduce($brandsData, function ($carry, $item) {
            $carry += $item['total_current_month'];

            return $carry;
        });

        $allLastMonth = array_reduce($brandsData, function ($carry, $item) {
            $carry += $item['total_previous_month'];

            return $carry;
        });

        $change = ! $allLastMonth ? '-' : number_format(($allCurrentMonth / $allLastMonth - 1) * 100, 2);

        return array_merge([[
            'name' => 'All',
            'total_current_month' => $allCurrentMonth,
            'total_previous_month' => $allLastMonth,
            'change' => $change,
            'change_color' => $this->getChangeColor($change, $type)
        ]], $brandsData);
    }

    protected function getOverviewData()
    {
        return [
            $this->getTotalCash(),
            $this->getTotalIncome(),
            $this->getTotalExpenses(),
            $this->getTotalInvestment(),
            $this->getTotalSavings(),
        ];
    }

    protected function getTotalCash()
    {
        $totalIncome = $this->sumLedgerTypeBefore(Category::INCOME, $this->endDateModel);
        $totalExpenses = $this->sumLedgerTypeBefore(Category::EXPENSES, $this->endDateModel);
        $totalInvestment = $this->sumLedgerTypeBefore(Category::INVESTMENT, $this->endDateModel);
        $totalSavings = $this->sumLedgerTypeBefore(Category::SAVINGS, $this->endDateModel);

        $totalIncomeExcludingThisMonth = $this->sumLedgerTypeBefore(Category::INCOME, $this->startDateModel);
        $totalExpensesExcludingThisMonth = $this->sumLedgerTypeBefore(Category::EXPENSES, $this->startDateModel);
        $totalInvestmentExcludingThisMonth = $this->sumLedgerTypeBefore(Category::INVESTMENT, $this->startDateModel);
        $totalSavingsExcludingThisMonth = $this->sumLedgerTypeBefore(Category::SAVINGS, $this->startDateModel);

        $totalCashTillNow = $totalIncome - ($totalExpenses + $totalInvestment + $totalSavings);
        $totalCashExcludingThisMonth = $totalIncomeExcludingThisMonth - ($totalExpensesExcludingThisMonth + $totalInvestmentExcludingThisMonth + $totalSavingsExcludingThisMonth);

        $change = ! $totalCashExcludingThisMonth ? '-' : number_format(($totalCashTillNow / $totalCashExcludingThisMonth - 1) * 100, 2);

        return [
            'name' => 'Total Cash',
            'total_current_month' => $totalCashTillNow,
            'total_previous_month' => $totalCashExcludingThisMonth,
            'change' => $change,
            'change_color' => $this->getChangeColor($change, 'INCOME')
        ];
    }

    protected function getTotalIncome()
    {
        $total = $this->sumLedgerTypeBetween(Category::INCOME, $this->startDateModel, $this->endDateModel);
        $totalExcludingThisMonth = $this->sumLedgerTypeBetween(Category::INCOME, $this->startDatePrevMonthModel, $this->endDatePrevMonthModel);

        $change = ! $totalExcludingThisMonth ? '-' : number_format(($total / $totalExcludingThisMonth - 1) * 100, 2);

        return [
            'name' => 'Total Income',
            'total_current_month' => $total,
            'total_previous_month' => $totalExcludingThisMonth,
            'change' => $change,
            'change_color' => $this->getChangeColor($change, 'INCOME')
        ];
    }

    protected function getTotalExpenses()
    {
        $total = $this->sumLedgerTypeBetween(Category::EXPENSES, $this->startDateModel, $this->endDateModel);
        $totalExcludingThisMonth = $this->sumLedgerTypeBetween(Category::EXPENSES, $this->startDatePrevMonthModel, $this->endDatePrevMonthModel);

        $change = ! $totalExcludingThisMonth ? '-' : number_format(($total / $totalExcludingThisMonth - 1) * 100, 2);

        return [
            'name' => 'Total Expenses',
            'total_current_month' => $total,
            'total_previous_month' => $totalExcludingThisMonth,
            'change' => $change,
            'change_color' => $this->getChangeColor($change, 'EXPENSES')
        ];
    }

    protected function getTotalInvestment()
    {
        $total = $this->sumLedgerTypeBetween(Category::INVESTMENT, $this->startDateModel, $this->endDateModel);
        $totalExcludingThisMonth = $this->sumLedgerTypeBetween(Category::INVESTMENT, $this->startDatePrevMonthModel, $this->endDatePrevMonthModel);

        $change = ! $totalExcludingThisMonth ? '-' : number_format(($total / $totalExcludingThisMonth - 1) * 100, 2);

        return [
            'name' => 'Total Investment',
            'total_current_month' => $total,
            'total_previous_month' => $totalExcludingThisMonth,
            'change' => $change,
            'change_color' => $this->getChangeColor($change, 'INCOME')
        ];
    }

    protected function getTotalSavings()
    {
        $total = $this->sumLedgerTypeBetween(Category::SAVINGS, $this->startDateModel, $this->endDateModel);
        $totalExcludingThisMonth = $this->sumLedgerTypeBetween(Category::SAVINGS, $this->startDatePrevMonthModel, $this->endDatePrevMonthModel);

        $change = ! $totalExcludingThisMonth ? '-' : number_format(($total / $totalExcludingThisMonth - 1) * 100, 2);

        return [
            'name' => 'Total Savings',
            'total_current_month' => $total,
            'total_previous_month' => $totalExcludingThisMonth,
            'change' => $change,
            'change_color' => $this->getChangeColor($change, 'INCOME')
        ];
    }

    protected function transactionsForLedgerCategory(Account $account, Category $category): Builder
    {
        return Transaction::query()->where(function (Builder $query) use ($account, $category) {
            if ($category->type === Category::INCOME) {
                $query->where('from_account_id', $account->id)
                    ->where('transaction_type', Transaction::TYPE_CREDIT);

                return;
            }

            $query->where('to_account_id', $account->id)
                ->where('transaction_type', Transaction::TYPE_DEBIT);
        });
    }

    protected function ledgerTypeQuery(string $type): Builder
    {
        return Transaction::query()->where(function (Builder $query) use ($type) {
            if ($type === Category::INCOME) {
                $query->whereHas('fromAccount', fn(Builder $builder) => $builder->where('type', Account::TYPE_INCOME))
                    ->orWhereHas('category', fn(Builder $builder) => $builder->where('type', Category::INCOME));

                return;
            }

            if ($type === Category::EXPENSES) {
                $query->whereHas('toAccount', fn(Builder $builder) => $builder->where('type', Account::TYPE_EXPENSE))
                    ->orWhereHas('category', fn(Builder $builder) => $builder->where('type', Category::EXPENSES));

                return;
            }

            $query->whereHas('category', fn(Builder $builder) => $builder->where('type', $type));
        });
    }

    protected function sumLedgerTypeBetween(string $type, Carbon $startDate, Carbon $endDate): float
    {
        return (float) $this->ledgerTypeQuery($type)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');
    }

    protected function sumLedgerTypeBefore(string $type, Carbon $date): float
    {
        return (float) $this->ledgerTypeQuery($type)
            ->where('created_at', '<', $date)
            ->sum('amount');
    }
}
