<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_it_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/accounts');

        $response->assertStatus(401);
    }

    public function test_it_returns_only_owned_accounts(): void
    {
        Account::factory()->create(['user_id' => $this->user->id]);
        $sharedAccount = Account::factory()->create();
        $sharedAccount->sharedUsers()->attach($this->user->id, ['permission_level' => Account::PERMISSION_VIEW]);
        Account::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/accounts');

        $response->assertOk()
            ->assertJsonPath('paginatorInfo.total', 2)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'name_translations', 'balance', 'transactionsCount', 'created_at', 'isOwner', 'canManage', 'canEditTransactions', 'permissionLevel'],
                ],
            ]);

        $this->assertContains($sharedAccount->id, collect($response->json('data'))->pluck('id'));
        $this->assertContains('owner', collect($response->json('data'))->pluck('permissionLevel'));
        $this->assertContains(Account::PERMISSION_VIEW, collect($response->json('data'))->pluck('permissionLevel'));
    }

    public function test_it_creates_an_account(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/accounts', [
            'name' => [
                'en' => 'Emergency Fund',
                'ar' => 'صندوق الطوارئ',
            ],
            'balance' => 1250.75,
            'currency' => 'usd',
        ]);

        $response->assertCreated()
            ->assertJsonPath('account.name', 'Emergency Fund')
            ->assertJsonPath('account.name_translations.en', 'Emergency Fund')
            ->assertJsonPath('account.name_translations.ar', 'صندوق الطوارئ')
            ->assertJsonPath('account.balance', 1250.75)
            ->assertJsonPath('account.currency', 'USD');

        $account = Account::query()->where('user_id', $this->user->id)->latest('id')->firstOrFail();

        $this->assertSame('Emergency Fund', $account->getTranslation('name', 'en'));
        $this->assertSame('صندوق الطوارئ', $account->getTranslation('name', 'ar'));
        $this->assertSame('USD', $account->currency);
    }

    public function test_it_shows_an_accessible_account(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Travel Fund', 'ar' => 'صندوق السفر'],
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/accounts/{$account->id}");

        $response->assertOk()
            ->assertJsonPath('account.id', $account->id)
            ->assertJsonPath('account.name', 'Travel Fund')
            ->assertJsonPath('account.name_translations.ar', 'صندوق السفر');
    }

    public function test_it_updates_an_account(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Checking'],
            'balance' => 200,
            'currency' => 'AED',
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/accounts/{$account->id}", [
            'name' => [
                'en' => 'Main Checking',
                'ar' => 'الحساب الرئيسي',
            ],
            'balance' => 450.50,
            'currency' => 'sar',
        ]);

        $response->assertOk()
            ->assertJsonPath('account.name', 'Main Checking')
            ->assertJsonPath('account.name_translations.en', 'Main Checking')
            ->assertJsonPath('account.name_translations.ar', 'الحساب الرئيسي')
            ->assertJsonPath('account.balance', 450.5)
            ->assertJsonPath('account.currency', 'SAR');

        $account->refresh();
        $this->assertSame('Main Checking', $account->getTranslation('name', 'en'));
        $this->assertSame('الحساب الرئيسي', $account->getTranslation('name', 'ar'));
        $this->assertSame(450.5, $account->balance);
        $this->assertSame('SAR', $account->currency);
    }

    public function test_it_filters_accounts_by_translated_name(): void
    {
        Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Emergency Fund', 'ar' => 'صندوق الطوارئ'],
        ]);

        Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Savings', 'ar' => 'المدخرات'],
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/accounts?filter[search]=الطوارئ');

        $response->assertOk()
            ->assertJsonPath('paginatorInfo.total', 1)
            ->assertJsonPath('data.0.name', 'Emergency Fund')
            ->assertJsonPath('data.0.name_translations.ar', 'صندوق الطوارئ');
    }

    public function test_it_handles_legacy_plain_string_account_names(): void
    {
        DB::table('accounts')->insert([
            'user_id' => $this->user->id,
            'name' => 'Legacy Checking',
            'balance' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/accounts');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Legacy Checking')
            ->assertJsonPath('data.0.name_translations.en', 'Legacy Checking');
    }

    public function test_it_can_search_legacy_plain_string_account_names(): void
    {
        DB::table('accounts')->insert([
            'user_id' => $this->user->id,
            'name' => 'Legacy Savings',
            'balance' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/accounts?filter[search]=Savings');

        $response->assertOk()
            ->assertJsonPath('paginatorInfo.total', 1)
            ->assertJsonPath('data.0.name', 'Legacy Savings');
    }

    public function test_it_deletes_an_account(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/accounts/{$account->id}");

        $response->assertOk()
            ->assertJsonPath('account.id', $account->id);

        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    }

    public function test_it_returns_404_when_updating_another_users_account(): void
    {
        $account = Account::factory()->create();

        $response = $this->actingAs($this->user)->putJson("/api/v1/accounts/{$account->id}", [
            'name' => ['en' => 'Blocked'],
            'balance' => 99,
            'currency' => 'USD',
        ]);

        $response->assertStatus(404);
    }

    public function test_it_returns_404_when_showing_another_users_account(): void
    {
        $account = Account::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/accounts/{$account->id}");

        $response->assertNotFound();
    }

    public function test_it_returns_404_when_deleting_another_users_account(): void
    {
        $account = Account::factory()->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/accounts/{$account->id}");

        $response->assertStatus(404);
    }

    public function test_owner_can_invite_a_user_to_a_shared_account(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $invitee = User::factory()->create();

        $response = $this->actingAs($this->user)->postJson("/api/v1/accounts/{$account->id}/shares", [
            'email' => $invitee->email,
            'permission_level' => Account::PERMISSION_VIEW,
        ]);

        $response->assertOk()
            ->assertJsonPath('account.sharedUsers.0.email', $invitee->email)
            ->assertJsonPath('account.sharedUsers.0.permissionLevel', Account::PERMISSION_VIEW);

        $this->assertDatabaseHas('account_user', [
            'account_id' => $account->id,
            'user_id' => $invitee->id,
            'permission_level' => Account::PERMISSION_VIEW,
        ]);
    }

    public function test_owner_can_search_shareable_users_by_email(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $matchingUser = User::factory()->create(['email' => 'jane.doe@example.com']);
        $alreadySharedUser = User::factory()->create(['email' => 'james.shared@example.com']);
        User::factory()->create(['email' => 'other.person@example.com']);

        $account->sharedUsers()->attach($alreadySharedUser->id, ['permission_level' => Account::PERMISSION_VIEW]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/accounts/{$account->id}/shareable-users?search=jan");

        $response->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $matchingUser->id)
            ->assertJsonPath('users.0.email', $matchingUser->email);
    }

    public function test_shareable_user_search_excludes_the_owner_and_existing_shared_users(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $alreadySharedUser = User::factory()->create(['email' => 'shared-match@example.com']);
        $availableUser = User::factory()->create(['email' => 'shareable-match@example.com']);

        $account->sharedUsers()->attach($alreadySharedUser->id, ['permission_level' => Account::PERMISSION_VIEW]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/accounts/{$account->id}/shareable-users?search=match");

        $emails = collect($response->json('users'))->pluck('email')->all();

        $response->assertOk();

        $this->assertNotContains($this->user->email, $emails);
        $this->assertNotContains($alreadySharedUser->email, $emails);
        $this->assertContains($availableUser->email, $emails);
    }

    public function test_owner_can_update_a_shared_users_permission(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $sharedUser = User::factory()->create();
        $account->sharedUsers()->attach($sharedUser->id, ['permission_level' => Account::PERMISSION_VIEW]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/accounts/{$account->id}/shares/{$sharedUser->id}", [
            'permission_level' => Account::PERMISSION_EDIT,
        ]);

        $response->assertOk()
            ->assertJsonPath('account.sharedUsers.0.permissionLevel', Account::PERMISSION_EDIT);

        $this->assertDatabaseHas('account_user', [
            'account_id' => $account->id,
            'user_id' => $sharedUser->id,
            'permission_level' => Account::PERMISSION_EDIT,
        ]);
    }

    public function test_owner_can_revoke_a_shared_user(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $sharedUser = User::factory()->create();
        $account->sharedUsers()->attach($sharedUser->id, ['permission_level' => Account::PERMISSION_VIEW]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/accounts/{$account->id}/shares/{$sharedUser->id}");

        $response->assertOk()
            ->assertJsonCount(0, 'account.sharedUsers');

        $this->assertSoftDeleted('account_user', [
            'account_id' => $account->id,
            'user_id' => $sharedUser->id,
        ]);
    }

    public function test_owner_can_reinvite_a_previously_revoked_shared_user(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $sharedUser = User::factory()->create();

        $account->sharedUsers()->attach($sharedUser->id, ['permission_level' => Account::PERMISSION_VIEW]);

        $this->actingAs($this->user)->deleteJson("/api/v1/accounts/{$account->id}/shares/{$sharedUser->id}")
            ->assertOk();

        $response = $this->actingAs($this->user)->postJson("/api/v1/accounts/{$account->id}/shares", [
            'email' => $sharedUser->email,
            'permission_level' => Account::PERMISSION_EDIT,
        ]);

        $response->assertOk()
            ->assertJsonPath('account.sharedUsers.0.id', $sharedUser->id)
            ->assertJsonPath('account.sharedUsers.0.permissionLevel', Account::PERMISSION_EDIT);

        $this->assertDatabaseHas('account_user', [
            'account_id' => $account->id,
            'user_id' => $sharedUser->id,
            'permission_level' => Account::PERMISSION_EDIT,
            'deleted_at' => null,
        ]);
    }

    public function test_shared_user_cannot_update_account_metadata(): void
    {
        $account = Account::factory()->create();
        $account->sharedUsers()->attach($this->user->id, ['permission_level' => Account::PERMISSION_EDIT]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/accounts/{$account->id}", [
            'name' => ['en' => 'Not Allowed'],
            'balance' => 999,
            'currency' => 'USD',
        ]);

        $response->assertStatus(403);
    }

    public function test_shared_user_cannot_manage_account_sharing(): void
    {
        $account = Account::factory()->create();
        $account->sharedUsers()->attach($this->user->id, ['permission_level' => Account::PERMISSION_EDIT]);
        $invitee = User::factory()->create();

        $inviteResponse = $this->actingAs($this->user)->postJson("/api/v1/accounts/{$account->id}/shares", [
            'email' => $invitee->email,
            'permission_level' => Account::PERMISSION_VIEW,
        ]);

        $inviteResponse->assertStatus(403);
    }

    public function test_shared_user_cannot_search_shareable_users(): void
    {
        $account = Account::factory()->create();
        $account->sharedUsers()->attach($this->user->id, ['permission_level' => Account::PERMISSION_EDIT]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/accounts/{$account->id}/shareable-users?search=user");

        $response->assertStatus(403);
    }
}
