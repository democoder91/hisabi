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
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Landing');
})->name('landing');

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
        $authenticatedUser = Auth::user();

        $data = [
            'sections' => app(ReportManager::class)->generate($start_date, $end_date),
            'currency' => $authenticatedUser ? $authenticatedUser->default_currency : config('hisabi.currency'),
            'range' => $start_date && $end_date ? $start_date . ' - ' . $end_date : now()->format('F Y')
        ];

        return view('report', $data);
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');

    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::post('/register', [RegisteredUserController::class, 'store']);
});
