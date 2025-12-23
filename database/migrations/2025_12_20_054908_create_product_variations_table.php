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
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->enum('unit', ['gm', 'kg', 'ml', 'liter', 'piece', 'packet', 'dozen']);
            $table->decimal('price', 10, 2);
            $table->integer('stock_quantity')->default(0);
            $table->string('sku', 100)->unique()->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('product_id');
            $table->index('enabled');
            $table->index(['product_id', 'enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
