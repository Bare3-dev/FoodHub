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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->date('date_of_birth');
            $table->string('national_id')->unique();
            $table->string('driver_license_number')->unique();
            $table->date('license_expiry_date');
            $table->string('profile_image_url')->nullable();
            $table->string('license_image_url')->nullable();
            $table->string('vehicle_type'); // motorcycle, car, bicycle
            $table->string('vehicle_make')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('vehicle_year')->nullable();
            $table->string('vehicle_color')->nullable();
            $table->string('vehicle_plate_number')->unique();
            $table->string('vehicle_image_url')->nullable();
            $table->enum('status', ['pending_verification', 'active', 'inactive', 'suspended', 'blocked'])->default('pending_verification');
            $table->boolean('is_online')->default(false);
            $table->boolean('is_available')->default(false);
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->timestamp('last_location_update')->nullable();
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->integer('total_deliveries')->default(0);
            $table->integer('completed_deliveries')->default(0);
            $table->integer('cancelled_deliveries')->default(0);
            $table->decimal('total_earnings', 10, 2)->default(0.00);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->jsonb('documents')->default('{}'); // Storage for verification documents
            $table->jsonb('banking_info')->default('{}'); // Encrypted banking information
            $table->rememberToken();
            $table->timestamps();
            
            // Indexes for frequently queried columns
            $table->index(['status', 'created_at']);
            $table->index(['is_online', 'is_available']);
            $table->index(['current_latitude', 'current_longitude']); // Location-based queries
            $table->index('rating');
            $table->index('email');
            $table->index('phone');
            
            // Removed: Geospatial index for location-based queries (will be in a separate migration)
            // DB::statement('CREATE INDEX drivers_location_idx ON drivers USING gist(point(current_longitude, current_latitude))');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
