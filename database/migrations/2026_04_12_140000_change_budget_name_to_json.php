<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('budgets')->select(['id', 'name'])->orderBy('id')->cursor() as $budget) {
            if ($this->isJsonObject($budget->name)) {
                continue;
            }

            DB::table('budgets')
                ->where('id', $budget->id)
                ->update([
                    'name' => json_encode(['en' => $budget->name], JSON_UNESCAPED_UNICODE),
                ]);
        }

        Schema::table('budgets', function (Blueprint $table) {
            $table->json('name')->change();
        });
    }

    public function down(): void
    {
        foreach (DB::table('budgets')->select(['id', 'name'])->orderBy('id')->cursor() as $budget) {
            $decoded = json_decode((string) $budget->name, true);

            if (! is_array($decoded)) {
                continue;
            }

            DB::table('budgets')
                ->where('id', $budget->id)
                ->update([
                    'name' => $decoded['en'] ?? reset($decoded) ?: '',
                ]);
        }

        Schema::table('budgets', function (Blueprint $table) {
            $table->string('name')->change();
        });
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