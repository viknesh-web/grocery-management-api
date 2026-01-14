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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2)->unsigned();
            $table->string('unit', 50)->default('kg');
            $table->decimal('price', 10, 2)->unsigned();
            $table->enum('discount_type', ['none', 'percentage', 'fixed'])->default('none')->nullable();
            $table->decimal('discount_value', 10, 2)->default(0)->unsigned()->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0)->unsigned()->nullable();
            $table->decimal('subtotal', 10, 2)->unsigned();
            $table->decimal('total', 10, 2)->unsigned();
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};