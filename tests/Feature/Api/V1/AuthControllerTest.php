<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_a_user_and_returns_a_token(): void
    {
        Carbon::setTestNow('2026-04-13 10:00:00');

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'API User',
            'email' => 'api@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'iphone',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'default_currency', 'locale'],
                'token',
            ])
            ->assertJsonPath('user.email', 'api@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'api@example.com',
            'available_credits' => 10,
            'trial_ends_at' => '2026-05-13 10:00:00',
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'iphone',
        ]);

        $user = User::query()->where('email', 'api@example.com')->firstOrFail();
        $accounts = $user->accounts()->get();

        $this->assertCount(2, $accounts);

        $defaultAccount = $accounts->firstWhere(fn (Account $account) => $account->getTranslation('name', 'en') === Account::DEFAULT_NAME);
        $startingBalanceAccount = $accounts->firstWhere(fn (Account $account) => $account->getTranslation('name', 'en') === Account::STARTING_BALANCE_NAME);

        $this->assertNotNull($defaultAccount);
        $this->assertNotNull($startingBalanceAccount);
        $this->assertSame(Account::TYPE_ASSET, $defaultAccount->type);
        $this->assertSame(Account::TYPE_EQUITY, $startingBalanceAccount->type);
        $this->assertSame(0.0, (float) $defaultAccount->balance);
        $this->assertSame(0.0, (float) $startingBalanceAccount->balance);

        Carbon::setTestNow();
    }

    public function test_it_logs_a_user_in_and_returns_a_token(): void
    {
        $user = User::factory()->create([
            'email' => 'api@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'android',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'android',
        ]);
    }

    public function test_it_rejects_invalid_login_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'api@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_it_returns_the_authenticated_user_for_me_endpoint(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_it_requires_authentication_for_me_endpoint(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_it_logs_out_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_it_requires_authentication_for_logout_endpoint(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }
}
