<?php

namespace Tests\Feature\Auth;

use App\Domains\Account\Models\Account;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_users_can_register(): void
    {
        Carbon::setTestNow('2026-04-13 10:00:00');

        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'name' => 'New User',
            'available_credits' => 10,
            'trial_ends_at' => '2026-05-13 10:00:00',
        ]);

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $accounts = $user->accounts()->get();

        $this->assertCount(2, $accounts);

        $defaultAccount = $accounts->firstWhere(fn(Account $account) => $account->getTranslation('name', 'en') === Account::DEFAULT_NAME);
        $startingBalanceAccount = $accounts->firstWhere(fn(Account $account) => $account->getTranslation('name', 'en') === Account::STARTING_BALANCE_NAME);

        $this->assertNotNull($defaultAccount);
        $this->assertNotNull($startingBalanceAccount);
        $this->assertSame(Account::TYPE_ASSET, $defaultAccount->type);
        $this->assertSame(Account::TYPE_EQUITY, $startingBalanceAccount->type);
        $this->assertSame(0.0, (float) $defaultAccount->balance);
        $this->assertSame(0.0, (float) $startingBalanceAccount->balance);

        Carbon::setTestNow();
    }

    public function test_authenticated_users_are_redirected_away_from_the_registration_screen(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/register');

        $response->assertRedirect('/dashboard');
    }
}
