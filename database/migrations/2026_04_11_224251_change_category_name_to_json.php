<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing string names to JSON {"en": "value"}
        DB::statement('UPDATE categories SET name = JSON_OBJECT("en", name) WHERE name NOT LIKE "{%"');

        Schema::table('categories', function (Blueprint $table) {
            $table->json('name')->change();
        });
    }

    public function down(): void
    {
        // Revert: extract the English name back to a plain string
        DB::statement('UPDATE categories SET name = JSON_UNQUOTE(JSON_EXTRACT(name, "$.en")) WHERE name LIKE "{%"');

        Schema::table('categories', function (Blueprint $table) {
            $table->string('name')->change();
        });
    }
};
