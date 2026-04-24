<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'type')) {
                $table->enum('type', \App\Domains\Account\Models\Account::ledgerTypes())
                    ->default(\App\Domains\Account\Models\Account::TYPE_ASSET)
                    ->after('name');
            }

            if (! Schema::hasColumn('accounts', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete()->after('type');
            }

            if (! Schema::hasColumn('accounts', 'color')) {
                $table->string('color', 50)->nullable()->after('currency');
            }

            if (! Schema::hasColumn('accounts', 'icon')) {
                $table->string('icon', 50)->nullable()->after('color');
            }
        });

        if (Schema::hasColumn('accounts', 'type') && ! collect(\DB::select("SHOW INDEX FROM accounts WHERE Key_name = 'accounts_user_id_type_index'"))->count()) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->index(['user_id', 'type'], 'accounts_user_id_type_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex('accounts_user_id_type_index');
            $table->dropColumn(['type', 'parent_id', 'color', 'icon']);
        });
    }
};
