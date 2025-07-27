<?php

declare(strict_types=1);

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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // Human-readable order number
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_address_id')->nullable()->constrained()->onDelete('set null');
            
            // Order Status Management
            $table->enum('status', [
                'pending',           // Order placed, awaiting confirmation
                'confirmed',         // Restaurant confirmed the order
                'preparing',         // Kitchen is preparing the order
                'ready_for_pickup',  // Order ready for pickup/delivery
                'out_for_delivery',  // Driver picked up the order
                'delivered',         // Order successfully delivered
                'completed',         // Order completed and closed
                'cancelled',         // Order cancelled
                'refunded'          // Order refunded
            ])->default('pending');
            
            // Order Type and Fulfillment
            $table->enum('type', ['delivery', 'pickup', 'dine_in'])->default('delivery');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->enum('payment_method', ['cash', 'card', 'wallet', 'apple_pay', 'google_pay'])->nullable();
            
            // Pricing Information
            $table->decimal('subtotal', 10, 2); // Total before taxes and fees
            $table->decimal('tax_amount', 8, 2)->default(0.00);
            $table->decimal('delivery_fee', 8, 2)->default(0.00);
            $table->decimal('service_fee', 8, 2)->default(0.00);
            $table->decimal('discount_amount', 8, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2); // Final amount charged
            $table->string('currency', 3)->default('SAR');
            
            // Timing Information
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('estimated_preparation_time')->nullable(); // minutes
            $table->integer('estimated_delivery_time')->nullable(); // minutes
            
            // Customer Information
            $table->string('customer_name')->nullable(); // For guest orders
            $table->string('customer_phone')->nullable(); // For guest orders
            $table->text('delivery_address')->nullable(); // Full address text
            $table->text('delivery_notes')->nullable();
            $table->text('special_instructions')->nullable();
            
            // Payment Information
            $table->string('payment_transaction_id')->nullable();
            $table->jsonb('payment_data')->default('{}'); // Payment gateway response data
            
            // Loyalty and Promotions
            $table->string('promo_code')->nullable();
            $table->decimal('loyalty_points_earned', 8, 2)->default(0.00);
            $table->decimal('loyalty_points_used', 8, 2)->default(0.00);
            
            // Integration Data
            $table->jsonb('pos_data')->default('{}'); // POS system integration data
            $table->text('cancellation_reason')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->timestamp('refunded_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for frequently queried columns
            $table->index(['customer_id', 'status', 'created_at']);
            $table->index(['restaurant_id', 'status', 'created_at']);
            $table->index(['restaurant_branch_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['payment_status', 'created_at']);
            $table->index('order_number');
            $table->index('type');
            $table->index('confirmed_at');
            $table->index('delivered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
