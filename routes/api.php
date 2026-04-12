<?php

use App\Http\Controllers\Api\V1\AIController;
use App\Http\Controllers\Api\V1\AccountAuditController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\Settings\CurrencySettingsController;
use App\Http\Controllers\Api\V1\SmsController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\TranscriptionController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/sms-test', [SmsController::class, 'store']);

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
    });

    Route::middleware(['auth:sanctum', SetLocale::class])->group(function () {
        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        Route::get('/transactions/form-options', [TransactionController::class, 'formOptions']);
        Route::apiResource('transactions', TransactionController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::get('/accounts', [AccountController::class, 'index']);
        Route::get('/accounts/all', [AccountController::class, 'all']);
        Route::post('/accounts', [AccountController::class, 'store']);
        Route::get('/accounts/{id}', [AccountController::class, 'show']);
        Route::put('/accounts/{id}', [AccountController::class, 'update']);
        Route::delete('/accounts/{id}', [AccountController::class, 'destroy']);
        Route::get('/accounts/{id}/shareable-users', [AccountController::class, 'searchShareableUsers']);
        Route::post('/accounts/{id}/shares', [AccountController::class, 'share']);
        Route::put('/accounts/{id}/shares/{shareUserId}', [AccountController::class, 'updateShare']);
        Route::delete('/accounts/{id}/shares/{shareUserId}', [AccountController::class, 'revokeShare']);
        Route::get('/accounts/{account}/audits', [AccountAuditController::class, 'index']);

        Route::get('/sms', [SmsController::class, 'index']);
        Route::post('/sms', [SmsController::class, 'store']);
        Route::put('/sms/{id}', [SmsController::class, 'update']);
        Route::delete('/sms/{id}', [SmsController::class, 'destroy']);

        Route::get('/categories/all', [CategoryController::class, 'all']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::get('/categories/{id}', [CategoryController::class, 'show']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        Route::get('/budgets', [BudgetController::class, 'index']);
        Route::post('/budgets', [BudgetController::class, 'store']);
        Route::get('/budgets/{id}', [BudgetController::class, 'show']);
        Route::put('/budgets/{id}', [BudgetController::class, 'update']);
        Route::delete('/budgets/{id}', [BudgetController::class, 'destroy']);

        Route::post('/ai/chat', [AIController::class, 'chat']);
        Route::post('/ai/transcribe', [TranscriptionController::class, 'transcribe']);
        Route::post('/ai/transcribe/token', [TranscriptionController::class, 'token']);
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::get('/settings/currencies', [CurrencySettingsController::class, 'show']);
        Route::put('/settings/currencies', [CurrencySettingsController::class, 'updatePreference']);
        Route::put('/settings/currencies/rates', [CurrencySettingsController::class, 'updateRates']);
        Route::post('/settings/currencies/refresh', [CurrencySettingsController::class, 'refresh']);
        Route::put('/user/profile', [UserController::class, 'updateProfile']);

        Route::prefix('metrics')->group(function () {
            Route::get('/total-income', [MetricsController::class, 'totalIncome']);
            Route::get('/total-expenses', [MetricsController::class, 'totalExpenses']);
            Route::get('/total-savings', [MetricsController::class, 'totalSavings']);
            Route::get('/total-investment', [MetricsController::class, 'totalInvestment']);
            Route::get('/total-cash', [MetricsController::class, 'totalCash']);
            Route::get('/net-worth', [MetricsController::class, 'netWorth']);
            Route::get('/net-worth-trend', [MetricsController::class, 'netWorthTrend']);
            Route::get('/total-income-trend', [MetricsController::class, 'totalIncomeTrend']);
            Route::get('/total-expenses-trend', [MetricsController::class, 'totalExpensesTrend']);
            Route::get('/category-trend', [MetricsController::class, 'categoryTrend']);
            Route::get('/category-daily-trend', [MetricsController::class, 'categoryDailyTrend']);
            Route::get('/expenses-by-category', [MetricsController::class, 'expensesByCategory']);
            Route::get('/income-by-category', [MetricsController::class, 'incomeByCategory']);
            Route::get('/transactions-count', [MetricsController::class, 'transactionsCount']);
            Route::get('/transactions-by-category', [MetricsController::class, 'transactionsByCategory']);
            Route::get('/highest-transaction', [MetricsController::class, 'highestTransaction']);
            Route::get('/lowest-transaction', [MetricsController::class, 'lowestTransaction']);
            Route::get('/average-transaction', [MetricsController::class, 'averageTransaction']);
            Route::get('/transactions-std-dev', [MetricsController::class, 'transactionsStdDev']);
            Route::get('/category-stats', [MetricsController::class, 'categoryStats']);
            Route::get('/circle-pack', [MetricsController::class, 'circlePack']);
        });
    });
});
