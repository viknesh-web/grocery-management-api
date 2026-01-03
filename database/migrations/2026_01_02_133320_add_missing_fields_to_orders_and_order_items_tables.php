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
        // Update orders table
        Schema::table('orders', function (Blueprint $table) {
            // Make customer_id nullable for guest orders
            $table->foreignId('customer_id')->nullable()->change();
            
            // Add customer snapshot fields for guest orders
            $table->string('customer_name')->nullable()->after('customer_id');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('customer_phone')->nullable()->after('customer_email');
            $table->text('customer_address')->nullable()->after('customer_phone');
            
            // Add payment status
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid')->after('status');
            
            // Add admin notes
            $table->text('admin_notes')->nullable()->after('notes');
        });

        // Update order_items table
        Schema::table('order_items', function (Blueprint $table) {
            // Add product snapshot fields
            $table->string('product_name')->nullable()->after('product_id');
            $table->string('product_code')->nullable()->after('product_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'customer_email',
                'customer_phone',
                'customer_address',
                'payment_status',
                'admin_notes',
            ]);
            
            // Note: Making customer_id non-nullable again might fail if there are guest orders
            // This is intentional - you may need to handle this manually
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'product_code']);
        });
    }
};
