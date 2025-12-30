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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('item_code', 50)->unique();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('image')->nullable();
            $table->decimal('regular_price', 10, 2)->unsigned();
            $table->decimal('stock_quantity', 10, 2)->default(0)->unsigned();
            $table->string('stock_unit', 50)->default('Kg');
            $table->decimal('min_quantity', 10, 2)->default(0)->unsigned();
            $table->decimal('max_quantity', 10, 2)->nullable()->unsigned();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('product_type', ['standard', 'daily'])->default('standard');
            $table->timestamps();
            $table->index('item_code');
            $table->index('category_id');
            $table->index('status');
            $table->index('product_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};