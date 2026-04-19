<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Domains\Account\Models\Account;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->enum('type', Account::ledgerTypes())->default(Account::TYPE_ASSET);
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('currency', 3)->default(config('hisabi.currency'));
            $table->string('color', 50)->nullable();
            $table->string('icon', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type'], 'accounts_user_id_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};