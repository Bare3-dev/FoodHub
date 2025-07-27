<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Added missing import for DB facade

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('label')->default('Home'); // Home, Work, Other
            $table->string('street_address');
            $table->string('apartment_number')->nullable();
            $table->string('building_name')->nullable();
            $table->string('floor_number')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('postal_code');
            $table->string('country')->default('SA');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('delivery_notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_validated')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            
            // Indexes for frequently queried columns
            $table->index(['customer_id', 'is_default']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['city', 'state']);
            $table->index(['latitude', 'longitude']); // Geospatial queries
            
            // Removed: Geospatial index for location-based queries (will be in a separate migration)
            // DB::statement('CREATE INDEX customer_addresses_location_idx ON customer_addresses USING gist(point(longitude, latitude))');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
