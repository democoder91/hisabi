<?php

namespace App\Providers;

use App\BusinessLogic\SmsParser;
use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Support\ServiceProvider;
use App\BusinessLogic\SmsTemplateDetector;
use App\BusinessLogic\ReportManager;
use App\BusinessLogic\SmsTransactionProcessor;
use App\Contracts\SmsParser as SmsParserContract;
use App\Contracts\SmsTemplateDetector as SmsTemplateDetectorContract;
use App\Contracts\ReportManager as ReportManagerContract;
use App\Contracts\SmsTransactionProcessor as SmsTransactionProcessorContract;
use App\Observers\AccountSearchObserver;
use App\Observers\BudgetSearchObserver;
use App\Observers\TransactionObserver;
use App\Observers\TransactionSearchObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(SmsParserContract::class, SmsParser::class);
        $this->app->bind(SmsTemplateDetectorContract::class, SmsTemplateDetector::class);
        $this->app->bind(SmsTransactionProcessorContract::class, SmsTransactionProcessor::class);
        $this->app->bind(ReportManagerContract::class, ReportManager::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Transaction::observe(TransactionObserver::class);
        Account::observe(AccountSearchObserver::class);
        Budget::observe(BudgetSearchObserver::class);
        Transaction::observe(TransactionSearchObserver::class);
    }
}
