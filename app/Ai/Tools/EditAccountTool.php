<?php

namespace App\Ai\Tools;

use App\Domains\Account\Services\AccountService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Ai\Tools\Request;
use Stringable;

class EditAccountTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Edit an existing account that the authenticated user owns. Use this to rename an account or adjust its balance. If the user does not know the account ID, list accounts first.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $input = $request->all();

        $this->ensureAnyProvided(
            $input,
            ['name_en', 'name_ar', 'balance'],
            'Provide at least one field to update: name_en, name_ar, or balance.'
        );

        $validated = $this->validateInput($input, [
            'account_id' => ['required', 'integer'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'balance' => ['nullable', 'numeric'],
        ]);

        $account = $this->accessibleAccount((int) $validated['account_id'], $user);

        if (! $account->isOwnedBy($user)) {
            throw new \RuntimeException('Only the account owner can edit account details.');
        }

        $payload = [];
        $translations = $this->normalizeNameTranslations($input, $account->getSafeNameTranslations());

        if ($translations !== null) {
            $payload['name'] = $translations;
        }

        if (Arr::exists($validated, 'balance')) {
            $payload['balance'] = (float) $validated['balance'];
        }

        $updated = app(AccountService::class)->update($account, $payload);

        return 'Account updated successfully: ' . $this->formatAccount($updated);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()
                ->description('The ID of the account to update.')
                ->required(),
            'name_en' => $schema->string()
                ->description('Optional new English name for the account.')
                ->nullable(),
            'name_ar' => $schema->string()
                ->description('Optional new Arabic translation for the account name. Use null to clear it.')
                ->nullable(),
            'balance' => $schema->number()
                ->description('Optional replacement balance for the account.')
                ->nullable(),
        ];
    }
}