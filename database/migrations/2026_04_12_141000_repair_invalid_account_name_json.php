<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('accounts')->select(['id', 'name'])->orderBy('id')->cursor() as $account) {
            if ($this->isJsonObject($account->name)) {
                continue;
            }

            DB::table('accounts')
                ->where('id', $account->id)
                ->update([
                    'name' => json_encode(['en' => (string) $account->name], JSON_UNESCAPED_UNICODE),
                ]);
        }
    }

    public function down(): void
    {
    }

    private function isJsonObject(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded);
    }
};