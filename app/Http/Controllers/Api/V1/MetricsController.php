<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domains\Metrics\Metrics\TotalIncomeMetric;
use App\Domains\Metrics\Metrics\TotalExpensesMetric;
use App\Domains\Metrics\Metrics\TotalAssetsMetric;
use App\Domains\Metrics\Metrics\TotalLiabilitiesMetric;
use App\Domains\Metrics\Metrics\TotalEquityMetric;
use App\Domains\Metrics\Metrics\NetWorthMetric;
use App\Domains\Metrics\Metrics\NetWorthTrendMetric;
use App\Domains\Metrics\Metrics\TotalIncomeTrendMetric;
use App\Domains\Metrics\Metrics\TotalExpensesTrendMetric;
use App\Domains\Metrics\Metrics\AccountTrendMetric;
use App\Domains\Metrics\Metrics\AccountDailyTrendMetric;
use App\Domains\Metrics\Metrics\ExpensesByAccountMetric;
use App\Domains\Metrics\Metrics\IncomeByAccountMetric;
use App\Domains\Metrics\Metrics\TransactionsCountMetric;
use App\Domains\Metrics\Metrics\HighestTransactionMetric;
use App\Domains\Metrics\Metrics\LowestTransactionMetric;
use App\Domains\Metrics\Metrics\AverageTransactionMetric;
use App\Domains\Metrics\Metrics\AccountStatsMetric;
use App\Domains\Metrics\Metrics\CirclePackMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    public function totalIncome(Request $request): JsonResponse
    {
        $metric = new TotalIncomeMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function totalExpenses(Request $request): JsonResponse
    {
        $metric = new TotalExpensesMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function totalAssets(Request $request): JsonResponse
    {
        $metric = new TotalAssetsMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function totalLiabilities(Request $request): JsonResponse
    {
        $metric = new TotalLiabilitiesMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function totalEquity(Request $request): JsonResponse
    {
        $metric = new TotalEquityMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function netWorth(): JsonResponse
    {
        $metric = new NetWorthMetric();
        return response()->json(['data' => $metric->calculate()]);
    }

    public function netWorthTrend(Request $request): JsonResponse
    {
        $metric = new NetWorthTrendMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function totalIncomeTrend(Request $request): JsonResponse
    {
        $metric = new TotalIncomeTrendMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function totalExpensesTrend(Request $request): JsonResponse
    {
        $metric = new TotalExpensesTrendMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function accountTrend(Request $request): JsonResponse
    {
        $metric = new AccountTrendMetric(
            $request->query('from'),
            $request->query('to'),
            (int) $request->query('id')
        );
        return response()->json(['data' => $metric->calculate()]);
    }

    public function accountDailyTrend(Request $request): JsonResponse
    {
        $metric = new AccountDailyTrendMetric(
            $request->query('from'),
            $request->query('to'),
            (int) $request->query('id')
        );
        return response()->json(['data' => $metric->calculate()]);
    }

    public function expensesByAccount(Request $request): JsonResponse
    {
        $metric = new ExpensesByAccountMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function incomeByAccount(Request $request): JsonResponse
    {
        $metric = new IncomeByAccountMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function transactionsCount(Request $request): JsonResponse
    {
        $metric = new TransactionsCountMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function highestTransaction(Request $request): JsonResponse
    {
        $metric = new HighestTransactionMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function lowestTransaction(Request $request): JsonResponse
    {
        $metric = new LowestTransactionMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function averageTransaction(Request $request): JsonResponse
    {
        $metric = new AverageTransactionMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function accountStats(Request $request): JsonResponse
    {
        $metric = new AccountStatsMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }

    public function circlePack(Request $request): JsonResponse
    {
        $metric = new CirclePackMetric($request->query('from'), $request->query('to'));
        return response()->json(['data' => $metric->calculate()]);
    }
}
