<?php

namespace App\Domains\User\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function updateProfile(int $id, array $data): User
    {
        $user = User::findOrFail($id);

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }

        if (isset($data['locale'])) {
            $updateData['locale'] = $data['locale'];
        }

        if (isset($data['password']) && !empty($data['password'])) {
            // Verify current password before allowing password change
            if (!isset($data['currentPassword']) || !Hash::check($data['currentPassword'], $user->password)) {
                throw ValidationException::withMessages([
                    'currentPassword' => ['The current password is incorrect.'],
                ]);
            }

            $updateData['password'] = Hash::make($data['password']);
        }

        if (array_key_exists('default_currency', $data)) {
            $updateData['default_currency'] = $data['default_currency'];
        }

        $user->update($updateData);

        return $user->fresh();
    }
}
