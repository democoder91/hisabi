<?php

namespace Tests\Feature;

use App\Domains\Account\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAuditPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_the_account_audit_page(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/accounts/{$account->id}/audit");

        $response->assertOk();
    }

    public function test_shared_user_cannot_open_the_account_audit_page(): void
    {
        $owner = User::factory()->create();
        $sharedUser = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($sharedUser->id, ['permission_level' => Account::PERMISSION_EDIT]);

        $response = $this->actingAs($sharedUser)->get("/accounts/{$account->id}/audit");

        $response->assertStatus(403);
    }
}