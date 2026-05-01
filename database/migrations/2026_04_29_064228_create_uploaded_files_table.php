<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploaded_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('attachable');
            $table->string('purpose', 50)->default('general');
            $table->string('disk', 50)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('extension', 20)->nullable();
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64)->nullable();
            $table->string('visibility', 20)->default('private');
            $table->json('custom_attributes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose', 'created_at'], 'uploaded_files_user_purpose_created_index');
            $table->index(['attachable_type', 'attachable_id'], 'uploaded_files_attachable_index');
            $table->unique(['disk', 'path'], 'uploaded_files_disk_path_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploaded_files');
    }
};
