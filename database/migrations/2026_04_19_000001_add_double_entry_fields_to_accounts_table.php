<?php

use App\Domains\Account\Models\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->enum('type', Account::ledgerTypes())
                ->default(Account::TYPE_ASSET)
                ->after('name');
            $table->foreignId('parent_id')
                ->nullable()
                ->after('type')
                ->constrained('accounts')
                ->nullOnDelete();
            $table->string('color', 50)
                ->nullable()
                ->after('currency');
            $table->string('icon', 50)
                ->nullable()
                ->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['type', 'color', 'icon']);
        });
    }
};