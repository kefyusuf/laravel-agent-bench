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
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_syncs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('item_count');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_syncs');
        Schema::dropIfExists('stock_items');
    }
};
