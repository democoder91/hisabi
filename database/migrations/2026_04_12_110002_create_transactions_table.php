<?php

use App\Domains\Transaction\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('to_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default(config('hisabi.currency'));
            $table->string('transaction_type', 10)->default(Transaction::TYPE_DEBIT);
            $table->text('note')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('date')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at'], 'transactions_user_id_created_at_index');
            $table->index(['from_account_id', 'to_account_id'], 'transactions_from_to_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};