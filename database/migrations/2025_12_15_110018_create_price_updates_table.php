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
        Schema::create('price_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('old_original_price', 10, 2)->nullable();
            $table->decimal('new_original_price', 10, 2)->nullable();
            $table->enum('old_discount_type', ['none', 'percentage', 'fixed'])->nullable();
            $table->enum('new_discount_type', ['none', 'percentage', 'fixed'])->nullable();
            $table->decimal('old_discount_value', 10, 2)->nullable();
            $table->decimal('new_discount_value', 10, 2)->nullable();
            $table->decimal('old_stock_quantity', 10, 2)->nullable();
            $table->decimal('new_stock_quantity', 10, 2)->nullable();
            $table->decimal('new_selling_price', 10, 2)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_updates');
    }
};
