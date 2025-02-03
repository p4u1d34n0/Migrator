<?php

use App\MigrationHandler\Schema;
use App\MigrationHandler\Blueprint;
use App\MigrationHandler\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('test1', function (Blueprint $table) {
            $table->id();
            // $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('test1');
    }
};