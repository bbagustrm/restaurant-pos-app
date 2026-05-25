<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Table renamed from "tables" to "restaurant_tables" to avoid SQL reserved word conflict.
     */
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 50);
            $table->integer('capacity')->default(4);
            $table->string('status', 20)->default('available'); // available | occupied | reserved
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
