<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Currency\CurrencyRateService;
use Illuminate\Console\Command;

class RefreshExchangeRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hisabi:refresh-exchange-rates {userId? : Refresh rates only for a specific user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh per-user exchange rates from the public provider';

    /**
     * Execute the console command.
     */
    public function handle(CurrencyRateService $currencyRateService): int
    {
        $userId = $this->argument('userId');

        $users = $userId
            ? User::query()->whereKey($userId)->get()
            : User::query()->get();

        if ($users->isEmpty()) {
            $this->warn('No users found to refresh exchange rates for.');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $currencyRateService->refreshRates($user);
            $this->info("Refreshed exchange rates for user {$user->id}.");
        }

        return self::SUCCESS;
    }
}
