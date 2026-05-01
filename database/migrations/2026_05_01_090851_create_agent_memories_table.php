<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'accounting_standard',
                'app_instruction',
                'user_terminology',
                'user_context',
            ]);
            $table->text('content');
            $table->json('embedding');
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'type'], 'agent_memories_user_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_memories');
    }
};
