<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_files', function (Blueprint $table) {
            $table->string('attachable_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('uploaded_files', function (Blueprint $table) {
                $table->unsignedBigInteger('attachable_id')->nullable()->change();
            });

            return;
        }

        Schema::table('uploaded_files', function (Blueprint $table) {
            $table->unsignedBigInteger('attachable_id')->nullable()->change();
        });
    }
};