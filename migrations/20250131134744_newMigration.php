<?php

use DB\Migration;
use DB\Schema;
use DB\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('someTable', function (Blueprint $table) {
            // $table->id();
            // $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('someTable');
    }
};