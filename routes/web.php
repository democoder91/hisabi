<?php

use Illuminate\Http\Request;
use App\Contracts\ReportManager;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountAuditController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\MetricsController;

Route::redirect('/', '/dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts');
    Route::get('/accounts/{account}/audit', [AccountAuditController::class, 'show'])->name('accounts.audit');
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions');
    Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets');
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::get('/report', function (Request $request) {
        $start_date = $request->query('start_date');
        $end_date = $request->query('end_date');

        $data = [
            'sections' => app(ReportManager::class)->generate($start_date, $end_date),
            'currency' => auth()->user()->default_currency ?? config('hisabi.currency'),
            'range' => $start_date && $end_date ? $start_date . ' - ' . $end_date : now()->format('F Y')
        ];

        return view('report', $data);
    });

    Route::prefix('api/v1')->group(function () {
        Route::apiResource('transactions', \App\Http\Controllers\Api\V1\TransactionController::class)
            ->except(['show']);
        Route::get('/transactions/form-options', [\App\Http\Controllers\Api\V1\TransactionController::class, 'formOptions']);
        Route::get('/accounts', [\App\Http\Controllers\Api\V1\AccountController::class, 'index']);
        Route::get('/accounts/all', [\App\Http\Controllers\Api\V1\AccountController::class, 'all']);
        Route::post('/accounts', [\App\Http\Controllers\Api\V1\AccountController::class, 'store']);
        Route::put('/accounts/{id}', [\App\Http\Controllers\Api\V1\AccountController::class, 'update']);
        Route::delete('/accounts/{id}', [\App\Http\Controllers\Api\V1\AccountController::class, 'destroy']);
        Route::get('/accounts/{id}/shareable-users', [\App\Http\Controllers\Api\V1\AccountController::class, 'searchShareableUsers']);
        Route::post('/accounts/{id}/shares', [\App\Http\Controllers\Api\V1\AccountController::class, 'share']);
        Route::put('/accounts/{id}/shares/{shareUserId}', [\App\Http\Controllers\Api\V1\AccountController::class, 'updateShare']);
        Route::delete('/accounts/{id}/shares/{shareUserId}', [\App\Http\Controllers\Api\V1\AccountController::class, 'revokeShare']);
        Route::get('/accounts/{account}/audits', [\App\Http\Controllers\Api\V1\AccountAuditController::class, 'index']);
        Route::get('/sms', [\App\Http\Controllers\Api\V1\SmsController::class, 'index']);
        Route::post('/sms', [\App\Http\Controllers\Api\V1\SmsController::class, 'store']);
        Route::put('/sms/{id}', [\App\Http\Controllers\Api\V1\SmsController::class, 'update']);
        Route::delete('/sms/{id}', [\App\Http\Controllers\Api\V1\SmsController::class, 'destroy']);
        Route::get('/categories/all', [\App\Http\Controllers\Api\V1\CategoryController::class, 'all']);
        Route::post('/categories', [\App\Http\Controllers\Api\V1\CategoryController::class, 'store']);
        Route::put('/categories/{id}', [\App\Http\Controllers\Api\V1\CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [\App\Http\Controllers\Api\V1\CategoryController::class, 'destroy']);
        Route::get('/budgets', [\App\Http\Controllers\Api\V1\BudgetController::class, 'index']);
        Route::post('/budgets', [\App\Http\Controllers\Api\V1\BudgetController::class, 'store']);
        Route::put('/budgets/{id}', [\App\Http\Controllers\Api\V1\BudgetController::class, 'update']);
        Route::delete('/budgets/{id}', [\App\Http\Controllers\Api\V1\BudgetController::class, 'destroy']);
        Route::post('/ai/chat', [\App\Http\Controllers\Api\V1\AIController::class, 'chat']);
        Route::post('/ai/transcribe/token', [\App\Http\Controllers\Api\V1\TranscriptionController::class, 'token']);
        Route::put('/user/profile', [\App\Http\Controllers\Api\V1\UserController::class, 'updateProfile']);

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

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');

    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::post('/register', [RegisteredUserController::class, 'store']);
});