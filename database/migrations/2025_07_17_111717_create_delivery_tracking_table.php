<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->foreignId('order_assignment_id')->nullable()->constrained('order_assignments')->onDelete('cascade');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 8, 2)->nullable();
            $table->decimal('altitude', 8, 2)->nullable();
            $table->timestamp('timestamp');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['driver_id', 'timestamp']);
            $table->index(['order_assignment_id', 'timestamp']);
        });

        // Create spatial index for coordinates using PostgreSQL-specific syntax
        DB::statement('CREATE INDEX delivery_tracking_location_idx ON delivery_tracking USING gist (point(longitude, latitude))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the spatial index first
        DB::statement('DROP INDEX IF EXISTS delivery_tracking_location_idx');
        
        Schema::dropIfExists('delivery_tracking');
    }
};
