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
        Schema::create('driver_working_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->onDelete('cascade');
            $table->string('zone_name');
            $table->text('zone_description')->nullable();
            $table->json('coordinates'); // Store center coordinates as JSON
            $table->decimal('radius_km', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->integer('priority_level')->default(1);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();
            
            // Add indexes for performance (without JSON index)
            $table->index(['is_active', 'priority_level']);
            $table->index(['driver_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_working_zones');
    }
};
