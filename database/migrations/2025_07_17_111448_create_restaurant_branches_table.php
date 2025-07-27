<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Added DB facade import

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('restaurant_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug');
            $table->text('address');
            $table->string('city');
            $table->string('state');
            $table->string('postal_code');
            $table->string('country')->default('SA');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('manager_name')->nullable();
            $table->string('manager_phone')->nullable();
            $table->jsonb('operating_hours'); // Branch-specific operating hours
            $table->jsonb('delivery_zones'); // Polygon coordinates for delivery areas
            $table->decimal('delivery_fee', 8, 2)->default(0.00);
            $table->decimal('minimum_order_amount', 8, 2)->default(0.00);
            $table->integer('estimated_delivery_time')->default(30); // minutes
            $table->enum('status', ['active', 'inactive', 'temporarily_closed'])->default('active');
            $table->boolean('accepts_online_orders')->default(true);
            $table->boolean('accepts_delivery')->default(true);
            $table->boolean('accepts_pickup')->default(true);
            $table->jsonb('settings')->default('{}'); // Branch-specific settings
            $table->timestamps();
            
            // Indexes for frequently queried columns
            $table->index(['restaurant_id', 'status']);
            $table->index(['city', 'status']);
            $table->index('status');
            $table->index(['latitude', 'longitude']); // Geospatial queries
            $table->unique(['restaurant_id', 'slug']); // Unique slug per restaurant
            
            // Removed: Geospatial index for location-based queries (will be in a separate migration)
            // DB::statement('CREATE INDEX restaurant_branches_location_idx ON restaurant_branches USING gist(point(longitude, latitude))');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_branches');
    }
};
