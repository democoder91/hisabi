<?php

namespace App\BusinessLogic;

use Carbon\Carbon;
use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use App\Contracts\ReportManager as ReportManagerContract;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class ReportManager implements ReportManagerContract
{
    protected array $data = [];
    private Carbon $startDateModel;
    private Carbon $endDateModel;
    private Carbon $startDatePrevMonthModel;
    private Carbon $endDatePrevMonthModel;
    private ?User $reportUser = null;

    public function generate($startDate = null, $endDate = null, $user = null)
    {
        $this->data = [];
        $startDate = $startDate ?? now();
        $endDate = $endDate ?? now();
        $this->reportUser = $user instanceof User ? $user : null;

        $this->startDateModel = Carbon::parse($startDate)->startOfMonth();
        $this->endDateModel = Carbon::parse($endDate)->endOfMonth();
        $this->startDatePrevMonthModel = Carbon::parse($startDate)->subMonthNoOverflow()->startOfMonth();
        $this->endDatePrevMonthModel = Carbon::parse($endDate)->subMonthNoOverflow()->endOfMonth();

        $newAccountIds = $this->reportAccounts()
            ->whereBetween('created_at', [$this->startDateModel, $this->endDateModel])
            ->pluck('id');

        $this->addSection('Overview', $this->getOverviewData());

        $ledgerAccounts = $this->reportAccounts()
            ->get()
            ->groupBy(fn(Account $account) => $account->type);

        foreach ($this->sectionLabels() as $type => $sectionName) {
            $accounts = $ledgerAccounts->get($type, collect());
            $accountsData = [];

            foreach ($accounts as $account) {
                $totalCurrentMonth = $this->sumAccountMovementBetween($account, $this->startDateModel, $this->endDateModel);
                $totalLastMonth = $this->sumAccountMovementBetween($account, $this->startDatePrevMonthModel, $this->endDatePrevMonthModel);
                $change = ! $totalLastMonth ? '-' : number_format(($totalCurrentMonth / $totalLastMonth - 1) * 100, 2);

                if ($totalCurrentMonth == 0 && $totalLastMonth == 0) {
                    continue;
                }

                $accountsData[] = [
                    'name' => $account->getLocalizedName() ?: 'Unnamed account',
                    'total_current_month' => $totalCurrentMonth,
                    'total_previous_month' => $totalLastMonth,
                    'change' => $change,
                    'change_color' => $this->getChangeColor($change, $type),
                    'is_new' => $newAccountIds->contains($account->id),
                ];
            }

            if ($accountsData === []) {
                continue;
            }

            $this->addSection($sectionName, $this->calculateAndAddAllBrandsData($accountsData, $type));
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

        if (in_array($type, [Account::TYPE_INCOME, Account::TYPE_ASSET, Account::TYPE_EQUITY], true)) {
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
        return array_values(array_filter([
            $this->buildOverviewRow('Total Assets', Account::TYPE_ASSET),
            $this->buildOverviewRow('Total Liabilities', Account::TYPE_LIABILITY),
            $this->buildOverviewRow('Total Equity', Account::TYPE_EQUITY),
            $this->getTotalIncome(),
            $this->getTotalExpenses(),
        ], fn(array $row) => (float) $row['total_current_month'] !== 0.0 || (float) $row['total_previous_month'] !== 0.0));
    }

    protected function getTotalIncome()
    {
        $total = $this->sumAccountTypeBetween(Account::TYPE_INCOME, $this->startDateModel, $this->endDateModel);
        $totalExcludingThisMonth = $this->sumAccountTypeBetween(Account::TYPE_INCOME, $this->startDatePrevMonthModel, $this->endDatePrevMonthModel);

        $change = ! $totalExcludingThisMonth ? '-' : number_format(($total / $totalExcludingThisMonth - 1) * 100, 2);

        return [
            'name' => 'Total Income',
            'total_current_month' => $total,
            'total_previous_month' => $totalExcludingThisMonth,
            'change' => $change,
            'change_color' => $this->getChangeColor($change, Account::TYPE_INCOME)
        ];
    }

    protected function getTotalExpenses()
    {
        $total = $this->sumAccountTypeBetween(Account::TYPE_EXPENSE, $this->startDateModel, $this->endDateModel);
        $totalExcludingThisMonth = $this->sumAccountTypeBetween(Account::TYPE_EXPENSE, $this->startDatePrevMonthModel, $this->endDatePrevMonthModel);

        $change = ! $totalExcludingThisMonth ? '-' : number_format(($total / $totalExcludingThisMonth - 1) * 100, 2);

        return [
            'name' => 'Total Expenses',
            'total_current_month' => $total,
            'total_previous_month' => $totalExcludingThisMonth,
            'change' => $change,
            'change_color' => $this->getChangeColor($change, Account::TYPE_EXPENSE)
        ];
    }

    protected function buildOverviewRow(string $name, string $type): array
    {
        $total = $this->sumAccountTypeBetween($type, $this->startDateModel, $this->endDateModel);
        $totalExcludingThisMonth = $this->sumAccountTypeBetween($type, $this->startDatePrevMonthModel, $this->endDatePrevMonthModel);

        $change = ! $totalExcludingThisMonth ? '-' : number_format(($total / $totalExcludingThisMonth - 1) * 100, 2);

        return [
            'name' => $name,
            'total_current_month' => $total,
            'total_previous_month' => $totalExcludingThisMonth,
            'change' => $change,
            'change_color' => $this->getChangeColor($change, $type),
        ];
    }

    protected function sectionLabels(): array
    {
        return [
            Account::TYPE_INCOME => 'Income Accounts',
            Account::TYPE_EXPENSE => 'Expense Accounts',
            Account::TYPE_ASSET => 'Asset Accounts',
            Account::TYPE_LIABILITY => 'Liability Accounts',
            Account::TYPE_EQUITY => 'Equity Accounts',
        ];
    }

    protected function reportAccounts(): Builder
    {
        return Account::query()->accessibleTo($this->reportUser);
    }

    protected function transactionsForAccount(Account $account): Builder
    {
        return Transaction::query()
            ->forAccessibleAccounts($this->reportUser)
            ->with(['account', 'fromAccount', 'toAccount'])
            ->where(function (Builder $query) use ($account) {
                $query->where('account_id', $account->id)
                    ->orWhere('from_account_id', $account->id)
                    ->orWhere('to_account_id', $account->id);
            });
    }

    protected function sumAccountMovementBetween(Account $account, Carbon $startDate, Carbon $endDate): float
    {
        return round((float) $this->transactionsForAccount($account)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->sum(fn(Transaction $transaction) => $this->accountMovementForTransaction($account, $transaction)), 2);
    }

    protected function accountMovementForTransaction(Account $account, Transaction $transaction): float
    {
        if ($transaction->usesDoubleEntry()) {
            if ((int) $transaction->from_account_id === (int) $account->id) {
                return $account->balanceDeltaForCredit((float) $transaction->amount);
            }

            if ((int) $transaction->to_account_id === (int) $account->id) {
                return $account->balanceDeltaForDebit((float) $transaction->amount);
            }

            return 0.0;
        }

        if ((int) $transaction->account_id !== (int) $account->id) {
            return 0.0;
        }

        return Transaction::signedAmountFromValues(
            (float) $transaction->amount,
            (string) $transaction->transaction_type,
        );
    }

    protected function sumAccountTypeBetween(string $type, Carbon $startDate, Carbon $endDate): float
    {
        return round((float) $this->reportAccounts()
            ->where('type', $type)
            ->get()
            ->sum(fn(Account $account) => $this->sumAccountMovementBetween($account, $startDate, $endDate)), 2);
    }
}
