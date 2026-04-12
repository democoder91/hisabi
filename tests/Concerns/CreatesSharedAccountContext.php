<?php

namespace Tests\Concerns;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Models\User;

trait CreatesSharedAccountContext
{
    protected function createSharedAccountContext(string $permissionLevel = Account::PERMISSION_VIEW): array
    {
        $owner = User::factory()->create();
        $sharedUser = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($sharedUser->id, ['permission_level' => $permissionLevel]);

        $category = Category::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Shared Category'],
            'type' => Category::EXPENSES,
        ]);

        return compact('owner', 'sharedUser', 'account', 'category');
    }
}