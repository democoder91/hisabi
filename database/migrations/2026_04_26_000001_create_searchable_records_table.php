<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('searchable_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('searchable_type', 191);
            $table->unsignedBigInteger('searchable_id');
            $table->string('field', 64);
            $table->string('locale', 10)->nullable();
            $table->text('content');
            $table->json('embedding');
            $table->string('embedding_provider', 64)->nullable();
            $table->string('embedding_model', 128)->nullable();
            $table->unsignedInteger('embedding_dimensions')->nullable();
            $table->timestamp('embedded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'searchable_type'], 'searchable_records_user_type_index');
            $table->index(['searchable_type', 'searchable_id'], 'searchable_records_morph_index');
            $table->unique(
                ['searchable_type', 'searchable_id', 'field', 'locale'],
                'searchable_records_unique_doc',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('searchable_records');
    }
};
